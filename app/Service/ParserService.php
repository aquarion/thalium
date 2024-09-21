<?php

namespace App\Service;

use App\Exceptions;

use Elasticsearch;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

abstract class ParserService
{

    protected $file;


    protected $pages;

    public $lastModified;

    public $system;

    public $tags;

    public $title;

    public $filename;


    abstract public function parsePages();


    public function generateTags()
    {
        $tags = explode('/', $this->filename);
        array_pop($tags);
        // remove the filename from the tags list
        return $tags;

    }//end generateTags()


    public function __construct($file)
    {
        if (Storage::disk('libris')->missing($file)) {
            Log::error("[AddDoc] ".$this->filename." in 'total existance failure' error");
            throw new Exceptions\LibrisNotFound();
        }

        $this->filename           = $file;
        $this->lastModified       = Storage::disk('libris')->lastModified($file);

        $this->tags = $this->generateTags();

        $system = array_shift($this->tags);
        $system = preg_replace('!\.[^.]+$!', '', $system);
        $system = preg_replace('!-|_!', ' ', $system);

        $this->system = $system;

        $boom = explode("/", $this->filename);

        $title       = array_pop($boom);
        $title       = preg_replace('!\.[^.]+$$!', '', $title);
        $title       = preg_replace('!-|_!', ' ', $title);
        $this->title = $title;

        $size   = Storage::disk('libris')->size($this->filename);
        $sizeMB = number_format($size / 1024);

        if ($size > (1024 * 1024 * 1024)) {
            Log::error("[Parser] TOO LARGE: {$this->title} is ".$sizeMB."Mb, too large to index");
            throw new Exceptions\LibrisTooLarge();
        } else {
            Log::info("[Parser] {$this->title} is a ".$sizeMB."Mb PDF");
        }

    }//end __construct()


    public function generateDocThumbnail()
    {
        Log::info("[AddDoc] {$this->filename} Generating Thumbnail");
        return genericThumbnail($this->title);

    }//end generateDocThumbnail()


}//end class
