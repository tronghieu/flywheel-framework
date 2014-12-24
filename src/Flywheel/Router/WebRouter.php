<?php
namespace Flywheel\Router;
use Flywheel\Config\ConfigHandler as ConfigHandler;
use Flywheel\Base;
use Flywheel\Event\Event;
use Flywheel\Exception\Routing;
use Flywheel\Loader;
use Flywheel\Util\Inflection;

class WebRouter extends BaseRouter {
    /**
     * @var Collection[]
     */
    protected $_collectors = array();
    /**
     * @var
     * @deprecated from version 1.1
     */
    protected $_controllerPath;

    protected $_camelControllerName;
    protected $_controller;
    protected $_action;
    protected $_route;

    public $params = array();

    public function __construct() {
        parent::__construct();
    }

    public function init($config = null) {
        if (null == $config) {
            $routes = ConfigHandler::load('app.Config.routing', 'routing', true);
        } else {
            $routes = $config;
        }

        unset($routes['__urlSuffix__']);
        unset($routes['__remap__']);

        if($routes and !empty($routes)){
            foreach ($routes as $pattern => $config){
                $this->addCollection(new Collection($config, $pattern));
            }
        }

        parent::init($config);
    }

    /**
     * add router's collection
     *
     * @param Collection $collection
     */
    public function addCollection(Collection $collection) {
        $this->_collectors[] = $collection;
    }

    /**
     * get Route config
     *
     * @return mixed
     */
    public function getRoute() {
        return $this->_route;
    }

    public function getPathInfo() {
        $pathInfo = parent::getPathInfo();
        if (ConfigHandler::has('__urlSuffix__', 'routing')
            && '' != ($suffix = ConfigHandler::get('routing.__urlSuffix__')))
            $pathInfo = str_replace($suffix, '', $pathInfo);
        return $pathInfo;
    }

    /**
     * Get Controller
     *
     * @deprecated from version 1.1, will be removed from 1.2, use @$this::getCamelControllerName() instead
     * @return string
     */
    public function getController() {
        return $this->getCamelControllerName();
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
     * @deprecated from version 1.1, will be removed from 1.2 always return null
     */
    public function getControllerPath() {
        return null;
        //return $this->_controllerPath;
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
        return ConfigHandler::get('routing./.route');
    }

    private function _parseControllers($route) {
        if (false === is_array($route)) {
            $route = explode('/', $route);
        }
        $_path = '';
        $_camelName = '';

        $size = sizeof($route);
        for ($i = 0; $i < $size; ++$i) {
            $route[$i] = Inflection::camelize($route[$i]);
        }

        for ($i = $size - 1; $i >= 0; --$i) {
            $_camelName = implode("\\", array_slice($route, 0, $i+1));
            $_path = implode(DIRECTORY_SEPARATOR, array_slice($route, 0, $i+1));
            if (false !== file_exists($file = Base::getAppPath().'/Controller/' .$_path .'.php')) {
                $this->_camelControllerName = trim ($_camelName, "\\");
                break;
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
     * @param bool|string $absolute if you want return absolute url
     * @return string the constructed URL
     */
    public function createUrl($route, $params=array(), $ampersand='&', $absolute=true) {

        $anchor = '';
        $ampersand='&';
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
            return $this->createUrlDefault('', $params, $ampersand, $absolute);
        }

        for ($i = sizeof($this->_collectors)-1; $i >= 0; $i--) {

            if (($url = $this->_collectors[$i]->createUrl($this, $route, $params, $ampersand, $absolute)) !== false) {

                if ($this->_collectors[$i]->hasHostInfo) {
                    return ('' == $url)? '/' .$anchor : $url.$anchor;
                } else {
                    if( $absolute ) {
                        return rtrim($this->getBaseUrl(), '/') .'/' .$url .$anchor;
                    } else {
                        return $url . $anchor;
                    }
                }
            }
        }


        return $this->createUrlDefault($route,$params,$ampersand,$absolute).$anchor;
    }

    protected function _createFromDefaultController($route) {
        $routeCfg = ConfigHandler::get('routing./');
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
    protected function createUrlDefault($route,$params,$ampersand,$absolute) {
        if( $absolute ) {
            $url = rtrim($this->getBaseUrl(), '/') .(('' != $route)? '/' .$route : '');
        } else {
            $url = (('' != $route)? '/' .$route : '');
        }

        if ('' !== ($query = $this->createPathInfo($params,'=',$ampersand))) {
            $url .= '?' .$query;
        }

        return $url;
    }


    /**
     * @param $url
     *
     * @return mixed|void
     * @throws Routing
     */
    public function parseUrl($url) {
        $this->dispatch('onBeginWebRouterParsingUrl', new Event($this));
        $config = ConfigHandler::get('routing');
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
        $seek++;
        if (count($segment) > $seek) {
            $this->_action = $segment[$seek];
            $seek++;
            $this->params = array_slice($segment, $seek);
        }

        if (null == $this->_action) {
            $this->_action = 'default';
        }

        $this->dispatch('onAfterWebRouterParsingUrl', new Event($this));
    }
}