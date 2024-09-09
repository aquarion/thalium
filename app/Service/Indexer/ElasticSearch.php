<?php

namespace App\Service\Indexer;

use App\Interfaces\IndexerInterface;
use \Elasticsearch as ElasticSearchClient;
use Illuminate\Support\Facades\Log;
use App\Service\ParserService;

class ElasticSearch implements IndexerInterface
{
    public $elasticSearchIndex = "libris";
    private $pointInTime = false;
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        
    }


    public function indexDocument(ParserService $parser, $thumbnailURL){
        
        $params = [
            'index'   => $this->elasticSearchIndex,
            'id'      => $parser->filename,
            'routing' => $parser->filename,
            'body'    => [
                "doc_type"      => "document",
                'path'          => $parser->filename,
                'system'        => $parser->system,
                'tags'          => $parser->tags,
                'thumbnail'     => $thumbnailURL,
                'title'         => $parser->title,
                'last_modified' => $parser->lastModified,
                "page_relation" => ["name" => "document"],
            ],
        ];

        Log::debug("[AddDoc] {$parser->filename} Indexing");
        return ElasticSearchClient::index($params);

    }


    public function indexPages(ParserService $parser)
    {
        Log::info("[AddDoc] {$parser->filename} Indexing Pages");

        $pageCount = count($parser->pages);

        foreach ($parser->pages as $pageIndex => $text) {
            $pageNo = ($pageIndex + 1);
            set_time_limit(30);

            $params = [
                'index'    => $this->elasticSearchIndex,
                'id'       => $parser->filename."/".$pageNo,
                'routing'  => $parser->filename,
                'pipeline' => 'attachment_pipeline',
                'body'     => [
                    "doc_type"      => "page",
                    'data'          => base64_encode($text),
                    'pageNo'        => $pageNo,
                    'path'          => $parser->filename,
                    'system'        => $parser->system,
                    'tags'          => $parser->tags,
                    'title'         => $parser->title,
                    "page_relation" => [
                        "name"   => "page",
                        "parent" => $parser->filename,
                    ],
                ],
            ];
            ElasticSearchClient::index($params);
        }//end foreach

        Log::debug("[AddDoc] {$parser->filename} Added $pageCount Pages");

    }//end indexPages()

    public function updateDocument($id, $body)
    {
        $params   = [
            'index' => $this->elasticSearchIndex,
            'id'    => $id,
            'body'  => $body,
        ];
        $response = ElasticSearchClient::update($params);

    }//end updateDocument()


    public function deleteDocument($docId)
    {
        // Now delete the document
        $params = [
            'index' => $this->elasticSearchIndex,
            'id'    => $docId,
        ];

        $this->deleteDocumentPages($docId);
        ElasticSearchClient::delete($params);

    }//end deleteDocument()


    public function deletePage($docId)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'id'    => $docId,
        ];

        ElasticSearchClient::delete($params);

    }//end deletePage()


    public function deleteDocumentPages($docId = false)
    {
        $size        = 100;
        $searchAfter = false;
        $this->openPointInTime();
        while (true) {
            $pages = $this->fetchAllPages($docId, $size, $searchAfter);
            if (count($pages['hits']['hits']) == 0) {
                break;
            }

            foreach ($pages['hits']['hits'] as $index => $page) {
                $pageId   = $page['_id'];
                $this->deletePage($pageId);
                $searchAfter = $page['sort'];
            }
        }

    }//end deleteDocumentPages()


    public function fetchDocument($id)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'id'    => $id,
        ];

        try {
            // Get doc at /my_index/_doc/my_id
            return ElasticSearchClient::get($params);
        } catch (ElasticSearchClient\Common\Exceptions\Missing404Exception $e) {
            return false;
        }

    }//end fetchDocument()


    public function fetchAllDocuments($tag = false, $size = 100, $searchAfter = false)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'size'  => $size,
                'query' => [
                    'match' => ['doc_type' => 'document'],
                ],
                "sort"  => [
                    [
                        "path" => ["order" => "asc"],
                    ],
                ],
            ],
        ];

        if ($searchAfter) {
            $params['body']['search_after'] = $searchAfter;
        }

        if ($this->pointInTime) {
            $params['body']['pit'] = [
                'id'         => $this->pointInTime,
                'keep_alive' => '1m',
            ];
            unset($params['index']);
        }

        return ElasticSearchClient::search($params);

    }//end fetchAllDocuments()

    public function countAllDocuments(){
        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'query' => [
                    'match' => ['doc_type' => 'document'],
                ],
            ],
        ];

        return ElasticSearchClient::count($params)['count'];
    }

    public function countAllPages($docId = false)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['match' => [ 'doc_type' => 'page' ]],
                        ],

                    ],
                ],
            ],
        ];

        if ($docId) {
            $params['body']['query']['bool']['filter'] = [
                'match' => [ 'path' => $docId ],
            ];
        }

        return ElasticSearchClient::count($params)['count'];

    }//end countAllPages()


    public function fetchAllPages($docId = false, $size = 100, $searchAfter = false)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'size'  => $size,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['match' => [ 'doc_type' => 'page' ]],
                        ],

                    ],
                ],
                "sort"  => [
                    [
                        "path" => ["order" => "asc"],
                    ],
                ],
                'aggs'  => [
                    'uniq_systems' => [
                        'composite' => [
                            'size'    => 100,
                            'sources' => [
                                'systems' => ['terms' => ['field' => 'document'] ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($docId) {
            $params['body']['query']['bool']['filter'] = [
                'match' => [ 'path' => $docId ],
            ];
        }

        if ($this->pointInTime) {
            $params['body']['pit'] = [
                'id'         => $this->pointInTime,
                'keep_alive' => '1m',
            ];
            unset($params['index']);
        }

        if ($searchAfter) {
            $params['body']['search_after'] = $searchAfter;
        }

        // dd($params);

        return ElasticSearchClient::search($params);

    }//end fetchAllPages()

    public function setup(){
        $this->updateIndex();
    }

    public function updateIndex()
    {
        Log::info("Update/Create Index");

        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                '_source'    => ['enabled' => true],

                'properties' => [
                    'system'        => ['type' => 'keyword'],
                    'tags'          => ['type' => 'keyword'],
                    'filename'      => ['type' => 'keyword'],
                    'path'          => ['type' => 'keyword'],
                    'title'         => ['type' => 'keyword'],
                    'content'       => ['type' => 'keyword'],
                    'doc_type'      => ['type' => 'keyword'],
                    'pageNo'        => ['type' => 'integer'],
                    'thumbnail'     => ['type' => 'text'],

                    'page_relation' => [
                        "type"      => "join",
                        "relations" => [ "document" => "page" ],
                    ],
                ],
            ],
        ];

        // If it's missing, create it.
        try {
            return ElasticSearchClient::indices()->putMapping($params);
        } catch (ElasticSearchClient\Common\Exceptions\Missing404Exception $e) {
            $indexparams = [
                'index' => $this->elasticSearchIndex,
            ];

            // Create the index
            ElasticSearchClient::indices()->create($indexparams);

            return ElasticSearchClient::indices()->putMapping($params);
        }

        $this->createPipeline();

    }//end updateIndex()


    public function deleteIndex()
    {
        Log::warning("Deleting Index");
        return ElasticSearchClient::indices()->delete(['index' => $this->elasticSearchIndex]);

    }//end deleteIndex()


    private function updatePipeline()
    {
        Log::info("Update Pipeline");
        $params = [
            'id'   => 'attachment_pipeline',
            'body' => [
                'description' => 'my attachment ingest processor',
                'processors'  => [
                    [
                        'attachment' => ['field' => 'data'],
                    ],
                    [
                        'remove' => ['field' => 'data'],
                    ],
                ],
            ],
        ];

        $result = ElasticSearchClient::ingest()->putPipeline($params);

    }//end updatePipeline()


    private function createPipeline()
    {

        // If it's missing, create it.
        try {
            Log::info("Create Pipeline");
            $params = ['id' => 'attachment_pipeline'];

            $hasPipeline = ElasticSearchClient::ingest()->getPipeline($params);

            return;
        } catch (ElasticSearchClient\Common\Exceptions\Missing404Exception $e) {
            $this->updatePipeline();
        }

    }//end createPipeline()




    public function listSystems()
    {
        $filter = [
            'only_documents' => [
                'filter' => [
                    'term' => ['doc_type' => 'document'],
                ],
            ],
        ];
        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                "size" => 0,
                'aggs' => [
                    'uniq_systems' => [
                        'composite' => [
                            'size'    => 100,
                            'sources' => [
                                'systems' => ['terms' => ['field' => 'system'] ],
                            ],
                        ],
                        'aggs'      => $filter,
                    ],
                ],
            ],
        ];

        $buckets  = [];
        $continue = true;

        while ($continue == true) {
            $results = ElasticSearchClient::search($params);
            $buckets = array_merge($buckets, $results['aggregations']['uniq_systems']['buckets']);
            if (isset($results['aggregations']['uniq_systems']['after_key'])) {
                $params['body']['aggs']['uniq_systems']['composite']['after']
                    = $results['aggregations']['uniq_systems']['after_key'];
            } else {
                $continue = false;
            }
        }

        $return = [];
        foreach ($buckets as $bucket) {
            $system = $bucket['key']['systems'];
            $count  = $bucket['only_documents']['doc_count'];

            if ($count) {
                
                $return[]  = [
                    'system'    => $system,
                    'count'     => $count,
                    'thumbnail' => false,
                ];
            }
        }

        Log::info($return);
        return $return;

    }//end systems()


    public function listDocuments($system, $page = 1, $size = 60, $tag = false)
    {
        $from = (($page - 1) * $size);

        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'size'  => $size,
                'from'  => $from,
                'sort'  => [
                    [
                        "tags"  => [
                            "missing" => "_first",
                            "order"   => "asc",
                        ],
                        "path"  => [ "order" => "asc"],
                        "title" => [ "order" => "asc"],
                    ],
                ],
                'query' => [
                    'bool' => [
                        'must'   => [
                            0 => [
                                'match' => [
                                    'system' => [
                                        'query'    => $system,
                                        "operator" => "and",
                                    ],
                                ],
                            ],
                        ],
                        'filter' => [
                            ['match' => [ 'doc_type' => 'document' ]],
                        ],

                    ],
                ],
            ],
        ];

        $params['body']["aggs"] = [
            "tags" => [
                "terms" => [
                    "field" => "tags",
                    "size"  => 30,
                ],
            ],
        ];

        if ($tag) {
            $params['body']['query']['bool']['must'][] = [
                'match' => [
                    'tags' => [
                        'query'    => $tag,
                        "operator" => "and",
                    ],
                ],
            ];
        } elseif ($tag === 0) {
            $params['body']['query']['bool']['filter'][] = [
                'script' => ["script" => "doc['tags'].length == 0"],
            ];
        }

        $result = ElasticSearchClient::search($params);

        return($result);

    }//end docsBySystem()

    public function tagsForSystem($system){

        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'size'  => 0,
                'query' => [
                    'bool' => [
                        'must'   => [
                            0 => [
                                'match' => [
                                    'system' => [
                                        'query'    => $system,
                                        "operator" => "and",
                                    ],
                                ],
                            ],
                        ],
                        'filter' => [
                            ['match' => [ 'doc_type' => 'document' ]],
                        ],

                    ],
                ],
            ],
        ];

        $params['body']["aggs"] = [
            "tags" => [
                "terms" => [
                    "field" => "tags",
                    "size"  => 30,
                ],
            ],
        ];

        $result = ElasticSearchClient::search($params);

        return $result['aggregations']['tags']['buckets'];

    }


    public function searchPages($terms, $system, $document, $tag, $page = 1, $size = 60)
    {

        // $system = "Goblin Quest";
        $from = (($page - 1) * $size);

        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'size'      => $size,
                'from'      => $from,
                'query'     => [
                    'bool' => [
                        'must' => [
                            'match_phrase' => [
                                'attachment.content' => ['query' => $terms],
                            ],
                        ],
                    ],
                ],
                "highlight" => [
                    "fields" => [
                        "attachment.content" => new \stdClass(),
                    ],
                ],
            ],
        ];

        if ($system) {
            $params['body']['query']['bool']['filter'][] = [
                'match' => [ 'system' => $system ],
            ];
        }

        if ($document) {
            $params['body']['query']['bool']['filter'][] = [
                'match' => [ 'path' => $document ],
            ];
        }

        if ($tag) {
            $params['body']['query']['bool']['filter'][] = [
                'match' => [ 'tags' => $tag ],
            ];
        }

        $params['body']["aggs"] = [
            "parents" => [
                "terms" => [
                    "field" => "page_relation#document",
                    "size"  => 30,
                ],
            ],
            "systems" => [
                "terms" => [
                    "field" => "system",
                    "size"  => 30,
                ],
                "aggs"  => [
                    "tags" => [
                        "terms" => [
                            "field" => "tags",
                            "size"  => 30,
                        ],
                    ],
                ],
            ],
        ];

        Log::debug($params);
        $result = ElasticSearchClient::search($params);
        Log::debug($result);

        return $result;

    }//end searchPages()


    public function openPointInTime()
    {
        $params   = [
            'index'      => $this->elasticSearchIndex,
            'keep_alive' => '1m',
        ];
        $response = ElasticSearchClient::openPointInTime($params);

        $this->pointInTime = $response['id'];

    }//end openPointInTime()


    private function setPointInTime($id)
    {
        $this->pointInTime = $id;

    }//end setPointInTime()


    private function closePointInTime()
    {
        $params   = [
            // 'index' => $this->elasticSearchIndex,
            'id'    => $this->pointInTime,
        ];
        // $response = ElasticSearchClient::closePointInTime($params);

    }//end closePointInTime()

    public function getDocThumbnail($doc){
        if (isset($doc['_source']['thumbnail']) && $doc['_source']['thumbnail']) {
            return $doc['_source']['thumbnail'];
        }
        return false;
    }

    public function getLocalFilename($doc){
        return $doc['_source']['path'];
    }

    public function updateSingleField($document, $field, $value){

        $params = [
            'index' => $document['_index'],
            'id'    => $document['_id'],
            'body'  => [
                'doc' => [$field => $value],
            ],
        ];

        // Update doc at /my_index/_doc/my_id
        ElasticSearchClient::update($params);

    }

    public function __destruct()
    {
        if ($this->pointInTime) {
            $this->closePointInTime();
        }

    }//end __destruct()

    public function sweepPages($bar){

        $deleted  = 0;
        $filename = false;

        $size        = 100;
        $searchAfter = false;
        $this->openPointInTime();

        while (true) {
            $pages = $this->fetchAllPages($docId, $size, $searchAfter);
            if (count($pages['hits']['hits']) == 0) {
                break;
            }

            foreach ($pages['hits']['hits'] as $index => $page) {
                $pageId = $page['_id'];
                // if($filename != $page['_source']['path']) {
                //     $bar->setMessage("[" . $deleted . "] " . $filename);
                // }
                $filename = $page['_source']['path'];
                if ($this->isMissing($filename)) {
                    $this->libris->deletePage($pageId);
                    $bar->setMessage(" Deleted ".$pageId);
                    $deleted++;
                }

                $bar->advance();

                $searchAfter = $page['sort'];
            }
        }//end while

    }
}
