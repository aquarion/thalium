<?php

namespace App\Service;

use App\Exceptions;

use App\Service\ParserService;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ParseTextService extends ParserService
{


    public function parsePages()
    {
        return [ Storage::disk('libris')->get($this->filename) ];

    }//end parsePages()


}//end class
