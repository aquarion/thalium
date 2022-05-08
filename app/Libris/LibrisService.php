<?php

namespace App\Libris;

use Elasticsearch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Jobs\ScanDirectory;
use App\Jobs\ScanPDF;

use App\Service\PDFBoxService;
use App\Service\ParserService;
use App\Service\ParseTextService;
use App\Exceptions;

class LibrisService implements LibrisInterface
{

    public $elasticSearchIndex = "libris";


    public function addDocument($file, $log=false)
    {
        $boom        = explode("/", $file);
        $title       = array_pop($boom);
        $this->title = $title;

        if (strpos($title, ".") === 0) {
            Log::error("[AddDoc] Ignoring Name: [{$file}], hidden file");
            return true;
        }

        $lastModified = Storage::disk('libris')->lastModified($file);

        if ($doc = $this->fetchDocument($file)) {
            if (isset($doc['_source']['last_modified'])) {
                if ($doc['_source']['last_modified'] == $lastModified) {
                    Log::info("[AddDoc] $file is already in the index with the same date");
                    return true;
                }
            }

            Log::info("[AddDoc] $file is already in the index, but this is different?");
        }

        $parser = $this->getParser($file);

        if ($parser) {
            return $this->indexDocument($parser);
        } else {
            Log::warning("[AddDoc] No parser for $file");
        }

    }//end addDocument()


    public function indexDocument(ParserService $parser)
    {
        try {
            $parser->pages = $parser->parsePages();
        } catch (Exceptions\LibrisParseFailed $e) {
            Log::error("[AddDoc] FAILED TO PARSE Name: [{$parser->filename}], System: [{$parser->system}]");
            return false;
        }

        Log::debug("[AddDoc] Name: {$parser->title}, System: {$parser->system}, Tags: ".implode(",", $parser->tags));

        $params = [
            'index'   => $this->elasticSearchIndex,
            'id'      => $parser->filename,
            'routing' => $parser->filename,
            'body'    => [
                "doc_type"      => "document",
                'path'          => $parser->filename,
                'system'        => $parser->system,
                'tags'          => $parser->tags,
                'thumbnail'     => $parser->generateThumbnail(),
                'title'         => $parser->title,
                'last_modified' => $parser->lastModified,
                "page_relation" => ["name" => "document"],
            ],
        ];

        Log::debug("[AddDoc] {$parser->filename} Indexing");
        Elasticsearch::index($params);

        if ($parser->pages) {
            $this->indexPages($parser);
        }

        return true;

    }//end indexDocument()


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
                    'thumbnail'     => $parser->generateThumbnail(),
                    "page_relation" => [
                        "name"   => "page",
                        "parent" => $parser->filename,
                    ],
                ],
            ];
            Elasticsearch::index($params);
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
        $response = Elasticsearch::update($params);

    }//end updateDocument()


    public function getParser($file)
    {
        $mimeType      = Storage::disk('libris')->mimeType($file);
        $mimeTypeArray = explode("/", $mimeType);

        $parser = false;

        if ($mimeType == "application/pdf") {
            Log::debug("[Parser] Parsing PDF $file ...");
            $parser = new PDFBoxService($file, $this->elasticSearchIndex);
        } else if ($mimeTypeArray && $mimeTypeArray[0] == "text") {
            Log::debug("[Parser] Parsing $mimeType $file as text ...");
            $parser = new ParseTextService($file, $this->elasticSearchIndex);
        } else {
            Log::debug("[Parser] Ignoring $mimeType $file, cannot parse ...");
            return false;
        }

        return $parser;

    }//end getParser()


    public function deleteDocument($id)
    {

        // Now delete the pages
        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'query' => [
                    'bool' => [
                        'must'   => [
                            0 => [
                                'match' => [
                                    'path' => [
                                        'query'    => $id,
                                        "operator" => "and",
                                    ],
                                ],
                            ],
                        ],
                        'filter' => [
                            'match' => [ 'doc_type' => 'page' ],
                        ],
                    ],
                ],
            ],
        ];

        Elasticsearch::deleteByQuery($params);

        // Now delete the document
        $params = [
            'index' => $this->elasticSearchIndex,
            'id'    => $id,
        ];
        // Get doc at /my_index/_doc/my_id
        Elasticsearch::delete($params);

    }//end deleteDocument()


    public function fetchDocument($id)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'id'    => $id,
        ];

        try {
            // Get doc at /my_index/_doc/my_id
            return Elasticsearch::get($params);
        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            return false;
        }

    }//end fetchDocument()


    public function deleteIndex()
    {
        Log::warning("Deleting Index");
        return Elasticsearch::indices()->delete(['index' => $this->elasticSearchIndex]);

    }//end deleteIndex()


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
            return Elasticsearch::indices()->putMapping($params);
        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            $indexparams = [
                'index' => $this->elasticSearchIndex,
            ];

            // Create the index
            Elasticsearch::indices()->create($indexparams);

            return Elasticsearch::indices()->putMapping($params);
        }

    }//end updateIndex()


    public function updatePipeline()
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

        $result = Elasticsearch::ingest()->putPipeline($params);

    }//end updatePipeline()


    public function createPipeline()
    {

        // If it's missing, create it.
        try {
            Log::info("Create Pipeline");
            $params = ['id' => 'attachment_pipeline'];

            $hasPipeline = Elasticsearch::ingest()->getPipeline($params);

            return;
        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            $this->updatePipeline();
        }

    }//end createPipeline()


    public function dispatchIndexFile($filename)
    {
        if (Storage::disk('libris')->missing($filename)) {
            Log::error("[dispatchIndexFile] No Such File $filename");
            return false;
        }

        $mimeType = Storage::disk('libris')->mimeType($filename);

        Log::info("[dispatchIndexFile] New File Scan Job: File: $filename");
        ScanPDF::dispatch($filename);
        return true;

    }//end dispatchIndexFile()


    public function dispatchIndexDir($filename)
    {
        if (Storage::disk('libris')->getMetadata($filename)['type'] !== 'dir') {
            Log::error("$filename is not a directory");
            return false;
        }

        $tags   = explode('/', $filename);
        $system = substr(array_unshift($tags), 0, -4);

        Log::info("[indexDir] New Dir Scan Job: Sys: $system, Tags: ".implode(',', $tags).", File: $filename");
        ScanDirectory::dispatch($filename);
        return true;

    }//end dispatchIndexDir()


    public function showAll($page=1, $size=60, $tag=false)
    {
        $from = (($page - 1) * $size);

        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'size'  => $size,
                'from'  => $from,
                'query' => [
                    'match' => ['doc_type' => 'document'],
                ],
            ],
        ];
        // res = es.search(index='indexname', doc_type='typename', body=doc,scroll='1m')
        return Elasticsearch::search($params);

    }//end showAll()


    public function systems()
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
        Log::info($params);

        while ($continue == true) {
            $results = Elasticsearch::search($params);
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
                $return[$system] = $count;
            }
        }

        Log::info($return);
        return $return;

    }//end systems()


    public function AllBySystem($system, $page=1, $size=60, $tag=false)
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
        } else {
            $params['body']['query']['bool']['filter'][] = [
                'script' => ["script" => "doc['tags'].length == 0"],
            ];
        }

        $result = Elasticsearch::search($params);
        Log::debug($result);

        return($result);

    }//end AllBySystem()


    public static function tagSort($a, $b)
    {
        if ($a['key'] == $b['key']) {
            return 0;
        }

        if ($a['key'] > $b['key']) {
            return 1;
        } else {
            return -1;
        }

    }//end tagSort()


    public function SystemTags($system)
    {
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

        $result = Elasticsearch::search($params);

        $tag_list = $result['aggregations']['tags']['buckets'];

        usort($tag_list, [LibrisService::class, "tagSort"]);

        return($tag_list);

    }//end SystemTags()


    public function pageSearch($terms, $system, $document, $tag, $page=1, $size=60)
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
        $result = Elasticsearch::search($params);
        Log::debug($result);

        return $result;

    }//end pageSearch()


    public function getThumbnail($doc, $regen=false)
    {
        if (isset($doc['_source']['thumbnail']) && $doc['_source']['thumbnail'] && !$regen) {
            // Log::debug("[getThumbnail] Found Thumbnail");
            return $doc['_source']['thumbnail'];
        }

        Log::debug($doc);

        $file = $doc['_source']['path'];

        if (Storage::disk('libris')->missing($file)) {
            Log::error("[getThumbnail] No Such File $file");
            return false;
        }

        $dataURI = $this->genThumbnail($file);

        $params = [
            'index' => $doc['_index'],
            'id'    => $doc['_id'],
            'body'  => [
                'doc' => ['thumbnail' => $dataURI],
            ],
        ];

        // Update doc at /my_index/_doc/my_id
        $response = Elasticsearch::update($params);

        return $dataURI;

    }//end getThumbnail()


    public function genThumbnail($file)
    {
        Log::debug("[genThumbnail] ".$file);

        $parser = $this->getParser($file);

        if ($parser) {
            return $parser->generateThumbnail($file);
        }

        return false;

    }//end genThumbnail()


}//end class
