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
        $this->_path = Base::getAppPath() .'/controllers/';
        while(!empty($segment)) {
            $seg = array_shift($segment);
            if (is_dir($this->_path.$seg)) {
                $this->_path .= $seg.DIRECTORY_SEPARATOR;
            } elseif (is_file($this->_path.($api = Inflection::camelize($seg)).'Controller.php')) {
                $this->_api = $api;
                break;
            }
        }
        if (null == $this->_api)
            throw new ApiException('API not found', 404);

        if (!empty($segment)) {
            $this->_method = array_shift($segment);
            $this->_params = (!empty($segment))? $segment: array();
        }
    }
}