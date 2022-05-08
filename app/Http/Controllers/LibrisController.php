<?php

namespace App\Http\Controllers;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class LibrisController extends Controller
{


    public function home(LibrisInterface $libris)
    {
        return view('sysindex', ['systems' => $libris->systems()]);

    }//end home()


    public function allBySystemList(LibrisInterface $libris, Request $request, $system, $view="systemGrid")
    {
        return $this->allBySystem($libris, $request, $system, 'systemList');

    }//end allBySystemList()


    public function allBySystem(LibrisInterface $libris, Request $request, $system, $view="systemGrid")
    {
        $page    = $request->query('page', 1);
        $perpage = 60;

        if ($system == "null") {
            $system = "";
        } else {
            $system = $system;
        }

        $tag = $request->query('tag', false);

        $docresult = [];

        $documents = $libris->AllBySystem($system, $page, $perpage, $tag);
        $total     = $documents['hits']['total']['value'];

        foreach ($documents['hits']['hits'] as $doc) {
            $tags = $doc['_source']['tags'];
            array_pop($tags);

            $docresult[] = [
                'id'        => $doc['_id'],
                'name'      => urldecode($doc['_source']['title']),
                'path'      => $doc['_source']['path'],
                'thumbnail' => $libris->getThumbnail($doc),
                'tags'      => $tags,
                'download'  => Storage::disk('libris')->url($doc['_source']['path']),
            ];
        }

        $tag_list = $libris->SystemTags($system, $page, $perpage, $tag);

        $paginate = new LengthAwarePaginator(
            [],
            $total,
            $perpage,
            $page,
        );

        $paginate->setPath(url()->current());

        return view(
            $view,
            [
                'system'     => $system,
                'tag'        => $tag,
                'tag_list'   => $tag_list,
                'docs'       => $docresult,
                'page'       => $page,
                'pages'      => ceil($total / $perpage),
                'pagination' => $paginate,
            ]
        );

    }//end allBySystem()


    public function search(LibrisInterface $libris, Request $request)
    {
        $perpage = 20;

        $query    = $request->query('q');
        $system   = $request->query('s', false);
        $document = $request->query('d', false);
        $tag      = $request->query('t', false);
        $page     = $request->query('page', 1);

        $result = $libris->pageSearch($query, $system, $document, $tag, $page, $perpage);

        $total = $result['hits']['total']['value'];

        $appends = ['q' => $query ];

        if ($system) {
            $appends['s'] = $system;
        }

        if ($document) {
            $appends['d'] = $documents;
        }

        if ($tag) {
            $appends['t'] = $tag;
        }

        $paginate = new LengthAwarePaginator(
            [],
            $total,
            $perpage,
            $page,
            [
                'path'    => url()->current(),
                'appends' => $appends,
            ]
        );

        $paginate->appends($appends);

        $values = [
            'systems'    => $result['aggregations']['systems']['buckets'],
            'top_docs'   => $result['aggregations']['parents']['buckets'],
            'tag_list'   => &$result['aggregations']['systems']['buckets'][0]['tags']['buckets'],
            'hits'       => $result['hits']['hits'],
            'query'      => $query,
            'system'     => $system,
            'active_tag' => $tag,
            'document'   => $document,
            'pagination' => $paginate,
        ];

        foreach ($values['hits'] as &$doc) {
            $doc['_source']['download'] = Storage::disk('libris')->url($doc['_source']['path']);
        }

        return view('search', $values);

    }//end search()


    public function showDocument(LibrisInterface $libris, Request $request)
    {
        $file = $request->query('file', false);
        $page = $request->query('page', false);
        $file ? true : abort(400);

        $file = urldecode($file);

        $document = $libris->fetchDocument($file);
        $document ? true : abort(404);

        $values = [
            'system'            => $document['_source']['system'],
            'display_document'  => $document,
            'document_download' => Storage::disk('libris')->url($document['_source']['path']).($page ? '#page='.$page : ''),
            'title'             => $document['_source']['title'],
            'document_page'     => $page,
        ];

        Log::debug($document);

        return view('document', $values);

    }//end showDocument()


}//end class
