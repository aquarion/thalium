<?php

namespace App\Libris;


interface LibrisInterface
{


    // public function __construct()
    public function addDocument($file, $log=false);


    // public function indexDocument(ParserService $parser)
    // public function getParser($file)
    // public function deleteDocument($docId)
    // public function updateDocument($docId, $body)
    // public function deletePage($docId)
    // public function fetchDocument($id)
    // public function deleteIndex()
    // public function scanFile($filename)
    // public function dispatchIndexFile($filename)
    // public function dispatchIndexDir($filename)
    // public function countAllDocuments()
    // function fetchAllDocuments($tag = false, $size = 100, $searchAfter = false){
    // function countAllPages($docId = false){
    // function fetchAllPages($docId = false, $size = 100, $searchAfter = false){
    public function systems();


    public function docsBySystem($system, $page, $perpage, $tag);


    // public static function tagSort($a, $b)
    public function tagsForSystem($system);


    // public function searchPages($terms, $system, $document, $tag, $page = 1, $size = 60)
    public function getDocThumbnail($doc, $regen=false);


    // public function updateDocThumbnail($doc)
    // protected function saveDocThumbnail(\Imagick $image, $file)
    // public function generateDocThumbnail($file)
    //     $called_by = debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS,2)[1]['function'];
    // public function thumbnailDataURI($file)
    // public function dataURI($image)
    // public function getSystemThumbnail($system)
    // public function sweepPages($docId, $bar=false)
    // protected function fileIsMissing($docId)
    // public function getIndexer()


}//end interface
