<?php
namespace Flywheel\Document\Html; 
class OpenGraph {	
	public $title;
	public $description;
	public $type;
	public $image;
    public $icon = '';
	public $video;
	public $video_height;
	public $video_width;
	public $video_type;	
	public $site_name;
	public $url;
    public $namespace_uri;
	
	public function __construct() {
		$this->type = 'article';
		$this->site_name = 't90';
		$this->video_type  = "application/x-shockwave-flash";	
	}

    /**
     * import feed's attachment data to OG
     * @param $data
     */
	public function addFeedData($data){	
		$this->title = @$data['attach_title'];
		$this->description = @$data['attach_desc'];
		$this->image = @$data['attach_thumb'];
		$this->video = @$data['attach_video'];
		$this->video_height = @$data['attach_video_height'];
		$this->video_width = @$data['attach_video_width'];
		$this->video_type = @$data['attach_video_type'];
	}
	
	public function toHtml(){
		$og = '';
		$og .= '<meta property="og:site_name" content="' .$this->site_name .'" />' ."\n";	
		$og .= '<meta property="og:title" content="' .$this->title .'" />' ."\n";
		$og .= '<meta property="og:description" content="' .$this->description .'" />' ."\n";
        $og .= (null != $this->url)? '<meta property="og:url" content="' .$this->url .'" />' ."\n" : '';
		if($this->video){
			$this->type = 'video';
			$og .= '<meta property="og:video" content="' .$this->video .'" />' ."\n";
			if($this->video_height) $og .= '<meta property="og:video:height" content="' .$this->video_height .'" />' ."\n";
			if($this->video_width) $og .= '<meta property="og:video:width" content="' .$this->video_width .'" />' ."\n";
			if($this->video_type) $og .= '<meta property="og:video:type" content="' .$this->video_type .'" />' ."\n";
		}
		$og .= '<meta property="og:type" content="' .$this->type .'" />' ."\n";
		if (null != $this->image) {
			if (false === stripos($this->image, 'http')) {
				$this->image = IMAGE_URL .$this->image;
			}

            if($this->icon != ''){
                $this->image =  $this->icon;
            }

			
			$og .= '<meta property="og:image" content="' .$this->image .'" />' ."\n";
		}
		return $og;
	}
}


