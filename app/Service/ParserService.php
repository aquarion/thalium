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
                'thumbnail' => $this->generateThumbnail(),
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

        return true;
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
                    'thumbnail' => $this->generateThumbnail(),
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

    function wordWrapAnnotation($image, $draw, $text, $maxWidth)
    {
        $text = trim($text);

        $words = preg_split('%\s%', $text, -1, PREG_SPLIT_NO_EMPTY);
        $lines = array();
        $i = 0;
        $lineHeight = 0;

        while (count($words) > 0)
        {
            $metrics = $image->queryFontMetrics($draw, implode(' ', array_slice($words, 0, ++$i)));
            $lineHeight = max($metrics['textHeight'], $lineHeight);

            // check if we have found the word that exceeds the line width
            if ($metrics['textWidth'] > $maxWidth or count($words) < $i)
            {
                // handle case where a single word is longer than the allowed line width (just add this as a word on its own line?)
                if ($i == 1)
                    $i++;

                $lines[] = implode(' ', array_slice($words, 0, --$i));
                $words = array_slice($words, $i);
                $i = 0;
            }
        }

        return array($lines, $lineHeight);
    }


    public function generateThumbnail(){

        Log::info("[AddDoc] {$this->filename} Generating Thumbnail");

        /* Create a new imagick object */
        $im = new \Imagick();

        /* Create new image. This will be used as fill pattern */
        $im->newPseudoImage(50, 50, "gradient:red-black");

        /* Create imagickdraw object */
        $draw = new \ImagickDraw();

        /* Start a new pattern called "gradient" */
        $draw->pushPattern('gradient', 0, 0, 50, 50);

        /* Composite the gradient on the pattern */
        $draw->composite(\Imagick::COMPOSITE_OVER, 0, 0, 50, 50, $im);

        /* Close the pattern */
        $draw->popPattern();

        /* Use the pattern called "gradient" as the fill */
        $draw->setFillPatternURL('#gradient');

        $draw->setGravity(\Imagick::GRAVITY_CENTER);

        /* Set font size to 52 */
        $draw->setFontSize(32);

        list($lines, $lineHeight) = $this->wordWrapAnnotation($im, $draw, $this->title, 200);
        for($i = 0; $i < count($lines); $i++){
            // $image->annotateImage($draw, $xpos, $ypos + $i*$lineHeight, 0, $lines[$i]);
            $draw->annotation(0, 0+$i*$lineHeight, $lines[$i]);
        }
        /* Annotate some text */

        /* Create a new canvas object and a white image */
        $canvas = new \Imagick();
        $canvas->newImage(200, 300, "white");

        /* Draw the ImagickDraw on to the canvas */
        $canvas->drawImage($draw);

        /* 1px black border around the image */
        $canvas->borderImage('black', 1, 1);

        /* Set the format to PNG */
        $canvas->setImageFormat('png');

        $imageData = base64_encode($canvas);
        return 'data:image/png;base64,'.$imageData;

    }


}