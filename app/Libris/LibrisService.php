<?php

namespace App\Libris;

use Elasticsearch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use setasign\Fpdi\Fpdi;

use App\Jobs\ScanDirectory;
use App\Jobs\ScanPDF;
use setasign\Fpdi\PdfParser\StreamReader;

use App\Service\PDFBoxService;

class LibrisService implements LibrisInterface
{
    public $index_name = "libris";

    public function addDocument($file, $log = false)
    {
        // Hackity Hack Hack so I can use this as an artisan command
        if(!$log){
            $log = Log::stack(['stack']);
        }

        $last_modified = Storage::disk('libris')->lastModified($file);

        $pdf = new PDFBoxService($file);

        $pdf->analyse_pdf();

        if ($doc = $this->fetchDocument($file)){
             if(isset($doc['_source']['last_modified'])){
                if($doc['_source']['last_modified'] == $last_modified){
                     $log->info("[AddDoc] $file is already in the index with the same date");
                     return true;
                }
             }
             
             $log->info("[AddDoc] $file is already in the index, but this is different?");
        }

    
        $log->debug("[AddDoc] Name: {$pdf->title}, System: {$pdf->system}, Tags: ".implode(",",$pdf->tags));


        $log->debug("[AddDoc] Parsing $file ...");
        
        $pages = $pdf->parse();

        $params = [
            'index' => $this->index_name,
            'id' => $file,
            'routing' => $file,
            'body' => [
                "doc_type" => "document",
                'path' => $file,
                'system' => $pdf->system,
                'tags' => $pdf->tags,
                'title' => $pdf->title,
                'last_modified' => $last_modified,
                "page_relation" => [
                    "name" => "document",
                ],
            ],
        ];
        $log->debug("[AddDoc] $file Indexing");
        $return = Elasticsearch::index($params);

        $log->info("[AddDoc] $file Getting Pages");
        
        $pageCount = count($pages);

        foreach($pages as $pageIndex => $text){
            $pageNo = $pageIndex + 1;
            set_time_limit ( 30 );

            $params = [
                'index' => $this->index_name,
                'id' => $file."/".$pageNo,
                'routing' => $file,
                'pipeline' => 'attachment_pipeline',
                'body' => [
                    "doc_type" => "page",
                    'data' => base64_encode($text),
                    'pageNo' => $pageNo,
                    'path' => $file,
                    'system' => $pdf->system,
                    'tags' => $pdf->tags,
                    'title' => $pdf->title,
                    "page_relation" => [
                        "name" => "page",
                        "parent" => $file,
                    ]
                ],
            ];
            Elasticsearch::index($params);
        }
        $log->debug("[AddDoc] $file Added $pageCount Pages");

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

        if ($mimeType !== "application/pdf"){
             Log::error("$filename is not a pdf");
             return false;
        }

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
