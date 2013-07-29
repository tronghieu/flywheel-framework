<?php
namespace Flywheel\Router;
use Flywheel\Config\ConfigHandler as ConfigHandler;
use Flywheel\Base;
use Flywheel\Exception\Routing;
use Flywheel\Util\Inflection;

class WebRouter extends BaseRouter
{
    /**
     * @var Collection[]
     */
    protected $_collectors = array();
    protected $_controllerPath;
    protected $_camelControllerName;
    protected $_controller;
    protected $_action;
    protected $_route;

    public $params = array();

    public function __construct() {
        $routes = ConfigHandler::load('app.config.routing', 'routing', true);
//        print_r($routes);exit;
        unset($routes['__urlSuffix__']);
        unset($routes['__remap__']);
//        unset($routes['/']);

        if($routes and !empty($routes)){
            foreach ($routes as $pattern => $config){
                $this->_collectors[] = new Collection($config, $pattern);
            }
        }
        parent::__construct();
    }

    public function getRoute() {
        return $this->_route;
    }


    public function getPathInfo() {
        $pathInfo = parent::getPathInfo();
        if (ConfigHandler::has('__urlSuffix__', 'routing')
            && '' != ($suffix = ConfigHandler::get('__urlSuffix__', 'routing')))
            $pathInfo = str_replace($suffix, '', $pathInfo);
        return $pathInfo;
    }

    /**
     * Get Controller
     * 	ten controllers
     *
     * @return string
     */
    public function getController() {
        return $this->_controller;
    }

    /**
     * get controllers name after camelize
     *
     * @return string
     */
    public function getCamelControllerName() {
        return $this->_camelControllerName;
    }

    /**
     * get path of request controllers
     *
     * @return string
     */
    public function getControllerPath() {
        return $this->_controllerPath;
    }

    /**
     * Get Method
     *
     * @return string
     */
    public function getAction() {
        return $this->_action;
    }

    private function _parseDefaultController() {
        return ConfigHandler::get('/.route', 'routing');
    }

    private function _parseControllers($route) {
        if (false === is_array($route)) {
            $route = explode('/', $route);
        }
        $_path = '';
        for ($i = 0; $i < sizeof($route); ++$i) {
            $_camelName	= Inflection::camelize($route[$i]);

            $_path .= $_camelName .DIRECTORY_SEPARATOR;
            if (false === (file_exists(Base::getAppPath().'/controllers/' .$_path))) {
                break;
            } else {
                $this->_camelControllerName = $_camelName;
                $this->_controllerPath		= $_path;
                $this->_controller = $route[$i];
            }
        }
        return $i;
    }

    /**
     * Constructs a URL.
     * @param string $route the controller and the action (e.g. article/read)
     * @param array $params list of GET parameters (name=>value). Both the name and value will be URL-encoded.
     * If the name is '#', the corresponding value will be treated as an anchor
     * and will be appended at the end of the URL.
     * @param string $ampersand the token separating name-value pairs in the URL. Defaults to '&'.
     * @return string the constructed URL
     */
    public function createUrl($route,$params=array(),$ampersand='&') {
        $anchor = '';

        foreach($params as $i=>$param) {
            if($param===null) $params[$i]='';
        }

        if(isset($params['#'])) {
            $anchor='#'.$params['#'];
            unset($params['#']);
        } else {
            $anchor='';
        }

        $route=trim($route,'/');

        if ('/' == ($url = $this->_createFromDefaultController($route))) {
            return $this->createUrlDefault('', $params, $ampersand);
        }

        for ($i = sizeof($this->_collectors)-1; $i >= 0; $i--) {
            if (($url = $this->_collectors[$i]->createUrl($this, $route, $params, $ampersand)) !== false) {
                if ($this->_collectors[$i]->hasHostInfo) {
                    return ('' == $url)? '/' .$anchor : $url.$anchor;
                } else {
                    return rtrim($this->getBaseUrl(), '/') .'/' .$url .$anchor;
                }
            }
        }


        return $this->createUrlDefault($route,$params,$ampersand).$anchor;
    }

    protected function _createFromDefaultController($route) {
        $routeCfg = ConfigHandler::get('/', 'routing');
        if ($route == $routeCfg['route']) {
            return '/';
        }

        return false;
    }

    /**
     * Creates a URL based on default settings.
     * @param string $route the controller and the action (e.g. article/read)
     * @param array $params list of GET parameters
     * @param string $ampersand the token separating name-value pairs in the URL.
     * @return string the constructed URL
     */
    protected function createUrlDefault($route,$params,$ampersand) {
        $url = rtrim($this->getBaseUrl(), '/') .(('' != $route)? '/' .$route : '');

        if ('' !== ($query = $this->createPathInfo($params,'=',$ampersand))) {
            $url .= '?' .$query;
        }

        return $url;
    }


    /**
     * @param $url
     * @throws Routing
     */
    public function parseUrl($url) {

        $config = ConfigHandler::get('routing', false);
        $rawUrl = $url;

        $url = $this->removeUrlSuffix($url, isset($config['__urlSuffix__'])? $config['__urlSuffix__']: null);

        if ('/' == $url) {
            if (!isset($config['/'])) { //default
                throw new Routing('Router: Not found default "/" in config. Default must be set!');
            }
            $route = $this->_parseDefaultController();
        } else {
            for ($i = sizeof($this->_collectors)-1; $i >= 0; --$i) {
                if(false !== ($route = $this->_collectors[$i]->parseUrl($this, trim($url, '/'), $rawUrl))) {
                    break;
                }

            }
            if (false == $route) {
                $route = trim($url, '/');
            }
        }

        $this->_route = $route;

        $segment = explode('/', $route);

        $seek = $this->_parseControllers($route);

        if (count($segment) > $seek) {
            $this->_action = $segment[$seek];
            $seek++;
            $this->params = array_slice($segment, $seek);
        }

        if (null == $this->_action) {
            $this->_action = 'default';
        }
    }
}