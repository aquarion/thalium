<?php

namespace App\Http\Controllers;

use App\Libris\LibrisInterface;

class LibrisController extends Controller
{

    public function home(LibrisInterface $libris)
    {
        $all = $libris->showAll();

        var_dump($all);
        var_dump($all['hits']['hits'][0]['_source']);

    }

    public function reindex(LibrisInterface $libris)
    {
        var_dump($libris->reindex());

    }
}
