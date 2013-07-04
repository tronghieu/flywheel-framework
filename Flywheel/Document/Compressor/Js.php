<?php
namespace Flywheel\Document\Compressor;
class Document_Compressor_Js {
	protected $_js;
	public function __construct() {		
	}
	
	public function process($jsFile, $jsv='1.0') {
        $document	= Ming_Factory::getDocument();
        if(!$document->jsBaseDir)
            $document->jsBaseDir = PUBLIC_DIR.'assets'.DS.'js'.DS;
        $baseDir = $document->jsBaseDir;
		foreach ($jsFile as $fileName=>$data) {
			$file = $baseDir.$fileName;		
			$this->_sum .= $fileName.@filemtime($file);
		}
        $this->_sum .= $jsv;
		$name = hash('crc32b' ,$this->_sum) .'.js';
		if (!file_exists(PUBLIC_DIR .'temp' .DS .$name)) {			
			$js = '';
			foreach ($jsFile as $file=>$data) {
				$js .= file_get_contents($baseDir.$file);				
			}
			$this->_js = $js;			
			$this->save($name);			
		}

		return $name;
	}
	
	public function minify() {		
	}
	
	public function show() {
	}
	
	/**
	 * save css to file in temp directory
	 */
	public function save($output) {	
		file_put_contents(PUBLIC_DIR.'temp'.DS.$output, $this->_js);				
	}
}