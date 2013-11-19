<?php
namespace Flywheel\Router;
use Flywheel\Exception\Routing;
use Flywheel\Factory;

class Collection
{
    public $hasHostInfo = false;
    public $initParameters = array();
    public $options = array();
    public $filter = array();
    public $matchValue;
    public $route;
    /**
     * @var array list of parameters (name=>regular expression)
     */
    public $params=array();

    /**
     * @var array the mapping from route param name to token name (e.g. _r1=><1>)
     */
    public $references = array();

    /**
     * @var boolean whether the URL allows additional parameters at the end of the path info.
     */
    public $append;

    /**
     * @var string template used to construct a URL
     */
    public $template;

    /**
     * @var string regular expression used to parse a URL
     */
    public $pattern;

    /**
     * @var string the pattern used to match route
     */
    public $routePattern;

    function __construct($config, $pattern) {
        if (is_string($config)) {
            $config = array('route' => $config);
        }

        if (!isset($config['route']))
            throw new Routing("Router Collection: missing \"route\" parameter for {$pattern}");

        $this->route = trim($config['route'], '/');
        $tr2['/']=$tr['/']='\\/';

        if (isset($config['filter']))
            $this->filter = array_merge($this->filter, $config['filter']);

        if (isset($config['params']))
            $this->initParameters = array_merge($this->initParameters, $config['params']);

        if (isset($config['options']))
            $this->options = array_merge($this->options, $config['options']);
        // && preg_match_all('/{(\w+)}/', $pattern, $matches)

        if (strpos($this->route,'{')!==false && preg_match_all('/{(\w+)}/', $this->route, $matches2)) {
            foreach($matches2[1] as $name)
                $this->references[$name] = '{' .$name .'}';
        }

        if (isset($this->filter['method']) && null != $this->filter['method'])
            $this->filter['method'] = preg_split('/[\s,]+/',strtoupper($this->filter['method']),-1,PREG_SPLIT_NO_EMPTY);

        if (preg_match_all('/{(\w+):?(.*?)?}/', $pattern, $matches)) {
            $tokens = array_combine($matches[1], $matches[2]);
            foreach ($tokens as $name=>$value) {
                if ('' == $value)
                    $value = '\w+';
                $tr['{' .$name .'}'] = '(?P<' .$name .'>' .$value .')';
                if (isset($this->references[$name]))
                    $tr2['{' .$name .'}'] = $tr['{' .$name .'}'];
                else
                    $this->params[$name] = $value;
            }
        }
        $p = rtrim($pattern, '*');
        $this->append=$p!==$pattern;
        $p = trim($p, '/');
        $this->template = preg_replace('/{(\w+):?.*?}/', '{$1}', $p);
        $this->pattern = '/^' .strtr($this->template, $tr) .'\/';
        if ($this->append){
            $this->pattern .=  '/u';
        } else {
            $this->pattern .= '$/u';
        }

        if (is_array($this->references) && sizeof($this->references) > 0){
            $this->routePattern = '/^' .strtr($this->route, $tr2) .'$/u';
        }
    }

    /**
     * Creates a URL based on this rule.
     * @param WebRouter $router the manager
     * @param string $route the route
     * @param array $params list of parameters
     * @param string $ampersand the token separating name-value pairs in the URL.
     * @return mixed the constructed URL or false on error
     */
    public function createUrl($router,$route,$params,$ampersand) {

        $tr=array();
        if($route !== $this->route) {
            if($this->routePattern!==null && preg_match($this->routePattern,$route,$matches)) {
                foreach($this->references as $key=>$name) {
                    $tr[$name]=$matches[$key];
                }
            } else {
                return false;
            }
        }

        foreach($this->initParameters as $key => $value) {
            if(isset($params[$key])) {
                if($params[$key] == $value) {
                    unset($params[$key]);
                } else if(preg_match('/\A'.$value.'\z/u', $params[$key])) {
//                    $tr['{' .$key .'}']=urlencode($params[$key]);
                    $tr['{' .$key .'}'] = $params[$key];
                    unset($params[$key]);
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        foreach($this->params as $key=>$value) {
            if(!isset($params[$key])){
                return false;
            }
        }

        //check matching parameter
        foreach($this->params as $key=>$value) {
            if(!preg_match('/\A'.$value.'\z/u', $params[$key])) {
                return false;
            }
        }


        foreach($this->params as $key => $value) {
//            $tr['{' .$key .'}']=urlencode($params[$key]);
            $tr['{' .$key .'}'] = $params[$key];
            unset($params[$key]);
        }

        if (isset($this->options['urlSuffix']) && null != ($this->options['urlSuffix'])) {
            $suffix = $this->options['urlSuffix'];
        } else {
            $suffix = '';
        }

        $url = strtr($this->template, $tr);

        if($this->hasHostInfo) {
            $hostInfo = Factory::getRequest()->getHostInfo();
            if(stripos($url,$hostInfo)===0) {
                $url=substr($url,strlen($hostInfo));
            }
        }

        if(empty($params)) {
            return ('' !== $url)? $url.$suffix : $url;
        }

        if($this->append) {
            $url.='/'.$router->createPathInfo($params,'/','/').$suffix;
        } else {
            if($url!=='') {
                $url.=$suffix;
            }
            $url.='?'.$router->createPathInfo($params,'=',$ampersand);
        }

        return $url;
    }

    /**
     * Parses a URL based on this rule.
     * @param WebRouter $router the URL manager
     * @param string $pathInfo path info part of the URL
     * @param string $rawPathInfo path info that contains the potential URL suffix
     * @return mixed the route that consists of the controller ID and action ID or false on error
     */
    public function parseUrl($router,$pathInfo,$rawPathInfo) {
        $request = Factory::getRequest();

        if (isset($this->filter['method']) && is_array($this->filter['method'])
            && !in_array($request->getMethod(), $this->filter['method'], true))
            return false;

        if(isset($this->options['urlSuffix']) && null !== $this->options['urlSuffix']){
            $pathInfo = $router->removeUrlSuffix($rawPathInfo,$this->options['urlSuffix']);
        }

        $pathInfo.='/';

        if(preg_match($this->pattern,$pathInfo,$matches)){
            foreach($this->initParameters as $name=>$value){
                if(!isset($_GET[$name])) {
                    $_REQUEST[$name] = $_GET[$name] = $value;
                }
            }

            $tr = array();
            foreach($matches as $key=>$value){
                if(isset($this->references[$key])){
                    $tr[$this->references[$key]]=$value;
                }else if(isset($this->params[$key])){
                    $router->params[$key] = $_GET[$key] = $value;
                }
            }

            if($pathInfo!==$matches[0]){
                $router->parsePathInfo(ltrim(substr($pathInfo,strlen($matches[0])),'/'));
            }

            if($this->routePattern!==null){
                return strtr($this->route,$tr);
            }else{
                return $this->route;
            }
        }
        else{
            return false;
        }
    }
}
