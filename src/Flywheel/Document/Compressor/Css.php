<?php
namespace Flywheel\Document\Compressor;
class Document_Compressor_Css {
	protected $_css;
	protected $_sum;
	protected $_inHack;
	protected $_replacementHash;
	protected $_placeholders = array();
	protected $_docRoot;
	protected $_cssBaseDir;
	protected $_publicDir;
	
	public function __construct($options = array()) {
		$document	= Ming_Factory::getDocument();	
		$this->_docRoot = isset($options['doc_root'])?
				$options['doc_root'] : PUBLIC_DIR;
        if(!$document->cssBaseDir)
            $document->cssBaseDir = PUBLIC_DIR.'assets'.DS.'css'.DS;
		$this->_cssBaseDir = isset($options['base_dir'])?
				$options['base_dir'] : $document->cssBaseDir;
		$this->_publicDir = isset($options['public_dir'])?
				$options['public_dir'] : $document->getPublicPath();
	}
	
	public function process($cssFile, $cssv='1.0') {
		$baseDir = $this->_cssBaseDir;
		foreach ($cssFile as $fileName=>$data) {
			$file = $baseDir.$fileName;
			if (!file_exists($file)) {
				unset($cssFile[$fileName]);
                continue;
			} else {
				$this->_sum .= $fileName .filemtime($file);
			}
		}
        $this->_sum .= $cssv;
		$name = hash('crc32b', $this->_sum) .'.css';
		if (!file_exists(PUBLIC_DIR .'temp' .DS .$name)) {			
			$css = '';
			foreach ($cssFile as $file=>$data) {
				$css .= file_get_contents($baseDir.$file);				
			}

			require_once LIBRARIES_DIR .'Cssmin' .DS .'Cssmin.php';
			$minifier = new CSSmin();
			$css = $minifier->run($css);

			/*
			 * @FIXME
			 */
			//$css = $this->minify($css);
			
        	$this->_css = $this->_fixUrl($css);			
			$this->save($name);			
		}

		return $name;
	}
	
	/**
     * Minify a CSS string
     * 
     * @param string $css
     * 
     * @return string
     */
	public function minify($css) {
		$this->_replacementHash = 'MINIFYCSS' . md5($_SERVER['REQUEST_TIME']);
        $this->_placeholders = array();
        
        $css = preg_replace_callback('~(".*"|\'.*\')~U', array($this, '_removeQuotesCB'), $css);
        
        $css = str_replace("\r\n", "\n", $css);
        
        // preserve empty comment after '>'
        // http://www.webdevout.net/css-hacks#in_css-selectors
        $css = preg_replace('@>/\\*\\s*\\*/@', '>/*keep*/', $css);
        
        // preserve empty comment between property and value
        // http://css-discuss.incutio.com/?page=BoxModelHack
        $css = preg_replace('@/\\*\\s*\\*/\\s*:@', '/*keep*/:', $css);
        $css = preg_replace('@:\\s*/\\*\\s*\\*/@', ':/*keep*/', $css);
        
        // apply callback to all valid comments (and strip out surrounding ws
        $css = preg_replace_callback('@\\s*/\\*([\\s\\S]*?)\\*/\\s*@'
            ,array($this, '_commentCB'), $css);

        // remove ws around { } and last semicolon in declaration block
        $css = preg_replace('/\\s*{\\s*/', '{', $css);
        $css = preg_replace('/;?\\s*}\\s*/', '}', $css);
        
        // remove ws surrounding semicolons
        $css = preg_replace('/\\s*;\\s*/', ';', $css);
        
        // remove ws around urls
        $css = preg_replace('/
                url\\(      # url(
                \\s*
                ([^\\)]+?)  # 1 = the URL (really just a bunch of non right parenthesis)
                \\s*
                \\)         # )
            /x', 'url($1)', $css);
        
        // remove ws between rules and colons
        $css = preg_replace('/
                \\s*
                ([{;])              # 1 = beginning of block or rule separator 
                \\s*
                ([\\*_]?[\\w\\-]+)  # 2 = property (and maybe IE filter)
                \\s*
                :
                \\s*
                (\\b|[#\'"])        # 3 = first character of a value
            /x', '$1$2:$3', $css);
        // remove ws in selectors
        $css = preg_replace_callback('/
                (?:              # non-capture
                    \\s*
                    [^~>+,\\s]+  # selector part
                    \\s*
                    [,>+~]       # combinators
                )+
                \\s*
                [^~>+,\\s]+      # selector part
                {                # open declaration block
            /x'
            ,array($this, '_selectorsCB'), $css);
            
        // minimize hex colors
        $css = preg_replace('/([^=])#([a-f\\d])\\2([a-f\\d])\\3([a-f\\d])\\4([\\s;\\}])/i'
            , '$1#$2$3$4$5', $css);
        
        // remove spaces between font families
        $css = preg_replace_callback('/font-family:([^;}]+)([;}])/'
            ,array($this, '_fontFamilyCB'), $css);
        
        $css = preg_replace('/@import\\s+url/', '@import url', $css);
        
        // replace any ws involving newlines with a single newline
        $css = preg_replace('/[ \\t]*\\n+\\s*/', "\n", $css);
        
        // separate common descendent selectors w/ newlines (to limit line lengths)
        $css = preg_replace('/([\\w#\\.\\*]+)\\s+([\\w#\\.\\*]+){/', "$1\n$2{", $css);
        
        // Use newline after 1st numeric value (to limit line lengths).
        $css = preg_replace('/
            ((?:padding|margin|border|outline):\\d+(?:px|em)?) # 1 = prop : 1st numeric value
            \\s+
            /x'
            ,"$1\n", $css);
        
        // prevent triggering IE6 bug: http://www.crankygeek.com/ie6pebug/
        $css = preg_replace('/:first-l(etter|ine)\\{/', ':first-l$1 {', $css);
        // fill placeholders
        $css = str_replace(
            array_keys($this->_placeholders)
            ,array_values($this->_placeholders)
            ,$css
        );
            
        return trim($css);
	}
	
	/**
     * Replace what looks like a set of selectors  
     *
     * @param array $m regex matches
     * 
     * @return string
     */
    protected function _selectorsCB($m) {
        // remove ws around the combinators
        return preg_replace('/\\s*([,>+~])\\s*/', '$1', $m[0]);
    }
	
	/**
     * Process a comment and return a replacement
     * 
     * @param array $m regex matches
     * 
     * @return string
     */
    protected function _commentCB($m) {
        $hasSurroundingWs = (trim($m[0]) !== $m[1]);
        $m = $m[1]; 
        // $m is the comment content w/o the surrounding tokens, 
        // but the return value will replace the entire comment.
        if ($m === 'keep') {
            return '/**/';
        }
        if ($m === '" "') {
            // component of http://tantek.com/CSS/Examples/midpass.html
            return '/*" "*/';
        }
        if (preg_match('@";\\}\\s*\\}/\\*\\s+@', $m)) {
            // component of http://tantek.com/CSS/Examples/midpass.html
            return '/*";}}/* */';
        }
        if ($this->_inHack) {
            // inversion: feeding only to one browser
            if (preg_match('@
                    ^/               # comment started like /*/
                    \\s*
                    (\\S[\\s\\S]+?)  # has at least some non-ws content
                    \\s*
                    /\\*             # ends like /*/ or /**/
                @x', $m, $n)) {
                // end hack mode after this comment, but preserve the hack and comment content
                $this->_inHack = false;
                return "/*/{$n[1]}/**/";
            }
        }
        if (substr($m, -1) === '\\') { // comment ends like \*/
            // begin hack mode and preserve hack
            $this->_inHack = true;
            return '/*\\*/';
        }
        if ($m !== '' && $m[0] === '/') { // comment looks like /*/ foo */
            // begin hack mode and preserve hack
            $this->_inHack = true;
            return '/*/*/';
        }
        if ($this->_inHack) {
            // a regular comment ends hack mode but should be preserved
            $this->_inHack = false;
            return '/**/';
        }
        // Issue 107: if there's any surrounding whitespace, it may be important, so 
        // replace the comment with a single space
        return $hasSurroundingWs // remove all other comments
            ? ' '
            : '';
    }
    
	/**
     * Process a font-family listing and return a replacement
     * 
     * @param array $m regex matches
     * 
     * @return string   
     */
    protected function _fontFamilyCB($m)
    {
        $m[1] = preg_replace('/
                \\s*
                (
                    "[^"]+"      # 1 = family in double qutoes
                    |\'[^\']+\'  # or 1 = family in single quotes
                    |[\\w\\-]+   # or 1 = unquoted family
                )
                \\s*
            /x', '$1', $m[1]);
        return 'font-family:' . $m[1] . $m[2];
    }
    
    protected function _reservePlace($content) {
        $placeholder = '"' . $this->_replacementHash . count($this->_placeholders) . '"';
        $this->_placeholders[$placeholder] = $content;
        return $placeholder;
    }
    
    protected function _removeQuotesCB($m) {
        return $this->_reservePlace($m[1]);
    }
	
	/**
	 * fix url
	 * @param string $css
	 * @param string $docRoot
	 */
	protected function _fixUrl($css) {
		$css = preg_replace_callback('/url\\(\\s*([^\\)\\s]+)\\s*\\)/'
            ,array($this, '_processUriCB'), $css);
        return $css;
	}
	
	private function _processUriCB($m) {
		$quoteChar = ($m[1][0] === "'" || $m[1][0] === '"')
                ? $m[1][0]
                : '';
		$uri = ($quoteChar === '')
                ? $m[1]
                : substr($m[1], 1, strlen($m[1]) - 2);
       
		// analyze URI
		$uriRewrite	= new Ming_Document_Compressor_UriRewrite();				
        if ('/' !== $uri[0]                  // root-relative
            && false === strpos($uri, '//')  // protocol (non-data)
            && 0 !== strpos($uri, 'data:')   // data protocol
        ) {
            // URI is file-relative: rewrite depending on options            
            $uri = $uriRewrite->rewrite($uri, $this->_cssBaseDir, $this->_docRoot);
        }
                
        return "url({$quoteChar}../{$uri}{$quoteChar})";
    }
    
	/**
     * Rewrite a file relative URI as root relative
     *
     * <code>
     * Ming_Document_Compressor_Css::rewriteRelative(
     *       '../img/hello.gif'
     *     , '/home/user/www/css'  // path of CSS file
     *     , '/home/user/www'      // doc root
     * );
     * // returns '/img/hello.gif'
     * </code>
     * 
     * @param string $uri file relative URI
     * 
     * @param string $realCurrentDir realpath of the current file's directory.
     * 
     * @param string $realDocRoot realpath of the site document root.
     * 
     * @return string
     */
    public static function rewriteRelative($uri) {
    	$filePath = self::_realpath($uri);    	    	
    	$inSegs  = preg_split('!/!u', $uri);
    	$outSegs = array();
    	for ($i = 0, $size = sizeof($inSegs); $i < $size; ++$i) {
    		if ('' == $inSegs[$i] || '.' == $inSegs) {
    			continue;    			
    		}
    		
    		if ($inSegs[$i] != '..') {
    			$outSegs[] = $inSegs[$i];
    		}
    	}
    	return '../' .implode('/', $outSegs);    	
    }
	
	public function show() {
	}
	
	/**
	 * save css to file in temp directory
	 */
	public function save($output) {		
		$file = PUBLIC_DIR.'temp'.DS.$output;
		$css = sprintf("/** css minify temp file, generated by Ming_Document_Css, date: %s*/\n%s",
                      date('Y/m/d H:i:s'), $this->_css);
		file_put_contents($file, $css);
		chmod($file, 0777);				
	}
}