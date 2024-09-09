<?php

namespace App\Interfaces;
use App\Service\ParserService;

interface IndexerInterface
{

    public function setup();

    public function indexDocument(ParserService $parser, $thumbnailURL);

    public function indexPages(ParserService $parser);

    public function updateDocument($id, $body);

    public function deleteDocument($docId);

    public function deletePage($docId);

    public function deleteDocumentPages($docId = false);

    public function fetchDocument($id);

    public function fetchAllDocuments($tag = false, $size = 100, $searchAfter = false);

    public function countAllDocuments();

    public function countAllPages($docId = false);

    public function fetchAllPages($docId = false, $size = 100, $searchAfter = false);

    public function updateIndex();

    public function deleteIndex();

    public function listSystems();

    public function listDocuments($system, $page = 1, $size = 60, $tag = false);

    public function tagsForSystem($system);

    public function searchPages($terms, $system, $document, $tag, $page = 1, $size = 60);

    public function getDocThumbnail($doc);

    public function getLocalFilename($doc);

    public function updateSingleField($document, $field, $value);

    public function sweepPages($bar);
}