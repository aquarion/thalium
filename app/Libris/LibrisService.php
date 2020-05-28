<?php
 
namespace App\Libris;

use ElasticSearch; 
use Illuminate\Support\Facades\Storage;
 
class LibrisService implements LibrisInterface
{
    public function reindex()
    {

    	$systems = Storage::disk('libris')->directories('.');

    	$return = [];

    	foreach ($systems as $system) {
    		$return[$system] = [];
    		$files   = Storage::disk('libris')->files($system);
    		$subdirs = Storage::disk('libris')->directories($system);
    		$return[$system]['files'] = $files;
    		$return[$system]['subdirs'] = $subdirs;
    	}
        return $return;
    }
}
