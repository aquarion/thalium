<?php

namespace App\Libris;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Jobs\ScanDirectory;
use App\Jobs\ScanFile;
use App\Service\PDFBoxService;
use App\Service\ParserService;
use App\Service\ParseTextService;
use App\Exceptions;
use App\Service\Indexer\ElasticSearch;

class LibrisService implements LibrisInterface
{
    protected $title;

    private $indexer;

    private $presentCache = array();
    private $missingCache = array();

    public function __construct()
    {
        $this->indexer = new ElasticSearch();
    }


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
            $this->indexDocument($parser);
        } else {
            Log::warning("[AddDoc] No parser for $file");
            throw new Exceptions\LibrisFileNotSupported("No parser for $file");
        }

    }//end addDocument()


    public function indexDocument(ParserService $parser)
    {
        // try {

        $parseResult = $parser->parsePages();

        if (!$parseResult) {
            Log::warning("No searchable content found in document {$parser->filename}");
            # Todo: Add a way to index documents without searchable content
        }
        // } catch (Exceptions\LibrisParseFailed $e) {
        //     Log::error("[AddDoc] FAILED TO PARSE Name: [{$parser->filename}], System: [{$parser->system}]");
        //     return 5;
        // }

        Log::debug("[AddDoc] Name: {$parser->title}, System: {$parser->system}, Tags: ".implode(",", $parser->tags));

        $image        = $this->generateDocThumbnail($parser->filename);
        $thumbnailURL = $this->saveDocThumbnail($image, $parser->filename);

        $this->indexer->indexDocument($parser, $thumbnailURL);

        if ($parseResult) { // If we have pages
            $this->indexer->indexPages($parser);
        }

        return 0;

    }//end indexDocument()



    public function getParser($file)
    {
        $mimeType      = Storage::disk('libris')->mimeType($file);
        $mimeTypeArray = explode("/", $mimeType);

        $parser = false;

        if ($mimeType == "application/pdf") {
            Log::debug("[Parser] Parsing PDF $file ...");
            $parser = new PDFBoxService($file);
        } elseif ($mimeTypeArray && $mimeTypeArray[0] == "text") {
            Log::debug("[Parser] Parsing $mimeType $file as text ...");
            $parser = new ParseTextService($file);
        } else {
            Log::debug("[Parser] Ignoring $mimeType $file, cannot parse ...");
            return false;
        }

        return $parser;

    }//end getParser()

    public function deleteDocument($docId)
    {
        return $this->indexer->deleteDocument($docId);

    }//end deleteDocument()

    public function updateDocument($docId, $body)
    {
        return $this->indexer->updateDocument($docId, $body);

    }//end deleteDocument()

    public function deletePage($docId)
    {
        $this->indexer->deletePage($docId);

    }//end deletePage()


    public function fetchDocument($id)
    {
        return $this->indexer->fetchDocument($id);

    }//end fetchDocument()


    public function deleteIndex()
    {
        $this->indexer->deleteIndex();

    }//end deleteIndex()




    public function scanFile($filename)
    {
        Log::debug("[Scanfile] Got lock for ".$filename." = ".md5($filename));
        try {
            $returnValue = $this->addDocument($filename);
            Log::info("[Scanfile] Finished ".$filename);
        } catch (Exceptions\LibrisFileNotSupported $e) {
            Log::warning("[Scanfile] Unsupported File Type ".$filename);
            return 0;
        } catch (Exceptions\LibrisTooLarge $e) {
            Log::warning("[Scanfile] File too big ".$filename);
            return 0;
        }

        // if ($returnValue !== true) {
        //     Log::error("[Scanfile] Bad return value ($returnValue) from PDFBox");
        //     throw new Exceptions\LibrisParserError("Bad return value ($returnValue) from PDFBox");
        // }

        return $returnValue;

    }//end scanFile()


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
        if (Storage::disk('libris')->directoryExists($filename)) {
            Log::error("$filename is not a directory");
            return false;
        }

        $tags   = explode('/', $filename);
        $system = substr(array_unshift($tags), 0, -4);

        Log::info("[indexDir] New Dir Scan Job: Sys: $system, Tags: ".implode(',', $tags).", File: $filename");
        ScanDirectory::dispatch($filename);
        return true;

    }//end dispatchIndexDir()


    public function countAllDocuments()
    {
        return $this->indexer->countAllDocuments();

    }//end countAllDocuments()

    function fetchAllDocuments($tag = false, $size = 100, $searchAfter = false){
        return $this->indexer->fetchAllDocuments($tag, $size, $searchAfter);
    }

    function countAllPages($docId = false){
        return $this->indexer->countAllPages($docId);
    }

    function fetchAllPages($docId = false, $size = 100, $searchAfter = false){
        return $this->indexer->fetchAllPages($docId, $size, $searchAfter)['hits']['hits'] ;
    }
    

    public function systems(){
        $systems = $this->indexer->listSystems();
        foreach ($systems as &$system) {
            $system['thumbnail'] = $this->getSystemThumbnail($system['system']);
        }
        return $systems;
    }

    public function docsBySystem($system, $page, $perpage, $tag){
        return $this->indexer->listDocuments($system, $page, $perpage, $tag);
    }


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
        $tagList = $this->indexer->tagsForSystem($system);

        usort($tagList, [LibrisService::class, "tagSort"]);

        return($tagList);

    }//end tagsForSystem()


    public function searchPages($terms, $system, $document, $tag, $page = 1, $size = 60)
    {
        return $this->indexer->searchPages($terms, $system, $document, $tag, $page, $size);

    }//end searchPages()


    public function getDocThumbnail($doc, $regen = false)
    {
        $thumbnailUrl = $this->indexer->getDocThumbnail($doc);

        if ($regen == "generic" && $thumbnailUrl) {
            $mimeType = Storage::disk('libris')->mimeType($doc['_source']['path']);
            if ($mimeType !== "application/pdf") {
                return $this->updateDocThumbnail($doc);
            }
        }

        if ($regen == "all") {
            return $this->updateDocThumbnail($doc);
        } elseif ($thumbnailUrl) {
            return $thumbnailUrl;
        } else {
            return $this->updateDocThumbnail($doc);
        }

    }//end getDocThumbnail()


    public function updateDocThumbnail($doc)
    {
       
        $file = $this->indexer->getLocalFilename($doc);

        Log::info("[updateDocThumbnail] Update Thumbnail - ".$file);

        if (Storage::disk('thumbnails')->exists($file)) {
            Log::info("[updateDocThumbnail] Already exists $file");

        }
        $image = $this->generateDocThumbnail($file);

        $thumbnailURL = $this->saveDocThumbnail($image, $file);

        if ($thumbnailURL == $doc['_source']['thumbnail']) {
            Log::info("[updateDocThumbnail] Filename already set");
            return $thumbnailURL;
        }

        $this->indexer->updateSingleField($doc['_id'], 'thumbnail', $thumbnailURL);
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

        $thumbnailFileName = md5($file).".png";
        $thumbnailURL      = Storage::disk('thumbnails')->url($thumbnailFileName);
        Storage::disk('thumbnails')->put($thumbnailFileName, $image);
        Log::debug("[saveDocThumbnail] saved to ".$thumbnailURL);
        return $thumbnailURL;

    }//end saveDocThumbnail()


    public function generateDocThumbnail($file)
    {
        Log::debug("[generateDocThumbnail] ".$file);
        $called_by = debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS,2)[1]['function'];
        Log::debug("[generateDocThumbnail] Called by ".$called_by);

        $parser = $this->getParser($file);

        if ($parser) {
            return $parser->generateDocThumbnail($file);
        }

        Log::debug("[generateDocThumbnail] No thumbnail generated");
        return false;

    }//end generateDocThumbnail()


    public function thumbnailDataURI($file)
    {
        return $this->dataURI($this->generateDocThumbnail($file));

    }//end thumbnailDataURI()


    public function dataURI($image)
    {
        return 'data:image/png;base64,'.base64_encode($image);

    }//end dataURI()


    public function getSystemThumbnail($system)
    {
        $file = ".thalium/".strtolower($system).".png";
        if (Storage::disk('libris')->missing($file)) {
            Log::error("[updateDocThumbnail] No Such thumbnail at ".Storage::disk('libris')->path($file));
            return $this->dataURI(genericThumbnail($system));
        } else {
            return Storage::disk('libris')->url($file);
        }

    }//end getSystemThumbnail()


    public function sweepPages($docId, $bar=false)
    {
        
        $deleted  = 0;
        $filename = false;

        $size        = 100;
        $searchAfter = false;
        $this->indexer->openPointInTime();

        while (true) {
            $pages = $this->fetchAllPages($docId, $size, $searchAfter);
            if (count($pages) == 0) {
                break;
            }

            foreach ($pages as $index => $page) {
                //var_dump($page);
                $pageId = $page['_id'];
                // if($filename != $page['_source']['path']) {
                //     $bar->setMessage("[" . $deleted . "] " . $filename);
                // }
                $filename = $page['_source']['path'];
                if ($this->fileIsMissing($filename)) {
                    $this->deletePage($pageId);
                    $bar ? $bar->setMessage(" Deleted ".$pageId) : null;
                    $deleted++;
                }

                $bar ? $bar->advance() : null;

                $searchAfter = $page['sort'];
            }
        }//end while

    }//end sweepPages()


    protected function fileIsMissing($docId)
    {
        if (array_key_exists($docId, $this->presentCache)) {
            return false;
        } else if (array_key_exists($docId, $this->missingCache)) {
            return true;
        }

        if (Storage::disk('libris')->missing($docId)) {
            array_push($this->missingCache, $docId);
            return true;
        } else {
            array_push($this->presentCache, $docId);
            return false;
        }

        throw new \Exception("Shouldn't have got here");

    }//end isMissing()


    public function getIndexer()
    {
        return $this->indexer;

    }//end getIndexer()

}//end class
