<?PHP

namespace App\Service;


use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class PDFBoxService
{

	protected $file;

	public $system;
	public $tags;
	public $title;

	// java -jar pdfbox-app-x.y.z.jar PDFSplit [OPTIONS] <PDF file>
	private $pdfbox_bin = "/usr/share/java/pdfbox-app-2.0.20.jar";
	// /usr/share/java/pdfbox2-tools-2.0.13.jar

	public function __construct($file){
		$this->filename = $file;
	}

	public function analyse_pdf(){

        if (Storage::disk('libris')->missing($this->filename)) {
            Log::error("[AddDoc] ".$this->filename." in 'total existance failure' error");

            throw new LibrisTooLarge();
        }

        $size = Storage::disk('libris')->size($this->filename);
        $mimeType = Storage::disk('libris')->mimeType($this->filename);

        $this->tags = explode('/', $this->filename);

        $system = array_shift($this->tags);
        $system = preg_replace('!\.pdf$!', '', $system);
        $system = preg_replace('!-|_!', ' ', $system);

        $this->system = $system;

        $boom = explode("/", $this->filename);

        $title = array_pop($boom);
        $title = preg_replace('!\.pdf$!', '', $title);
        $title = preg_replace('!-|_!', ' ', $title);
        $this->title = $title;

        Log::info("[AddDoc] $title is a $mimeType of size ".number_format($size/1024)."Mb");

        if ($mimeType !== "application/pdf"){
             Log::info("[AddDoc] $title is not a pdf");
             throw new LibrisNotPDF();
        }

        if ($size > 1024 * 1024 * 512) {
            Log::error("[AddDoc] $title is a $mimeType of size ".number_format($size/1024)."Mb, too large to index");

            throw new LibrisTooLarge();
        }


	}

	public function parse(){
		$tempfile = tempnam(sys_get_temp_dir(),"scanfile-");
		$pdf_content = Storage::disk('libris')->get($this->filename);
        //$tempfile2 = tempnam(sys_get_temp_dir(),"scanfile-");
        file_put_contents($tempfile, $pdf_content); 
		
		$cmd_tpl = '/usr/bin/java -jar %s ExtractText -html -console "%s"';

		$cmd = sprintf($cmd_tpl, $this->pdfbox_bin, $tempfile);
		exec($cmd, $output, $return);

		if($return > 0){
			Log::Error(implode("\n", $output));
			throw new LibrisParseFailed(implode("\n", $output));
		}

		$page = "";
		$pages = array();

		foreach($output as $line){
			if(substr($line, 0, 4) == "<div"){
				$page = strip_tags($page);
				$page = trim($page);
				if($page){
					$pages[] = $page;
				}
				$page = "";
			}
			$page .= $line." ";
		}
		return $pages;

	}

}