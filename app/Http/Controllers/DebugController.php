<?php

namespace App\Http\Controllers;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function thumbnail(LibrisInterface $libris, Request $request)
    {
        $id = $request->query('id');
        // dump($id);
        // return view('sysindex', ['systems' => $libris->systems()]);
        $doc = $libris->fetchDocument($id);

        Log::Info($doc);
        $output = "<h2>Stuff</h2>\n";

        $output .= '[<a href="'.Storage::disk('libris')->url($doc['_source']['path'])."\">DL</a>]\n";
        $output .= "<h2>Images</h2>\n";

        $output .= '<img src="'.$libris->getDocThumbnail($doc).'" style="border: 1px solid red;" title="Current Thumbnail">'."\n";

        $output .= '<img src="'.$libris->thumbnailDataURI($doc['_source']['path']).'" style="border: 1px solid blue;" title="Regenerated Thumbnail">'."\n";

        $output .= '<img src="data:image/png;base64,'.base64_encode(genericThumbnail($doc['_source']['title'])).'" style="border: 1px solid green;" title="Regenerated Thumbnail">';

        return view(
            "debug",
            [
                'content' => $output,
                'doc' => $doc
            ]
        );
    }//end thumbnail()

    public function system(LibrisInterface $libris, Request $request)
    {
        $system = $request->query('system');
        
        $output = "<h2>System</h2>\n";


        $output .= '<img src="'.$libris->getSystemThumbnail($system).'" style="border: 1px solid red;" title="Current Thumbnail">'."\n";
        $output .= '<img src="'.$libris->dataURI(genericThumbnail($system)).'" style="border: 1px solid red;" title="Generic Thumbnail">'."\n";

        // $output .= '<img src="'.$libris->thumbnailDataURI($doc['_source']['path']).'" style="border: 1px solid blue;" title="Regenerated Thumbnail">'."\n";

        // $output .= '<img src="data:image/png;base64,'.base64_encode(genericThumbnail($doc['_source']['title'])).'" style="border: 1px solid green;" title="Regenerated Thumbnail">';

        return view(
            "debug",
            [
                'content' => $output,
                'doc' => false
            ]
        );
    }//end thumbnail()
}//end class
