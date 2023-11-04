<?php

namespace App\Libris;

use Elasticsearch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Jobs\ScanDirectory;
use App\Jobs\ScanFile;

use App\Service\PDFBoxService;
use App\Service\ParserService;
use App\Service\ParseTextService;
use App\Exceptions;

class LibrisService implements LibrisInterface
{
    public $elasticSearchIndex = "libris";

    private $pointInTime = false;


    public function addDocument($file, $log = false)
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
            throw new Exceptions\LibrisFileNotSupported("No parser for $file");
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

        Log::debug("[AddDoc] Name: {$parser->title}, System: {$parser->system}, Tags: " . implode(",", $parser->tags));

        $image        = $this->generateDocThumbnail($parser->filename);
        $thumbnailURL = $this->saveDocThumbnail($image, $parser->filename);

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
                'id'       => $parser->filename . "/" . $pageNo,
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
        } elseif ($mimeTypeArray && $mimeTypeArray[0] == "text") {
            Log::debug("[Parser] Parsing $mimeType $file as text ...");
            $parser = new ParseTextService($file, $this->elasticSearchIndex);
        } else {
            Log::debug("[Parser] Ignoring $mimeType $file, cannot parse ...");
            return false;
        }

        return $parser;

    }//end getParser()


    public function deleteDocument($docId)
    {
        // Now delete the document
        $params = [
            'index' => $this->elasticSearchIndex,
            'id'    => $docId,
        ];

        Elasticsearch::delete($params);
        $this->deleteDocumentPages($docId);

    }//end deleteDocument()


    public function deletePage($docId)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'id'    => $docId,
        ];

        Elasticsearch::delete($params);

    }//end deletePage()


    private function deleteDocumentPages($docId = false)
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
                $filename = $page['_source']['path'];
                if (Storage::disk('libris')->missing($filename)) {
                    $this->deletePage($pageId);
                }

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

    public function scanFile($filename)
    {
        Log::debug("[Scanfile] Got lock for " . $filename . " = " . md5($filename));
        try {
            $returnValue = $this->addDocument($filename);
            Log::info("[Scanfile] Finished " . $filename);
        } catch (Exceptions\LibrisFileNotSupported $e) {
            Log::warning("[Scanfile] Unsupported File Type " . $filename);
            return 0;
        } catch (Exceptions\LibrisTooLarge $e) {
            Log::warning("[Scanfile] File too big " . $filename);
            return 0;
        }

        if (!$returnValue) {
            Log::error("[Scanfile] Bad return value ($returnValue) from PDFBox");
            throw new Exceptions\LibrisParserError("Bad return value ($returnValue) from PDFBox");
        }

        return $returnValue;
    }

    public function dispatchIndexFile($filename)
    {
        if (Storage::disk('libris')->missing($filename)) {
            Log::error("[dispatchIndexFile] No Such File $filename");
            return false;
        }

        $mimeType = Storage::disk('libris')->mimeType($filename);

        Log::info("[dispatchIndexFile] New File Scan Job: File: $filename");
        ScanFile::dispatch($filename);
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

        Log::info("[indexDir] New Dir Scan Job: Sys: $system, Tags: " . implode(',', $tags) . ", File: $filename");
        ScanDirectory::dispatch($filename);
        return true;

    }//end dispatchIndexDir()


    public function countAllDocuments($tag = false)
    {
        $params = [
            'index' => $this->elasticSearchIndex,
            'body'  => [
                'query' => [
                    'match' => ['doc_type' => 'document'],
                ],
            ],
        ];

        return Elasticsearch::count($params)['count'];

    }//end countAllDocuments()


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

        return Elasticsearch::search($params);

    }//end fetchAllDocuments()


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

        return Elasticsearch::count($params)['count'];

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

        return Elasticsearch::search($params);

    }//end fetchAllPages()


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
                $thumbnail = $this->getSystemThumbnail($system);
                $return[]  = [
                    'system'    => $system,
                    'count'     => $count,
                    'thumbnail' => $thumbnail,
                ];
            }
        }

        Log::info($return);
        return $return;

    }//end systems()


    public function docsBySystem($system, $page = 1, $size = 60, $tag = false)
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

        $result = Elasticsearch::search($params);

        return($result);

    }//end docsBySystem()


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


    public function tagsForSystem($system)
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

        $tagList = $result['aggregations']['tags']['buckets'];

        usort($tagList, [LibrisService::class, "tagSort"]);

        return($tagList);

    }//end tagsForSystem()


    public function openPointInTime()
    {
        $params   = [
            'index'      => $this->elasticSearchIndex,
            'keep_alive' => '1m',
        ];
        $response = Elasticsearch::openPointInTime($params);

        $this->pointInTime = $response['id'];

    }//end openPointInTime()


    public function setPointInTime($id)
    {
        $this->pointInTime = $id;

    }//end setPointInTime()


    public function closePointInTime()
    {
        $params   = [
            'index' => $this->elasticSearchIndex,
            'id'    => $this->pointInTime,
        ];
        $response = Elasticsearch::closePointInTime($params);

    }//end closePointInTime()


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
        $result = Elasticsearch::search($params);
        Log::debug($result);

        return $result;

    }//end searchPages()


    public function getDocThumbnail($doc, $regen = false)
    {
        $thumbnailSet = isset($doc['_source']['thumbnail']) && $doc['_source']['thumbnail'];

        if ($regen == "generic" && $thumbnailSet) {
            $mimeType = Storage::disk('libris')->mimeType($doc['_source']['path']);
            if ($mimeType !== "application/pdf") {
                return $this->updateDocThumbnail($doc);
            }
        }

        if ($regen == "all") {
            return $this->updateDocThumbnail($doc);
        } elseif ($thumbnailSet) {
            return $doc['_source']['thumbnail'];
        } else {
            return $this->updateDocThumbnail($doc);
        }

    }//end getDocThumbnail()


    public function updateDocThumbnail($doc)
    {
        $file = $doc['_source']['path'];
        Log::debug("[updateDocThumbnail] Update Thumbnail - " . $file);

        // if (Storage::disk('thumbnails')->exists($thumbnailFileName)) {
        //     Log::info("[updateDocThumbnail] Already exists $thumbnailFileName");
        // } else {
        $image = $this->generateDocThumbnail($file);

        $thumbnailURL = $this->saveDocThumbnail($image, $file);

        if ($thumbnailURL == $doc['_source']['thumbnail']) {
            Log::info("[updateDocThumbnail] Filename already set");
            return $thumbnailURL;
        }

        $params = [
            'index' => $doc['_index'],
            'id'    => $doc['_id'],
            'body'  => [
                'doc' => ['thumbnail' => $thumbnailURL],
            ],
        ];

        // Update doc at /my_index/_doc/my_id
        $response = Elasticsearch::update($params);
        Log::info("[updateDocThumbnail] Saved");

        return $thumbnailURL;

    }//end updateDocThumbnail()


    protected function saveDocThumbnail(\Imagick $image, $file)
    {
        if (Storage::disk('libris')->missing($file)) {
            Log::error("[saveDocThumbnail] No Such File $file");
            throw new Exception("[saveDocThumbnail] $file not found");
            return false;
        }

        $thumbnailFileName = md5($file) . ".png";
        $thumbnailURL      = Storage::disk('thumbnails')->url($thumbnailFileName);
        Storage::disk('thumbnails')->put($thumbnailFileName, $image);
        Log::debug("[saveDocThumbnail] saved to " . $thumbnailURL);
        return $thumbnailURL;

    }//end saveDocThumbnail()


    public function generateDocThumbnail($file)
    {
        Log::debug("[generateDocThumbnail] " . $file);

        $parser = $this->getParser($file);

        if ($parser) {
            return $parser->generateDocThumbnail($file);
        }

        return false;

    }//end generateDocThumbnail()


    public function thumbnailDataURI($file)
    {
        return $this->dataURI($this->generateDocThumbnail($file));

    }//end thumbnailDataURI()


    public function dataURI($image)
    {
        return 'data:image/png;base64,' . base64_encode($image);

    }//end dataURI()


    public function getSystemThumbnail($system)
    {
        $file = ".thalium/" . strtolower($system) . ".png";
        if (Storage::disk('libris')->missing($file)) {
            Log::error("[updateDocThumbnail] No Such thumbnail at " . Storage::disk('libris')->path($file));
            return $this->dataURI(genericThumbnail($system));
        } else {
            return Storage::disk('libris')->url($file);
        }

    }//end getSystemThumbnail()


    public function __destruct()
    {
        if ($this->pointInTime) {
            $this->closePointInTime();
        }

    }//end __destruct()


}//end class
