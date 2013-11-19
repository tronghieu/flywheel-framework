<?php
namespace Flywheel\Document\Compressor;
class Document_Compressor_UriRewrite {
	public function __construct() {		
	}
	
	public function rewrite($link, $currentDir, $docRoot) {
		$pos = strpos($link, '?');
		if (false !== $pos) {
			$version = substr($link, $pos);
			$link = substr($link, 0, $pos);			
		}
		
		$link = self::_realpath($link, $currentDir);		
		$path = substr($link, strlen($docRoot));
		
		$uri = strtr($path, '/\\', '//');
		
        // remove /./ and /../ where possible
        $uri = str_replace('/./', '/', $uri);

        // inspired by patch from Oleg Cherniy
        do {
            $uri = preg_replace('@/[^/]+/\\.\\./@', '/', $uri, 1, $changed);            
        } while ($changed);

        if (false !== $pos) {
        	$uri .= $version;        	        	        	
        }
        return $uri;
	}
	
	/**
     * Get realpath with any trailing slash removed. If realpath() fails,
     * just remove the trailing slash.
     * 
     * @param string $path
     * 
     * @return mixed path with no trailing slash
     */
    protected static function _realpath($path, $currentDir) {   	
        $realPath = realpath($currentDir .$path);                
        if ($realPath !== false) {
            $path = $realPath;
        }
        return rtrim($path, '/\\');
    }
}