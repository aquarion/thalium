<?php

namespace App\Libris;

use Elasticsearch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


use App\Jobs\ScanDirectory;
use App\Jobs\ScanPDF;

class LibrisService implements LibrisInterface
{
    public $index_name = "libris";

    public function addDocument($system, $tags, $filename)
    {
        ini_set('memory_limit', '512M');

        $size = Storage::disk('libris')->size($filename);

        $mimeType = Storage::disk('libris')->mimeType($filename);

        Log::info("$filename is a $mimeType of size ".number_format($size/1024)."Mb");

        if ($doc = $this->fetchDocument($filename)){
             Log::info("$filename is already in the index");
             return true;
        }

        if ($size > 1024 * 1024 * 512) {
            Log::error("$filename is a $mimeType of size ".number_format($size/1024)."Mb, too large to index");
            return true;
        }

        $params = [
            'index' => $this->index_name,
            'id' => $filename,
            'pipeline' => 'attachment_pipeline', // <----- here
            'body' => [
                'system' => $system,
                'tags' => $tags,
                'data' => base64_encode(Storage::disk('libris')->get($filename)),
            ],
        ];
        return Elasticsearch::index($params);
    }

    public function fetchDocument($id){
        $params = [
            'index' => $this->index_name,
           'id'    => $id
        ];

        try {
            // Get doc at /my_index/_doc/my_id
            return Elasticsearch::get($params);

        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            return false;
        }
    }

    public function deleteIndex(){
        return Elasticsearch::indices()->delete(array('index' => $this->index_name));
    }

    public function updatePipeline(){

            $params = [
                'id' => 'attachment_pipeline',
                'body' => [
                    'description' => 'my attachment ingest processor',
                    'processors' => [
                        [
                            'attachment' =>
                            [
                                'field' => 'data',
                            ],
                        ],
                        [
                            'remove' =>
                            [
                                'field' => 'data',
                            ],
                        ],
                    ],
                ],
            ];

            $result = Elasticsearch::ingest()->putPipeline($params);
    }

    public function createPipeline()
    {

        // If it's missing, create it.
        try {
            $params = ['id' => 'attachment_pipeline'];

            $hasPipeline = Elasticsearch::ingest()->getPipeline($params);

            return;

        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {

            $this->updatePipeline();
        }

    }

    public function reindex()
    {

        $this->updatePipeline();

        $systems = Storage::disk('libris')->directories('.');
        $files = Storage::disk('libris')->files('.');

        foreach ($systems as $system) {
            ScanDirectory::dispatch($system, array(), $system);
            // break;
        }

        foreach ($files as $filename) {
            $tags = explode('/', $filename);
            $file = array_pop($tags);

            // Execute scan job.
            Log::debug("[ScanDir] New File Scan Job: $filename");
            ScanPDF::dispatch(substr($filename, 0, -4), $tags, $filename);
            //$return[$system]['files'][$filename] = $this->addDocument($system, $tags, $filename);
        }

        return "Scanning ".count($systems)." directories";
    }

    public function showAll($page = 0)
    {

        $params = [
            'index' => $this->index_name,
        ];
        //res = es.search(index='indexname', doc_type='typename', body=doc,scroll='1m')
        return Elasticsearch::search($params);

    }
}
