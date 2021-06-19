<?PHP

namespace App\Service;

use App\Exceptions;

use App\Service\ParserService;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class ParseTextService extends ParserService
{

	// java -jar pdfbox-app-x.y.z.jar PDFSplit [OPTIONS] <PDF file>
	private $pdfbox_bin = "/usr/share/java/pdfbox.jar";
	// /usr/share/java/pdfbox2-tools-2.0.13.jar


	public function parse_pages(){
		return [ Storage::disk('libris')->get($this->filename) ];

	}

}