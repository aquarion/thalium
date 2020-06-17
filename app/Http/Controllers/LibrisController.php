<?php

namespace App\Http\Controllers;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Http\Request;


use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class LibrisController extends Controller
{

    public function home(LibrisInterface $libris)
    {

        return view('sysindex', ['systems' => $libris->systems()]);

    }

    public function everything(LibrisInterface $libris)
    {

        dump($libris->Everything());

    }

    public function allBySystem(LibrisInterface $libris, Request $request, $system)
    {
        $page = $request->query('page', 1);
        $perpage = 30;

        $documents = $libris->AllBySystem($system, $page, $perpage);
        $total = $documents['hits']['total']['value'];

        $docresult = [];
        foreach($documents['hits']['hits'] as $doc){
            $docresult[] = [
                'name' => $doc['_source']['title'],
                'path' => $doc['_source']['path'],
                'download' => Storage::disk('libris')->url($doc['_source']['path']),
            ];
        }

        $paginate = new LengthAwarePaginator(
            array(),
            $total,
            $perpage,
            $page,
        );

        $paginate->setPath(url()->current());

        return view('system', [
            'system' => $system,
            'docs'   => $docresult,
            'page'   => $page,
            'pages'  => ceil($total/$perpage),
            'pagination' => $paginate,
        ]);
        // dd($libris->showAll());

    }

    public function search(LibrisInterface $libris, Request $request){
        $perpage = 20;

        $query = $request->query('q');
        $system = $request->query('s', false);
        $document = $request->query('d', false);
        $page = $request->query('page', 1);

        $result = $libris->pageSearch($query,$system, $document, $page, $perpage);

        $total = $result['hits']['total']['value'];

        $appends = ['q' => $query ];

        if($system){
            $appends['s'] = $system;
        }
        if($document){
            $appends['d'] = $documents;
        }

        $paginate = new LengthAwarePaginator(
            array(),
            $total,
            $perpage,
            $page,
            [
                'path' => url()->current(),
                'appends' => $appends
            ]
        );

        $paginate->appends($appends);


        $values = [
            'systems' => $result['aggregations']['systems']['buckets'],
            'top_docs' => $result['aggregations']['parents']['buckets'],
            'hits' => $result['hits']['hits'],
            'query' => $query,
            'system' => $system,
            'document' => $document,
            'pagination' => $paginate
        ];

        foreach($values['hits'] as &$doc){
            $doc['_source']['download'] = Storage::disk('libris')->url($doc['_source']['path']);
        }

        return view('search', $values);

    }
}
