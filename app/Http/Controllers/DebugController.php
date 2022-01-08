<?php

namespace App\Http\Controllers;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Http\Request;


class DebugController extends Controller
{

    public function thumbnail(LibrisInterface $libris, Request $request){

            $id = $request->query('id');
            dump($id);
            // return view('sysindex', ['systems' => $libris->systems()]);
            $doc = $libris->fetchDocument($id);
            dump($doc);
            print "<h2>Stuff</h2>\n";

            print '[<a href="'.Storage::disk('libris')->url($doc['_source']['path'])."\">DL</a>]\n";
            print "<h2>Images</h2>\n";

            print '<img src="'.$doc['_source']['thumbnail'].'" style="border: 1px solid red;">'."\n";

            print '<img src="'.$libris->genThumbnail($doc['_source']['path']).'" style="border: 1px solid blue;">';
        }


}
