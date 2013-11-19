<?php
namespace Flywheel\Application;
use Flywheel\Base;
use Flywheel\Debug\Profiler;
use Flywheel\Event\Event;
use Flywheel\Factory;
use Flywheel\Config\ConfigHandler as ConfigHandler;
use Flywheel\Exception\Api as ApiException;
class ApiApp extends BaseApp
{
    private $_requestMethod;
    private $_format = 'json';
    public static $FORMAT_SUPPORT = array('json', 'xml', 'text');
    public static $RESTfulREQUEST = array('GET', 'POST', 'PUT', 'DELETE');

    /**
     * get response format
     *
     * @return string
     */
    public function getFormat() {
        return $this->_format;
    }

    /**
     * set response format
     * 	format allow "json", "xml" or "text", default is json
     *
     *
     * @throws \Flywheel\Exception\Api
     */
    public function setFormat($format) {
        if (in_array($format, self::$FORMAT_SUPPORT)) {
            $this->_format = $format;
            return $format;
        }

        throw new ApiException('Application: Invalid API response format', null, 400);
    }

    protected function _init() {
        ini_set('display_errors', (ConfigHandler::get('debug') || Base::getEnv() == Base::ENV_DEV)? 'on' : 'off');

        //Error reporting
        if (Base::getEnv() == Base::ENV_DEV) {
            error_reporting(E_ALL);
        }
        else if (Base::getEnv() == Base::ENV_TEST) {
            error_reporting(E_ALL ^ E_NOTICE);
        }

        //set timezone
        if (ConfigHandler::has('timezone'))
            date_default_timezone_set(ConfigHandler::get('timezone'));
        else
            date_default_timezone_set(@date_default_timezone_get());

        $this->_requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);
        if (!in_array($this->_requestMethod, self::$RESTfulREQUEST)) {
            throw new ApiException('Application: Request method unsupported!', null, 400);
        }
    }

    protected function _loadMethod() {
        /* @var \Flywheel\Router\ApiRouter $router */
        $router = Factory::getRouter();
        $name = $router->getApi();
        $apiMethod = $router->getMethod();
        $apiPath = $router->getPath();
        $class = $name.'Controller';
        if (!file_exists($file = $apiPath .$class.'.php')) {
            throw new ApiException("Api's method {$class}/{$apiMethod} not found!", 404);
        }

        require_once $file;
        $this->_controller = new $class($name, $apiPath);
        return $this->_controller->execute($this->_requestMethod, $apiMethod);
    }

    /**
     * Run
     * @throws \Flywheel\Exception\Api
     */
    public function run() {
        $this->beforeRun();
        $this->getEventDispatcher()->dispatch('onBeginRequest', new Event($this));
        $content = $this->_loadMethod();
        $response = Factory::getResponse();
        $response->setBody($content);
        $response->send();
        $this->getEventDispatcher()->dispatch('onEndRequest', new Event($this));
        $this->afterRun();
    }
}
