<?php

namespace App\Libris;

use Elasticsearch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Jobs\ScanDirectory;
use App\Jobs\ScanPDF;

use App\Service\PDFBoxService;
use App\Service\ParserService;
use App\Exceptions;


class LibrisService implements LibrisInterface
{
    public $index_name = "libris";

    public function addDocument($file, $log = false)
    {

        $last_modified = Storage::disk('libris')->lastModified($file);

        if ($doc = $this->fetchDocument($file)){
             if(isset($doc['_source']['last_modified'])){
                if($doc['_source']['last_modified'] == $last_modified){
                     Log::info("[AddDoc] $file is already in the index with the same date");
                     //return true;
                }
             }
             
             Log::info("[AddDoc] $file is already in the index, but this is different?");
        }

    
        $mimeType = Storage::disk('libris')->mimeType($file);
        $mimeTypeArray = explode("/", $mimeType);

        $parser = false;

        if($mimeType == "application/pdf"){
            Log::debug("[AddDoc] Parsing PDF $file ...");
            $parser = new PDFBoxService($file, $this->index_name);
        } elseif($mimeTypeArray[0] == "image") {
             Log::info("[AddDoc] Ignoring item of type $mimeType");
        } else {
            Log::debug("[AddDoc] Parsing $mimeType $file ...");
            $parser = new ParserService($file, $this->index_name);
        }

        if($parser){
            return $parser->index();
        }
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
        Log::warning("Deleting Index");
        return Elasticsearch::indices()->delete(array('index' => $this->index_name));
    }

    public function updateIndex(){
            Log::info("Update/Create Index");

            $params = [
                'index' => $this->index_name,
                'body' => [
                    '_source' => [
                        'enabled' => true
                    ],

                    'properties' => [
                        'system' => [
                            'type' => 'keyword'
                        ],
                        'tags' => [
                            'type' => 'keyword'
                        ],
                        'filename' => [
                            'type' => 'keyword'
                        ],
                        'path' => [
                            'type' => 'keyword'
                        ],
                        'title' => [
                            'type' => 'keyword'
                        ],
                        'content' => [
                            'type' => 'text'
                        ],
                        'doc_type' => [
                            'type' => 'keyword'
                        ],
                        'pageNo' => [
                            'type' => 'integer'
                        ],

                        'page_relation' => [
                            "type" => "join",
                            "relations" => [ "document" => "page" ]
                        ]
                    ]
                ]
            ];

            // If it's missing, create it.
            try {
                return Elasticsearch::indices()->putMapping($params);
            } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {

                $indexparams = [
                    'index' => $this->index_name,
                ];

                // Create the index
                Elasticsearch::indices()->create($indexparams);

                return Elasticsearch::indices()->putMapping($params);
            }
            
    }

    public function updatePipeline(){

            Log::info("Update Pipeline");
            $params = [
                'id' => 'attachment_pipeline',
                'body' => [
                    'description' => 'my attachment ingest processor',
                    'processors' => [
                        [
                            'attachment' =>
                            [
                                'field' => 'data'
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
            Log::info("Create Pipeline");
            $params = ['id' => 'attachment_pipeline'];

            $hasPipeline = Elasticsearch::ingest()->getPipeline($params);

            return;

        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {

            $this->updatePipeline();
        }

    }

    public function indexFile($filename){
        if(Storage::disk('libris')->missing($filename)){
             Log::error("[indexFile] No Such File $filename");
             return false;
        }

        $mimeType = Storage::disk('libris')->mimeType($filename);

        Log::info("[indexFile] New File Scan Job: File: $filename");
        ScanPDF::dispatch($filename);
        return true;
    }

    public function indexDirectory($filename){
        if(Storage::disk('libris')->getMetadata($filename)['type'] !== 'dir'){
             Log::error("$filename is not a directory");
             return false;
        }
        
        $tags = explode('/', $filename);
        $system = substr(array_unshift($tags), 0, -4);

        Log::info("[indexDir] New Dir Scan Job: Sys: $system, Tags: ".implode(',', $tags).", File: $filename");
        ScanDirectory::dispatch($filename);
        return true;
    }

    public function reindex()
    {
        Log::info("Kicking off a reindex");

        $this->updatePipeline();
        $this->updateIndex();

        $dirCount = 0;
        $fileCount = 0;

        $systems = Storage::disk('libris')->directories('.');
        $files = Storage::disk('libris')->files('.');

        foreach ($systems as $system) {
            ScanDirectory::dispatch($system) && $dirCount++;
        }

        foreach ($files as $filename) {
            $this->indexFile($filename) && $fileCount++;;
        }

        Log::info("Scanning $dirCount directories & $fileCount files");
        return true;
    }

    public function showAll($page = 0)
    {

        $params = [
            'index' => $this->index_name,
            'body' => [
                'query' => [
                    'match' => ['doc_type' => 'document']
                ]
            ]
        ];
        //res = es.search(index='indexname', doc_type='typename', body=doc,scroll='1m')
        return Elasticsearch::search($params);

    }

    public function Everything($page = 0)
    {

        $params = [
            'index' => $this->index_name,
            'body' => [
                'query' => [
                    "match_all" => [ "boost" => 1.0 ]
                ]
            ]
        ];
        //res = es.search(index='indexname', doc_type='typename', body=doc,scroll='1m')
        return Elasticsearch::search($params);

    }

    public function systems(){

        $filter = [ 
            'only_documents' => [
                'filter' => [
                    'term' => [
                        'doc_type' => 'document'
                        ]
                    ]
                ]
            ];
        $params = [
            'index' => $this->index_name,
            'body'  => [
                'aggs' => [
                    'uniq_systems' => [
                        'composite' => [ 
                            'size' => 100,
                            'sources' => [
                                'systems' => ['terms' => ['field' => 'system'] ]
                            ]
                        ],
                        'aggs' => $filter
                    ]
                ]
            ]
        ];
        $buckets = array();
        $continue = true;
        while ($continue == true){
            $results = Elasticsearch::search($params);
            $buckets = array_merge($buckets, $results['aggregations']['uniq_systems']['buckets']);
            if(isset($results['aggregations']['uniq_systems']['after_key'])){
                $params['body']['aggs']['uniq_systems']['composite']['after'] 
                    = $results['aggregations']['uniq_systems']['after_key'];
            } else {
                $continue = false;
            }
        }
        $return = array();
        foreach($buckets as $bucket){
            $system = $bucket['key']['systems'];
            $count = $bucket['only_documents']['doc_count'];
            $return[$system] = $count;
        }
        return $return;

    }

    public function AllBySystem($system, $page=1, $size){

        $from = ($page-1) * $size;

        $params = [
            'index' => $this->index_name,
            'body' => [
                'size' => $size,
                'from' => $from,
                'query' => [
                    'bool' => [
                        'must' => [
                            'match' => [
                                'system' => [ 
                                    'query' => $system,
                                    "operator" => "and"  
                                ]
                            ]
                        ],
                        'filter' => [
                            'match' => [ 'doc_type' => 'document' ]
                            ]
                        ]
                    ]
                ]
            ];
        //res = es.search(index='indexname', doc_type='typename', body=doc,scroll='1m')
        return Elasticsearch::search($params);
    }

    public function pageSearch($terms, $system, $document, $page=1, $size){

        // $system = "Goblin Quest";

        $from = ($page-1) * $size;

        $params = [
            'index' => $this->index_name,
            'body' => [
                'size' => $size,
                'from' => $from,
                'query' => [
                    'bool' => [
                        'must' => [
                            'match_phrase' => [
                                'attachment.content' => [ 
                                    'query' => $terms,
                                    ]
                                ]
                            ]
                        ]
                    ],
                  "highlight" => [
                    "fields" => [
                        "attachment.content" => new \stdClass()
                    ]
                  ]
                ]
            ];

        if($system){
            $params['body']['query']['bool']['filter'][] = [
                            'match' => [ 'system' => $system ]
                        ];
        }

        if($document){
            $params['body']['query']['bool']['filter'][] = [
                            'match' => [ 'path' => $document ]
                        ];
        }


        $params['body']["aggs"] = [
            "parents" => [
              "terms"=> [
                "field"=> "page_relation#document", 
                "size"=> 30
              ]
            ],
            "systems" => [
              "terms"=> [
                "field"=> "system", 
                "size"=> 30
            ]
            ]
          ];


        Log::debug($params);


        return Elasticsearch::search($params);
    }
}
