<?php

namespace App\Libris;

use Elasticsearch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use setasign\Fpdi\Fpdi;

use App\Jobs\ScanDirectory;
use App\Jobs\ScanPDF;
use setasign\Fpdi\PdfParser\StreamReader;

class LibrisService implements LibrisInterface
{
    public $index_name = "libris";

    public function addDocument($file, $log = false)
    {
        // Hackity Hack Hack so I can use this as an artisan command
        if(!$log){
            $log = Log::stack(['stack']);
        }


        set_time_limit ( 120 );
        ini_set('memory_limit', '2G');

        $size = Storage::disk('libris')->size($file);
        $mimeType = Storage::disk('libris')->mimeType($file);

        $tags = explode('/', $file);

        $system = array_shift($tags);
        $system = preg_replace('!\.pdf$!', '', $system);
        $system = preg_replace('!-|_!', ' ', $system);

        $boom = explode("/", $file);
        $filename = array_pop($boom);
        $filename = preg_replace('!\.pdf$!', '', $filename);
        $title = preg_replace('!-|_!', ' ', $filename);

        $log->info("[AddDoc] $filename is a $mimeType of size ".number_format($size/1024)."Mb");

        if ($mimeType !== "application/pdf"){
             $log->info("[AddDoc] $filename is not a pdf");
             return true;
        }
        if ($doc = $this->fetchDocument($file)){
             $log->info("[AddDoc] $filename is already in the index");
             // return true;
        }

        if ($size > 1024 * 1024 * 512) {
            $log->error("[AddDoc] $filename is a $mimeType of size ".number_format($size/1024)."Mb, too large to index");
            return true;
        }

        $log->debug("[AddDoc] Name: $filename, System: $system, Tags: ".implode(",",$tags));

        $log->debug("[AddDoc] Parsing $filename ...");
        $pdf_content = Storage::disk('libris')->get($file);


        try {   
            $log->debug("[AddDoc] $filename Loading Parser");
            $parser = new \Smalot\PdfParser\Parser();   
            $pdf    = $parser->parseContent($pdf_content);
            unset($pdf_content);

        } catch ( \Exception $e){

            $log->debug("[AddDoc] $filename isn't readable directly, running though ghostscript...");
            $tempfile = tempnam(sys_get_temp_dir(),"scanfile-");
            $tempfile2 = tempnam(sys_get_temp_dir(),"scanfile-");
            file_put_contents($tempfile, $pdf_content); 
            exec('gs -q -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -o "'.$tempfile2.'" "'.$tempfile.'"', $output, $return);
            // dump('gs -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$tempfile2.'" "'.$tempfile.'"');
            if ($return > 0) {
                $log->error($output);
                throw new \Exception("Ghostscript Failed");
            }
            $log->debug("[AddDoc] $filename ... converted. Here we go...");
            $pdf_content = file_get_contents($tempfile2);
            unlink($tempfile);
            unlink($tempfile2);


            $parser = new \Smalot\PdfParser\Parser();   
            $pdf    = $parser->parseContent($pdf_content);
            unset($pdf_content);
        }

        $log->debug("[AddDoc] $filename Getting Details");
        $details  = $pdf->getDetails();

        $params = [
            'index' => $this->index_name,
            'id' => $file,
            'routing' => $file,
            'body' => [
                'system' => $system,
                'filename' => $filename,
                'path' => $file,
                'title' => $title,
                'tags' => $tags,
                "page_relation" => [
                    "name" => "document",
                ],
                'metadata' => $details,
                "doc_type" => "document"
            ],
        ];
        $log->debug("[AddDoc] $filename Indexing");
        $return = Elasticsearch::index($params);

        $log->info("[AddDoc] $filename Getting Pages");
        $pages  = $pdf->getPages();
        $pageCount = count($pages);

        foreach($pages as $pageIndex => $page){
            $pageNo = $pageIndex + 1;
            set_time_limit ( 30 );

            // $pageNo
            // $content

                $text = $page->getText();
            // try {
            // } catch (\Exception $e) {
            //     $log->error("Error on $filename $pageNo: ".$e->getMessage());
            // }

            $params = [
                'index' => $this->index_name,
                'id' => $file."/".$pageNo,
                'routing' => $filename,
                'pipeline' => 'attachment_pipeline',
                'body' => [
                    'system' => $system,
                    'tags' => $tags,
                    'pageNo' => $pageNo,
                    "doc_type" => "page",
                    'filename' => $filename,
                    'path' => $file,
                    'title' => $title,
                    'data' => base64_encode($text),
                    "page_relation" => [
                        "name" => "page",
                        "parent" => $filename,
                    ]
                ],
            ];
            Elasticsearch::index($params);
        }
        $log->debug("[AddDoc] $filename Added $pageCount Pages");

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

    public function updateIndex(){

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

        $this->updatePipeline();
        $this->updateIndex();

        $dirCount = 0;
        $fileCount = 0;

        $systems = Storage::disk('libris')->directories('.');
        $files = Storage::disk('libris')->files('.');

        foreach ($systems as $system) {
            ScanDirectory::dispatch($system, array(), $system) && $dirCount++;
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
