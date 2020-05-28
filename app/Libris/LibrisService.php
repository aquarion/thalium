<?php

namespace App\Libris;

use Elasticsearch;
use Illuminate\Support\Facades\Storage;

class LibrisService implements LibrisInterface
{
    public $index_name = "libris";

    public function addDocument($system, $tags, $filename)
    {
        ini_set('memory_limit', '512M');

        $params = [
            'index' => $this->index_name,
            'type' => 'type',
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

    public function createPipeline()
    {

        try {
            $params = ['id' => 'attachment_pipeline'];

            $hasPipeline = Elasticsearch::ingest()->getPipeline($params);

            return;

        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {

            // If it's missing, create it.

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
                    ],
                ],
            ];

            $result = Elasticsearch::ingest()->putPipeline($params);
        }

    }

    public function reindex()
    {

        $this->createPipeline();

        $systems = Storage::disk('libris')->directories('.');

        $return = [];

        foreach ($systems as $system) {
            $return[$system] = [];
            $return[$system]['files'] = [];

            $files = Storage::disk('libris')->files($system);
            $subdirs = Storage::disk('libris')->directories($system);
            foreach ($files as $filename) {
                $tags = explode('/', $filename);
                $system = array_shift($tags);
                $return[$system]['files'][$filename] = $this->addDocument($system, $tags, $filename);
            }
            $return[$system]['files'] = $files;
            $return[$system]['subdirs'] = $subdirs;
            break;

        }
        return $return;
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
