<?php

namespace App\Http\Controllers;

use App\Libris\LibrisInterface;

class LibrisController extends Controller
{

    public function home(LibrisInterface $libris)
    {

        ini_set('memory_limit', '5512M');
        $all = $libris->showAll();

        // var_dump($all);
        print_r($all['hits']['hits'][0]);

    }

    public function reindex(LibrisInterface $libris)
    {

        // var_dump($libris->deleteIndex());

        var_dump($libris->reindex());

    }

    public function delete(LibrisInterface $libris)
    {

        var_dump($libris->deleteIndex());


    }
}
