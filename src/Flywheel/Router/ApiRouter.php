<?php
namespace Flywheel\Router;
use Flywheel\Base;
use Flywheel\Exception\Api as ApiException;
use Flywheel\Util\Inflection;
use Flywheel\Factory;
use Flywheel\Config\ConfigHandler as ConfigHandler;
class ApiRouter extends BaseRouter
{
    protected $_format = 'json';
    protected $_api;
    protected $_method;
    protected $_path;
    protected $_routes = array();

    /**
     * get api response format (json|xml|text)
     *
     * @return string
     */
    public function getFormat() {
        return $this->_format;
    }

    /**
     * get API method
     *
     * @return mixed
     */
    public function getMethod() {
        return $this->_method;
    }

    /**
     * get Api controller
     *
     * @return mixed
     */
    public function getApi() {
        return $this->_api;
    }

    /**
     * get full api path
     *
     * @return mixed
     * @deprecated from version 1.1
     */
    public function getPath() {
        return $this->_path;
    }

    public function parseUrl($url) {
        $url = trim($url, '/');
        if (null == $url) {
            throw new ApiException('Invalid request !', 404);
        }

        $segment = explode('/', $url);
        $_cf = explode('.', end($segment)); //check define format
        if (isset($_cf[1])) {
            $this->_format = $_cf[1];
            $segment[count($segment) - 1] = $_cf[0];
        }

        $size = sizeof($segment);
        $router = array();
        for ($i = 0; $i < $size; ++$i) {
            $router[$i] = Inflection::camelize($segment[$i]);
        }

        for ($i = $size - 1; $i >= 0; --$i) {
            $router = array_slice($router, 0, $i+1);
            $_camelName = implode("\\", $router);
            $_path = implode(DIRECTORY_SEPARATOR, $router);
            if (false !== file_exists($file = Base::getAppPath().'/Controller/' .$_path .'.php')) {
                $this->_api = trim ($_camelName, "\\");
                break;
            }
        }

        if (null == $this->_api) {
            throw new ApiException('API not found', 404);
        }

        $segment = array_slice($segment, $i+1);

        if (!empty($segment)) {
            $this->_method = array_shift($segment);
            $this->_params = (!empty($segment))? $segment: array();
        }
    }
}