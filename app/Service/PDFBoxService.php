<?PHP

namespace App\Service;

use App\Exceptions;

use App\Service\ParserService;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class PDFBoxService extends ParserService
{

	// java -jar pdfbox-app-x.y.z.jar PDFSplit [OPTIONS] <PDF file>
	private $pdfbox_bin = "/usr/share/java/pdfbox-app-2.0.20.jar";
	// /usr/share/java/pdfbox2-tools-2.0.13.jar


	public function parse_pages(){
		set_time_limit ( 120 );
        ini_set('memory_limit', '2G');

		$tempfile = tempnam(sys_get_temp_dir(),"scanfile-");
		$pdf_content = Storage::disk('libris')->get($this->filename);
        //$tempfile2 = tempnam(sys_get_temp_dir(),"scanfile-");
        file_put_contents($tempfile, $pdf_content); 
		
		$cmd_tpl = '/usr/bin/java -jar %s ExtractText -html -console "%s"';

		$cmd = sprintf($cmd_tpl, $this->pdfbox_bin, $tempfile);
		exec($cmd, $output, $return);

		if($return > 0){
			Log::Error(implode("\n", $output));
			throw new Exceptions\LibrisParseFailed(implode("\n", $output));
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