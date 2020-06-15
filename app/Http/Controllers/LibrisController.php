<?php

namespace App\Http\Controllers;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;

class LibrisController extends Controller
{

    public function home(LibrisInterface $libris)
    {

        return view('sysindex', ['systems' => $libris->systems()]);

    }

    public function myUrlEncode($string) {
        $entities = array('+');
        $replacements = array('%20');
        return str_replace($entities, $replacements, $string);
    }

    public function allBySystem(LibrisInterface $libris, $system, $page=1)
    {
        $perpage = 10;

        $documents = $libris->AllBySystem($system);
        $total = $documents['hits']['total']['value'];

        $docresult = [];
        foreach($documents['hits']['hits'] as $doc){
            $path = $doc['_id'];
            $boom = explode("/", $path);
            $filename = array_pop($boom);

            $filename = preg_replace('!\.pdf$!', '', $filename);
            $filename = preg_replace('!-|_!', ' ', $filename);

            dd($doc);

            $urlpath = route("downloadDoc", ['docid' => rawurlencode($doc['_routing'])] );

            $docresult[] = [
                'name' => $filename,
                'path' => $doc['_id'],
                'download' => $urlpath,
            ];
        }

        return view('system', [
            'system' => $system,
            'docs'   => $docresult,
            'page'   => $page,
            'pages'  => ceil($total/$perpage)
        ]);
        // dd($libris->showAll());

    }

    public function reindex(LibrisInterface $libris)
    {

        // var_dump($libris->deleteIndex());

        var_dump($libris->reindex());

    }

    public function downloadDoc(LibrisInterface $libris, $docid)
    {

        Storage::disk('libris')->download($docid);

    }

    public function deleteIndex(LibrisInterface $libris)
    {

        var_dump($libris->deleteIndex());
    }

    public function updateIndex(LibrisInterface $libris)
    {
        var_dump($libris->updateIndex());
       var_dump($libris->updatePipeline());
    }
}
