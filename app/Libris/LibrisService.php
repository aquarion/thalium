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

    public function addDocument($system, $tags, $filename)
    {
        set_time_limit ( 120 );
        ini_set('memory_limit', '2G');

        $size = Storage::disk('libris')->size($filename);

        $mimeType = Storage::disk('libris')->mimeType($filename);

        Log::info("[AddDoc] $filename is a $mimeType of size ".number_format($size/1024)."Mb");

        if ($mimeType !== "application/pdf"){
             Log::info("[AddDoc] $filename is not a pdf");
             return true;
        }
        if ($doc = $this->fetchDocument($filename)){
             Log::info("[AddDoc] $filename is already in the index");
             // return true;
        }

        if ($size > 1024 * 1024 * 512) {
            Log::error("[AddDoc] $filename is a $mimeType of size ".number_format($size/1024)."Mb, too large to index");
            return true;
        }


        Log::debug("[AddDoc] Parsing $filename ...");
        $pdf_content = Storage::disk('libris')->get($filename);

        $ver_string = substr($pdf_content, 0, 72);
        preg_match('!\d+\.\d+!', $ver_string, $match); 
        // save that number in a variable
        $ver = floatval($match[0]);

        if($ver > 1.4){
            Log::debug("[AddDoc] $filename is version $ver, running though ghostscript...");
            $tempfile = tempnam(sys_get_temp_dir(),"scanfile-");
            $tempfile2 = tempnam(sys_get_temp_dir(),"scanfile-");
            file_put_contents($tempfile, $pdf_content); 
            exec('gs -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$tempfile2.'" "'.$tempfile.'"', $output, $return);
            // dump('gs -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$tempfile2.'" "'.$tempfile.'"');
            if ($return > 0) {
                dump($output);
                throw new \Exception("Ghostscript Failed");
            }
            Log::debug("[AddDoc] $filename ... converted. Here we go...");
            $pdf_content = file_get_contents($tempfile2);
            unlink($tempfile);
            unlink($tempfile2);
        }


        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseContent($pdf_content);
        unset($pdf_content);

        $pages  = $pdf->getPages();
        $pageCount = count($pages);

        $details  = $pdf->getDetails();

        $params = [
            'index' => $this->index_name,
            'id' => $filename,
            'routing' => $filename,
            'body' => [
                'system' => $system,
                'tags' => $tags,
                "page_relation" => [
                    "name" => "document",
                ],
                'metadata' => $details,
                "doc_type" => "document"
            ],
        ];
        $return = Elasticsearch::index($params);

        return;

        foreach($pages as $pageIndex => $page){
            $pageNo = $pageIndex + 1;
            set_time_limit ( 30 );

            Log::info("[AddDoc] $filename $pageNo/$pageCount");
            // $pageNo
            // $content

            try {
                $text = $page->getText();
            } catch (\Exception $e) {
                Log::error("Error on $filename $pageNo ".$e);
            }

            $params = [
                'index' => $this->index_name,
                'id' => $filename."/".$pageNo,
                'routing' => $filename,
                'pipeline' => 'attachment_pipeline', // <----- here
                'body' => [
                    'system' => $system,
                    'tags' => $tags,
                    'pageNo' => $pageNo,
                    "doc_type" => "page",
                    'data' => base64_encode($text),
                    "page_relation" => [
                        "name" => "page",
                        "parent" => $filename,
                    ]
                ],
            ];
            Elasticsearch::index($params);
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

    public function reindex()
    {

        $this->updatePipeline();
        $this->updateIndex();

        $systems = Storage::disk('libris')->directories('.');
        $files = Storage::disk('libris')->files('.');

        foreach ($systems as $system) {
            Log::debug("[ScanDir] New Directory Scan Job: $system");
            dump($system);
            ScanDirectory::dispatch($system, array(), $system);
            // break;
        }

        foreach ($files as $filename) {
            $tags = explode('/', $filename);
            $file = array_pop($tags);

            // Execute scan job.
            Log::debug("[ScanDir] New File Scan Job: $filename");

            $mimeType = Storage::disk('libris')->mimeType($filename);

            if ($mimeType !== "application/pdf"){
                 Log::info("$filename is not a pdf");
                 continue;
             }

            ScanPDF::dispatch(substr($filename, 0, -4), $tags, $filename);
        }

        return "Scanning ".count($systems)." directories";
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

    public function AllBySystem($system){

        $params = [
            'index' => $this->index_name,
            'body' => [
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
}
