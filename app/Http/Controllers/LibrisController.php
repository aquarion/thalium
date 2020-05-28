<?php

namespace App\Http\Controllers;

use Elasticsearch;
use Illuminate\Support\Facades\Storage;

use App\Libris\LibrisInterface;


use Illuminate\Http\Request;

class LibrisController extends Controller
{
    
    public function reindex(LibrisInterface $libris)
    {
    	var_dump( $libris->reindex() ) ;

    }
}
