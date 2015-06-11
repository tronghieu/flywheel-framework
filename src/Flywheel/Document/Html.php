<?php
//require_once MING_DIR . 'Document'.DS .'Html' .DS .'OpenGraph.php';
/**
 * Ming Document Html
 * object manager html element and data
 * 		element:	header element, javascript, css stylesheet
 * 		data: widgets (block) and component data
 * 
 * @author		Luu Trong Hieu <hieuluutrong@vccorp.vn>
 * @version		$Id: Html.php 516 2010-11-21 11:20:55Z mylifeisskidrow@gmail.com $
 * @package		Ming
 * @subpackage	Document
 */
namespace Flywheel\Document;
use Flywheel\Config\ConfigHandler;
use Flywheel\Factory;
use Flywheel\Router\WebRouter;

class Html extends BaseDoc {
	const JQUERY_OPEN = "\njQuery(document).ready(function () {\n";
	const JQUERY_CLOSE = "\n});\n";
	const ZEPTO_OPEN = "\n$(function () {\n";
	const ZEPTO_CLOSE = "\n});\n";
	/**
	 * title of website
	 * @var string
	 */
	public $title = '';
	
	/**
	 * meta description
	 * @var string
	 */
	public $description;
	
	/**
	 * meta keyword
	 * @var string
	 */
	public $keyword;
	
	/**
	 * meta canonical url
	 * @var string
	 */
	public $canonicalUrl;
	
	/**
	 * orther meta information
	 * @var string
	 */
	public $meta;
	
	/**
	 * the base url of js file
	 * @var string
	 */
	public $jsBaseUrl;
	
	/**
	 * the base url of css file
	 * @var string
	 */
	public $cssBaseUrl;
	
	/**
	 * js base directory
	 * @var string
	 */
	public $jsBaseDir;
	
	/**
	 * css base directory
	 * @var string
	 */
	public $cssBaseDir;
	
	/**
	 * is use google analystic
	 * 
	 * @var boolean
	 */
	public $userGA = true;
    protected $_jsVar = array(
        'TOP' => array(),
        'BOTTOM' => array()
    );

    private $_baseUrl;
	
	private $_domain;
	
	private $_publicPath;

	private $_lang = 'vi-VN';
	
	private $_stylesheet = array();

	private $_cssText = '';
		
	private $_javascript = array();
	private $_jsSingleFile = array();
	private $_jsText = array();
	
	public $header = array();

	private $_buffer;
	
	private $_blocks = array();

    private $_mode = 1;

    const MODE_NORMAL = 1,
        MODE_ASSETS_END = 2;
	
	public function __construct() {
        /** @var WebRouter $router */
		$router = Factory::getRouter();
		$this->_baseUrl = $router->getBaseUrl();	
		$this->_domain = $router->getDomain();	
		$this->_publicPath = $router->getFrontControllerPath();
//		$this->openGraph = new \Flywheel\Document\Html\OpenGraph();
	}

    public function setMode($mode) {
        $this->_mode = $mode;
    }

    public function getMode() {
        return $this->_mode;
    }
	
	/**
	 * Get Base Url
	 *
	 * @return string
	 */
	public function getBaseUrl() {
		return $this->_baseUrl;
	}
	
	/**
	 * Get domain
	 * 
	 * @return string
	 */
	public function getDomain() {
		return $this->_domain;		
	}
	
	/**
	 * Get public path (included domain) of script file
	 * 
	 * @return string
	 */
	public function getPublicPath() {
		return $this->_publicPath;
	}
	
	/**
	 * Set buffer
	 * 	Set document include data
	 *
	 * @param string $content	data
	 * @param string $type		component|widgets
	 * @param string $name
	 * 
	 * @return void
	 */
	public function setBuffer($content, $type, $name = null) {
		if ($name != null) {			
			$this->_buffer[$type][$name] = $content;			
		}
		else {
			$this->_buffer[$type] = $content;
		}
	}
	
	/**
	 * Get Buffer
	 * 	get document include data
	 *
	 * @return array
	 */
	public function getBuffer() {
		return $this->_buffer;
	}
	
	/**
	 * set widgets to block
	 * 
	 * @param string	$pos
	 * @param int		$ordering
	 * @param string	$file
	 * @param string	$name
	 * @param string	$config
	 */
	public function setBlock($pos, $ordering = 0, $file, $name, $config = array()) {		
		if (!isset($this->_blocks[$pos])) {
			$this->_blocks[$pos] = array();
		}
		$this->_blocks[$pos][] = array ('file' => $file, 'name' => $name, 'config' => $config); 
	}
	
	/**
	 * render block by position
	 * @param string $position
	 * 
	 * @return string
	 */
	/*public function block($position) {
		if (!isset($this->_blocks[$position])) {
			return null;				
		}		
		$html = '';		
		for($i = 0, $size = sizeof($this->_blocks[$position]); $i < $size; ++$i) {
			$widgets = Ming_Factory::getModule($this->_blocks[$position][$i]['name'],
							$this->_blocks[$position][$i]['file'],
							$this->_blocks[$position][$i]['config']);
			if (false !== $widgets) {
				$html .= $widgets->render();
			}
		}
		
		return $html;
	}*/
	
	/**
	 * count widgets of postions
	 * 
	 * @param string	$condition
	 * 
	 * @return integer
	 */
	public function countModules($condition) {		
		$result = '';
        $words = explode(' ', $condition);
        for($i = 0; $i < count($words); $i+=2) {
            $postion	= strtolower($words[$i]);
            $words[$i]	= (isset($this->_blocks[$postion]) || !is_array($this->_blocks[$postion]))? 0 : sizeof($this->_blocks[$postion]);
        }
        
        $str = 'return '.implode(' ', $words).';';
        return eval($str);		
	}
	
	public function getComponentData() {
		return $this->_buffer['component'];
	}
	
	/**
	 * Add Stylesheet
	 *
	 * @param string $file. Link to file
	 * @param array $media. Stylesheet Media style
	 */
	public function addCss($file, $media = array()) {
		$this->_stylesheet[$file] = $media;
	}
	
	/**
	 * Add javascript file
	 * 
	 * @param string	$file
	 * @param string	$position the position of js file, support TOP|BOTTOM 
	 * @param array		$option
	 */
	public function addJs($file, $position = 'BOTTOM', $option = array()) {
        if (is_array($file)) {
            for ($i = 0, $size = sizeof($file); $i < $size; ++$i) {
                $this->addJs($file[$i], $position, $option);
            }
        } else {
            $position = strtoupper($position);
            $this->_javascript[$position][$file] = $option;
        }
	}

    public function addJsVar($name, $value, $position = 'TOP') {
        $position = strtoupper($position);
        $this->_jsVar[$position][$name] = $value;
    }
	
	/**
	 * Add single js file 
	 * js compiler except files
	 * 
	 * @param string	$file path to js file (relative path with jsBaseDir)
	 * @param string	$position js include file BOTTOM or TOP
	 * @param array		$option
	 */
	public function addSingleJsFile($file, $position = 'BOTTOM', $option = array()) {
		$position = strtoupper($position);
		$this->_jsSingleFile[$position][$file] = $option;	
	}
	
	/**
	 * add Js code
	 * @param string	$code js code
	 * @param string	$position TOP|BOTTOm
	 * @param string	$lib, js framework like 'jQuery', 'MooTool', 'Dojo' etc
	 */
	public function addJsCode($code, $position = 'BOTTOM', $lib = 'jQuery') {
		$position = strtoupper($position);
		$this->_jsText[$position][$lib][] = $code;
	}
	
	/**
	 * render css link
	 * 
	 * @return string
	 */
	public function css() {
		if (count($this->_stylesheet) === 0) {
			return null;
		}

        $cssv = ConfigHandler::get('css_version');
		if (ConfigHandler::get('compile_css')) {
			$compressor = new Ming_Document_Compressor_Css();
			$css = $compressor->process($this->_stylesheet, $cssv);
			$css = '<link rel="stylesheet" type="text/css" href="'
						. $this->_publicPath .'temp/' .$css
                        . '?v=' .$cssv .'" media="screen" />' ."\n";
			return $css;	
		} else {
			$css = '';
			foreach ($this->_stylesheet as $file=>$media) {
				$media = ((is_array($media))? implode(', ', $media) : 'screen') .'"';
				if (null != $media) {
					$media = 'screen';				
				}
                if (strpos($file, 'http') !== false) {
                    $css .= '<link rel="stylesheet" type="text/css" href="'
                        . $file .'?v=' .$cssv .'"'
                        .' media="' .$media .'" />' ."\n";
                } else {
                    $css .= '<link rel="stylesheet" type="text/css" href="'
                        . $this->cssBaseUrl .$file .'?v=' .$cssv .'"'
                        .' media="' .$media .'" />' ."\n";
                }
			}
			
			return $css;
		}
	}
	
	/**
	 * render js link
	 * @param string	$pos
     * @return string
     */
	public function js($pos = 'BOTTOM') {

        $jsv = ConfigHandler::get('js_version');
        if (null == $jsv) {
            $jsv = '1';
        }
		$pos = strtoupper($pos);

        if (!empty($this->_jsVar[$pos])) {
            foreach ($this->_jsVar[$pos] as $name => $value) {
                $code = "var {$name} = ".json_encode($value) .';';
                $this->addJsCode($code, $pos, 'standard');
            }
        }

		$jsCode = '';
		if (isset($this->_jsText[$pos])) {
			foreach ($this->_jsText[$pos] as $lib=>$code) {
				if ('standard' == $lib) {
					$jsCode .= implode("\n", $code) ."\n";				
				} else {
					$open = constant(strtoupper('self::' .$lib) .'_OPEN');
					$close = constant(strtoupper('self::' .$lib) .'_CLOSE');
					$jsCode .= $open .implode("\n", $code) .$close;
				}				
			}
			$jsCode = "<script type=\"text/javascript\">\n" .$jsCode ."\n</script>";						
		}		
		$js = '';
		if (isset($this->_javascript[$pos])) {
            foreach($this->_javascript[$pos] as $file=>$option) {
                if (strpos($file, 'http') !== false) {
                    $js .= '<script type="text/javascript" src="'
                        .$file .'?v=' .$jsv .'"></script>';
                } else {
					$js_base_url = (isset($option['base_url']) && $option['base_url'] != null)? $option['base_url'] : $this->jsBaseUrl;
                    $js .= '<script type="text/javascript" src="'
                        .$js_base_url .$file .'?v=' .$jsv .'"></script>';
                }
            }
		}
		
		if ('TOP' == $pos) {
			$js .= $jsCode;			
		} else {
			$js = $jsCode .$js;
		}
		
		//single file
		if (isset($this->_jsSingleFile[$pos])) {
			foreach ($this->_jsSingleFile[$pos] as $file=>$option) {
                $jsv = (isset($option['version']))? $option['version']: $jsv;
				$js .= '<script type="text/javascript" src="'
							.(isset($option['base_url'])? $option['base_url']: $this->jsBaseUrl) .
							$file.'?v=' .$jsv .'">
						</script>';
			}
		}
		
		return $js ."\n";
	}
	
	/**
	 * render open graph information
     * @return string
     */
	public function og() {		
		$og = $this->openGraph;
		if (null == $og->title) {
			$og->title = htmlspecialchars($this->title);
		}		
		
		if (null == $og->description) {
			$og->description = $this->description;			
		}
		return $og->toHtml();
	}
    
    public function disbursement($content) {
        $content = str_replace("<doc::css() />", $this->css(), $content);
        $content = str_replace("<doc::js('top') />", $this->js('top'), $content);
        $content = str_replace("<doc::js('bottom') />", $this->js('bottom'), $content);
        return $content;
    }
}