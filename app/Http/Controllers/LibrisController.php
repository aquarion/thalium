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
            $urlpath = route("downloadDoc", ['docid' => $doc['_source']['path']] );

            $docresult[] = [
                'name' => $doc['_source']['title'],
                'path' => $doc['_source']['path'],
                'download' => Storage::disk('libris')->url($doc['_source']['path']),
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

    public function downloadDoc(LibrisInterface $libris, $docid)
    {
        return response()->file(Storage::disk('libris')->get($docid));

    }
}
