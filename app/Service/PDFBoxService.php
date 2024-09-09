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

    protected $pages;


    public function __construct($file, $elasticSearchIndex)
    {
        parent::__construct($file, $elasticSearchIndex);

        set_time_limit(120);
        ini_set('memory_limit', '2G');

        $this->tempFile = tempnam(sys_get_temp_dir(), "pdfbox-");
        $PDFContent     = Storage::disk('libris')->get($this->filename);
        file_put_contents($this->tempFile, $PDFContent);

    }//end __construct()

    private function exec($cmd)
    {
        $descriptorspec = array(
            0 => array("pipe", "r"), // STDIN
            1 => array("pipe", "w"), // STDOUT
            2 => array("pipe", "w"), // STDERR
        );
        $cwd = getcwd();
        $env = null;

        $return = array(
            'STDOUT' => '',
            'STDERR' => '',
            'return_value' => 127
        );

        $proc = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);
        if (is_resource($proc)) {
            // Output test:
            $return['STDOUT'] = stream_get_contents($pipes[1]);
            $return['STDERR'] = stream_get_contents($pipes[2]);
            $return['return_value'] = proc_close($proc);
        }
        return $return;
    }


    public function run_pdfbox($command)
    {
        $PDFBoxExecTemplate = '/usr/bin/java -jar %s %s "%s"';

        $cmd = sprintf($PDFBoxExecTemplate, $this->pdfboxBin, $command, $this->tempFile);
        Log::Info($cmd);
        // exec($cmd, $outputRef, $return);
        $PDFBoxExecute = $this->exec($cmd);

        if ($PDFBoxExecute['return_value'] > 0) {
            $error = $PDFBoxExecute['STDERR'];
            Log::Error($this->filename);
            Log::Error($error);
            if (\Str::contains($error, 'You do not have permission')) {
                Log::Info("Trying pdftk...");
                $this->pdkTemp = tempnam(sys_get_temp_dir(), "pdftk-");

                $pdftkExecTemplate = "pdftk %s input_pw output %s";
                $pdftkCmd         = sprintf($pdftkExecTemplate, $this->tempFile, $this->pdkTemp);


                $pdftkResult = $this->exec($pdftkCmd);
                if ($pdftkResult['return_value'] > 0) {
                    Log::error("PDFTK Error Parsing PDF ". $this->filename);
                    Log::Error($pdftkResult['STDOUT']);
                    Log::Error($pdftkResult['STDERR']);
                    throw new Exceptions\LibrisParseFailed($error);
                }


                $cmd = sprintf($PDFBoxExecTemplate, $this->pdfboxBin, $command, $this->pdkTemp);
                $retryPDFBoxExec = $this->exec($cmd);

                if ($retryPDFBoxExec['return_value'] > 0) {
                    Log::error("PDFBox + PDFTK Error Parsing PDF ". $this->filename);
                    Log::Error($retryPDFBoxExec['STDOUT']);
                    Log::Error($retryPDFBoxExec['STDERR']);
                    throw new Exceptions\LibrisParseFailed($error);
                }

                return $retryPDFBoxExec['STDOUT'];
            }//end if

            Log::error("PDFBox Error Parsing PDF ". $this->filename);
            Log::Error($PDFBoxExecute['STDOUT']);
            Log::Error($PDFBoxExecute['STDERR']);
            throw new Exceptions\LibrisParseFailed($error);
        }//end if

        return $PDFBoxExecute['STDOUT'];

    }//end run_pdfbox()


    public function parsePages()
    {
        $output = $this->run_pdfbox("export:text -html -console");

        $page  = "";
        $pages = [];

        $separator = "\r\n";
        $line = strtok($output, $separator);

        while ($line !== false) {
            if (substr($line, 0, 4) == "<div") {
                $page = strip_tags($page);
                $page = trim($page);
                if ($page) {
                    $pages[] = $page;
                }

                $page = "";
            }

            $page .= $line." ";
            $line = strtok($separator);
        }

        $this->pages = $pages;

        if (count($this->pages) == 0) {
            return false;
        }

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
