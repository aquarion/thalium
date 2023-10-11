<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class LibrisController extends Controller
{


    public function home(LibrisInterface $libris): View
    {
        return view('sysindex', ['systems' => $libris->systems()]);

    }//end home()


    public function docsBySystemList(LibrisInterface $libris, Request $request, $system, $view="systemGrid")
    {
        return $this->docsBySystem($libris, $request, $system, 'systemList');

    }//end docsBySystemList()


    public function docsBySystem(LibrisInterface $libris, Request $request, $system, $view="systemGrid"): View
    {
        $page    = $request->query('page', 1);
        $perpage = 60;

        if ($system == "null") {
            $system = "";
        } else {
            $system = $system;
        }

        $tag = $request->query('tag', 0);

        $docresult = [];

        $documents = $libris->docsBySystem($system, $page, $perpage, $tag);
        $total     = $documents['hits']['total']['value'];

        foreach ($documents['hits']['hits'] as $doc) {
            $tags = $doc['_source']['tags'];
            array_pop($tags);

            $docresult[] = [
                'id'        => $doc['_id'],
                'name'      => urldecode($doc['_source']['title']),
                'path'      => $doc['_source']['path'],
                'thumbnail' => $libris->getDocThumbnail($doc),
                'tags'      => $tags,
                'download'  => Storage::disk('libris')->url($doc['_source']['path']),
            ];
        }

        $tagList = $libris->tagsForSystem($system, $page, $perpage, $tag);

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
                'tagList'    => $tagList,
                'docs'       => $docresult,
                'page'       => $page,
                'pages'      => ceil($total / $perpage),
                'pagination' => $paginate,
            ]
        );

    }//end docsBySystem()


    public function search(LibrisInterface $libris, Request $request): View
    {
        $perpage = 20;

        $query    = $request->query('q');
        $system   = $request->query('s', false);
        $document = $request->query('d', false);
        $tag      = $request->query('t', false);
        $page     = $request->query('page', 1);

        $result = $libris->searchPages($query, $system, $document, $tag, $page, $perpage);

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
            'tagList'    => &$result['aggregations']['systems']['buckets'][0]['tags']['buckets'],
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


    public function showDocument(LibrisInterface $libris, Request $request): View
    {
        $file = $request->query('file', false);
        $page = $request->query('page', false);
        if (!$file) {
            abort(400);
        }

        $file = urldecode($file);

        $document = $libris->fetchDocument($file);
        if (!$document) {
            abort(404);
        }

        $fragment = '';
        if ($page) {
            $fragment = '#page='.$page;
        }

        $values = [
            'system'            => $document['_source']['system'],
            'display_document'  => $document,
            'document_download' => Storage::disk('libris')->url($document['_source']['path']).$fragment,
            'title'             => $document['_source']['title'],
            'document_page'     => $page,
        ];

        Log::debug($document);

        return view('document', $values);

    }//end showDocument()


}//end class
