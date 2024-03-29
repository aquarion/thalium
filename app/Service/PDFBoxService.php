<?php

namespace App\Service;

use App\Exceptions;

use App\Service\ParserService;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PDFBoxService extends ParserService
{

    private $pdfboxBin = "/usr/share/java/pdfbox.jar";

    private $tempFile = "";

    private $pdkTemp = "";


    public function __construct($file, $elasticSearchIndex)
    {
        parent::__construct($file, $elasticSearchIndex);

        set_time_limit(120);
        ini_set('memory_limit', '2G');

        $this->tempFile = tempnam(sys_get_temp_dir(), "pdfbox-");
        $PDFContent     = Storage::disk('libris')->get($this->filename);
        file_put_contents($this->tempFile, $PDFContent);

    }//end __construct()


    public function run_pdfbox($command)
    {
        $commandTemplate = '/usr/bin/java -jar %s %s "%s" 2>&1';

        $cmd = sprintf($commandTemplate, $this->pdfboxBin, $command, $this->tempFile);
        Log::Info($cmd);
        exec($cmd, $outputRef, $return);

        $output = new \ArrayObject($outputRef);
        $output = $output->getArrayCopy();

        if ($return > 0) {
            $error = array_shift($output);
            Log::Error($this->filename);
            Log::Error($error);
            if (\Str::contains($error, 'You do not have permission')) {
                Log::Info("Trying pdftk...");
                $this->pdkTemp = tempnam(sys_get_temp_dir(), "pdftk-");

                $pdftkCmdTemplate = "pdftk %s input_pw output %s";
                $pdftkCmd         = sprintf($pdftkCmdTemplate, $this->tempFile, $this->pdkTemp);

                exec($pdftkCmd, $pdftkOutputRef, $pdftkReturn);

                $pdftkOutput = new \ArrayObject($pdftkOutputRef);
                $pdftkOutput = $pdftkOutput->getArrayCopy();

                $cmd = sprintf($commandTemplate, $this->pdfboxBin, $command, $this->pdkTemp);
                Log::Info($cmd);
                exec($cmd, $outputRef, $return);

                $output = new \ArrayObject($outputRef);
                $output = $output->getArrayCopy();
                if ($return > 0) {
                    $error = array_shift($output);
                    Log::Error($error);
                    throw new Exceptions\LibrisParseFailed($error);
                }

                return $output;
            }//end if

            throw new Exceptions\LibrisParseFailed($error);
        }//end if

        return $output;

    }//end run_pdfbox()


    public function parsePages()
    {
        $output = $this->run_pdfbox("ExtractText -html -console");

        $page  = "";
        $pages = [];

        foreach ($output as $line) {
            if (substr($line, 0, 4) == "<div") {
                $page = strip_tags($page);
                $page = trim($page);
                if ($page) {
                    $pages[] = $page;
                }

                $page = "";
            }

            $page .= $line." ";
        }

        return $pages;

    }//end parsePages()


    public function generateDocThumbnail()
    {
        Log::info("[generateDocThumbnail] {$this->filename} Generating PDF Thumbnail");

        $image = new \Imagick($this->tempFile.'[0]');
        Log::Info('[Imagick] Hello '.$this->tempFile);
        $image->setFormat("png");
        Log::Info('[Imagick] Format: '.$image->getFormat());

        $w = $image->getImageWidth();
        $h = $image->getImageHeight();
        $image->trimImage(1);

        if (($w > $h) > 1.1) {
            // dd($w."/".$h." ".($w/$h));
            $image->cropImage(($w / 2), $h, ($w / 2), 0);
        }

        // If 0 is provided as a width or height parameter,
        // aspect ratio is maintained
        $image->thumbnailImage(200, 300, true);

        return $image;

    }//end generateDocThumbnail()


    public function delTree($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            if (is_dir("$dir/$file")) {
                $this->delTree("$dir/$file");
            } else {
                unlink("$dir/$file");
            }
        }

        return rmdir($dir);

    }//end delTree()


    public function __destruct()
    {
        if ($this->tempFile) {
            unlink($this->tempFile);
        }

        if (file_exists($this->tempFile.'.dir')) {
            $this->delTree($this->tempFile.'.dir');
        }

    }//end __destruct()


}//end class
