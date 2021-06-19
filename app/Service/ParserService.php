<?PHP

namespace App\Service;

use App\Exceptions;

use Elasticsearch;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


abstract class ParserService
{

	protected $file;
	protected $index_name;
	protected $last_modified;

	public $system;
	public $tags;
	public $title;


	abstract public function parse_pages();

	public function __construct($file, $index_name){

        if (Storage::disk('libris')->missing($file)) {
            Log::error("[AddDoc] ".$this->filename." in 'total existance failure' error");
            throw new Exceptions\LibrisNotFound();
        }


		$this->filename = $file;
		$this->index_name = $index_name;
        $this->last_modified = Storage::disk('libris')->lastModified($file);


        $this->tags = explode('/', $this->filename);

        $system = array_shift($this->tags);
        $system = preg_replace('!\.[^.]+$!', '', $system);
        $system = preg_replace('!-|_!', ' ', $system);

        $this->system = $system;

        $boom = explode("/", $this->filename);

        $title = array_pop($boom);
        $title = preg_replace('!\.[^.]+$$!', '', $title);
        $title = preg_replace('!-|_!', ' ', $title);
        $this->title = $title;


        $size = Storage::disk('libris')->size($this->filename);
        $sizeMB = number_format($size/1024);

        if ($size > 1024 * 1024 * 512) {
            Log::error("[AddDoc] {$this->title} is ".$sizeMB."Mb, too large to index");
            throw new Exceptions\LibrisTooLarge();
        } else {
        	Log::info("[AddDoc] {$this->title} is a ".$sizeMB."Mb");
        }
	}

	public function index(){

        try {
            $this->pages = $this->parse_pages();
        } catch (Exceptions\LibrisParseFailed $e) {
            Log::error("[AddDoc] FAILED TO PARSE Name: [{$this->filename}], System: [{$this->system}]");
            return false;
        }

        Log::debug("[AddDoc] Name: {$this->title}, System: {$this->system}, Tags: ".implode(",",$this->tags));

        $params = [
            'index' => $this->index_name,
            'id' => $this->filename,
            'routing' => $this->filename,
            'body' => [
                "doc_type" => "document",
                'path' => $this->filename,
                'system' => $this->system,
                'tags' => $this->tags,
                'title' => $this->title,
                'last_modified' => $this->last_modified,
                "page_relation" => [
                    "name" => "document",
                ],
            ],
        ];

        if(!$this->pages){
        	// $params['body']['data'] = Storage::disk('libris')->get($this->filename);
        }

        Log::debug("[AddDoc] {$this->filename} Indexing");
        Elasticsearch::index($params);

        if($this->pages){
        	$this->index_pages();
        }

	}

	public function index_pages(){

        Log::info("[AddDoc] {$this->filename} Indexing Pages");

        $pageCount = count($this->pages);

        foreach($this->pages as $pageIndex => $text){
            $pageNo = $pageIndex + 1;
            set_time_limit ( 30 );

            $params = [
                'index' => $this->index_name,
                'id' => $this->filename."/".$pageNo,
                'routing' => $this->filename,
                'pipeline' => 'attachment_pipeline',
                'body' => [
                    "doc_type" => "page",
                    'data' => base64_encode($text),
                    'pageNo' => $pageNo,
                    'path' => $this->filename,
                    'system' => $this->system,
                    'tags' => $this->tags,
                    'title' => $this->title,
                    "page_relation" => [
                        "name" => "page",
                        "parent" => $this->filename,
                    ]
                ],
            ];
            Elasticsearch::index($params);
        }
        Log::debug("[AddDoc] {$this->filename} Added $pageCount Pages");
	}


}