<?PHP

namespace App\Service;

use App\Exceptions;

use App\Service\ParserService;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class PDFBoxService extends ParserService
{

	// java -jar pdfbox-app-x.y.z.jar PDFSplit [OPTIONS] <PDF file>
	private $pdfbox_bin = "/usr/share/java/pdfbox.jar";
	private $tempfile = "";
	// /usr/share/java/pdfbox2-tools-2.0.13.jar

	public function __construct($file, $index_name){
		parent::__construct($file, $index_name);

		set_time_limit ( 120 );
        ini_set('memory_limit', '2G');

		$this->tempfile = tempnam(sys_get_temp_dir(),"scanfile-");
		$pdf_content = Storage::disk('libris')->get($this->filename);
        //$tempfile2 = tempnam(sys_get_temp_dir(),"scanfile-");
        file_put_contents($this->tempfile, $pdf_content);

	}

	public function run_pdfbox($command){

		$cmd_tpl = '/usr/bin/java -jar %s %s "%s" 2>&1';

		$cmd = sprintf($cmd_tpl, $this->pdfbox_bin, $command, $this->tempfile);
		exec($cmd, $outputRef, $return);

		$output = new \ArrayObject($outputRef) ;
		$output = $output->getArrayCopy();

		if($return > 0){
			$error = array_shift($output);
			Log::Error($this->filename);
			Log::Error($error);
			throw new Exceptions\LibrisParseFailed($error);
		}

		return $output;
	}

	public function parse_pages(){


		$output = $this->run_pdfbox("ExtractText -html -console");

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

	public function generateThumbnail(){
        Log::info("[AddDoc] {$this->filename} Generating PDF Thumbnail");

		$tempdir = $this->tempfile.".dir";

		mkdir($tempdir);

		$command = sprintf('PDFToImage -imageType png -outputPrefix "%s/" -page 1', $tempdir);
		$output = $this->run_pdfbox($command);

		$image = new \Imagick($tempdir.'/1.png');

		// If 0 is provided as a width or height parameter,
		// aspect ratio is maintained
		$image->thumbnailImage(200, 300, true);

        $imageData = base64_encode($image);
        return 'data: image/png;base64,'.$imageData;


	}

	public static function delTree($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
		 foreach ($files as $file) {
		   (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
		 }
		 return rmdir($dir);
	}

	public function __destruct(){
		if($this->tempfile){
			unlink($this->tempfile);
		}
		if(file_exists($this->tempfile.'.dir')){
			$this->delTree($this->tempfile.'.dir');
		}
	}

}