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
            return $parser->index();
        }

    }//end addDocument()

    public function updateDocument($id, $body)
    {
        $params = [
            'index' =>  $this->elasticSearchIndex,
            'id'    => $id,
            'body'  => $body
        ];
        $response = Elasticsearch::update($params);
    }


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


    public function indexFile($filename)
    {
        if (Storage::disk('libris')->missing($filename)) {
            Log::error("[indexFile] No Such File $filename");
            return false;
        }

        $mimeType = Storage::disk('libris')->mimeType($filename);

        Log::info("[indexFile] New File Scan Job: File: $filename");
        ScanPDF::dispatch($filename);
        return true;

    }//end indexFile()


    public function indexDirectory($filename)
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

    }//end indexDirectory()


    public function reindex()
    {
        Log::info("Kicking off a reindex for ".Storage::disk('libris')->path("."));

        $this->updatePipeline();
        $this->updateIndex();

        $dirCount  = 0;
        $fileCount = 0;

        $systems = Storage::disk('libris')->directories('.');
        $files   = Storage::disk('libris')->files('.');

        foreach ($systems as $system) {
            ScanDirectory::dispatch($system) && $dirCount++;
        }

        foreach ($files as $filename) {
            $this->indexFile($filename) && $fileCount++;
            ;
        }

        Log::info("Scanning $dirCount directories & $fileCount files");
        return true;

    }//end reindex()


    public function purgeDeletedFiles()
    {
        $size   = 100;
        $page   = 1;
        $cursor = 0;

        $results = $this->showAll(1, 0);
        $pages   = ceil(($results['hits']['total']['value'] / $size));

        $deletionList = [];

        for ($page = 1; $page <= $pages; $page++) {
            $docs = $this->showAll($page, $size);
            foreach ($docs['hits']['hits'] as $index => $doc) {
                $filename = $doc['_source']['path'];
                if (Storage::disk('libris')->missing($filename)) {
                    $deletionList[] = $doc['_id'];
                }
            }
        }

        foreach ($deletionList as $docId) {
            $this->deleteDocument($docId);
        }

        return $deletionList;
    }//end purgeDeletedFiles()

    public function updateTags()
    {
        $size   = 100;
        $page   = 1;
        $cursor = 0;

        $results = $this->showAll(1, 0);
        $pages   = ceil(($results['hits']['total']['value'] / $size));

        for ($page = 1; $page <= $pages; $page++) {
            dump($page.' of '.$pages);
            $docs = $this->showAll($page, $size);
            foreach ($docs['hits']['hits'] as $index => $doc) {
                // dd($doc);
                $parser = $this->getParser($doc['_source']['path']);

                if ($parser->tags == $doc['_source']['tags']) {
                    // dump('From '. implode(',', $doc['_source']['tags']).' ('.count($parser->tags).') to '.implode(',', $parser->tags).' ('.count($parser->tags).') - No change!');
                } else {
                    dump('     '.$parser->filename.' - from '. implode(',', $doc['_source']['tags']).' ('.count($doc['_source']['tags']).') to '.implode(',', $parser->tags).' ('.count($parser->tags).') - Update!');
                    $body = [
                        'doc' => ['tags'          => $parser->tags],
                    ];
                    $result = $this->updateDocument($doc['_id'], $body);
                }
            }
        }
    }//end updateTags()


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


    public function Everything($page=0)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'query' => [
                    "match_all" => [ "boost" => 1.0 ],
                ],
            ],
        ];
        // res = es.search(index='indexname', doc_type='typename', body=doc,scroll='1m')
        return Elasticsearch::search($params);

    }//end Everything()


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
                            'match' => [ 'doc_type' => 'document' ],
                        ],
                    ],
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
        }

        return Elasticsearch::search($params);

    }//end AllBySystem()


    public function pageSearch($terms, $system, $document, $page=1, $size=60)
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
            ],
        ];

        Log::debug($params);

        return Elasticsearch::search($params);

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
