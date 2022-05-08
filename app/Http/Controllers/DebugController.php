<?php

namespace App\Http\Controllers;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Http\Request;

class DebugController extends Controller
{


    public function thumbnail(LibrisInterface $libris, Request $request)
    {
        $id = $request->query('id');
        dump($id);
        // return view('sysindex', ['systems' => $libris->systems()]);
        $doc = $libris->fetchDocument($id);
        dump($doc);
        echo "<h2>Stuff</h2>\n";

        echo '[<a href="'.Storage::disk('libris')->url($doc['_source']['path'])."\">DL</a>]\n";
        echo "<h2>Images</h2>\n";

        echo '<img src="'.$libris->getThumbnail($doc).'" style="border: 1px solid red;" title="Current Thumbnail">'."\n";

        echo '<img src="'.$libris->thumbnailDataURI($doc['_source']['path']).'" style="border: 1px solid blue;" title="Regenerated Thumbnail">';

    }//end thumbnail()


}//end class
