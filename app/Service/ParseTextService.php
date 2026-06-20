<?php

namespace App\Service;

use Illuminate\Support\Facades\Storage;

class ParseTextService extends ParserService
{
    public function parsePages()
    {
        return [Storage::disk('libris')->get($this->filename)];

    }// end parsePages()

}// end class
