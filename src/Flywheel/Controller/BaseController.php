<?php
namespace Flywheel\Controller;
use Flywheel\Factory;
use Flywheel\Object;

abstract class BaseController extends Object
{
    protected $_name;
    protected $_path;

    public function __construct($name, $path) {
        $this->_name = $name;
        $this->_path = $path;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getPath()
    {
        return $this->_path;
    }

    /**
     * @return \Flywheel\Http\WebRequest | \Flywheel\Http\RESTfulRequest
     */
    public function request() {
        return Factory::getRequest();
    }

    /**
     * @return \Flywheel\Http\WebResponse | \Flywheel\Http\RESTfulResponse
     */
    public function response() {
        return Factory::getResponse();
    }

    public function filter() {}

    /**
     * /**
     * Returns the named POST parameter value.
     * If the POST parameter does not exist, the second parameter to this method will be returned.
     * @param string $name the POST parameter name
     * @param string $type filter type (INT, UINT, FLOAT, BOOLEAN, WORD, ALNUM, CMD, BASE64, STRING, ARRAY, PATH, NONE)
     * @param mixed $default the default parameter value if the POST parameter does not exist.
     * @return array|bool|float|int|null|string
     */
    public function post($name, $type = 'STRING', $default = null) {
        return $this->request()->post($name, $type, $default);
    }

    /**
     * Returns the named GET parameter value.
     * If the GET parameter does not exist, the second parameter to this method will be returned.
     * @param string $name the GET parameter name
     * @param string $type filter type (INT, UINT, FLOAT, BOOLEAN, WORD, ALNUM, CMD, BASE64, STRING, ARRAY, PATH, NONE)
     * @param mixed $default the default parameter value if the GET parameter does not exist.
     * @return mixed the GET parameter value
     */
    public function get($name, $type = 'STRING', $default = null) {
        return $this->request()->get($name, $type, $default);
    }

    /**
     * Returns the named PUT parameter value.
     * If the PUT parameter does not exist or if the current request is not a PUT request,
     * the second parameter to this method will be returned.
     * If the PUT request was tunneled through POST via _method parameter, the POST parameter
     * will be returned instead
     * @param string $name the PUT parameter name
     * @param string $type filter type (INT, UINT, FLOAT, BOOLEAN, WORD, ALNUM, CMD, BASE64, STRING, ARRAY, PATH, NONE)
     * @param mixed $default the default parameter value if the PUT parameter does not exist.
     * @return mixed the PUT parameter value
     */
    public function put($name, $type = 'STRING', $default = null) {
        return $this->request()->put($name, $type, $default);
    }

    /**
     * Returns the named DELETE parameter value.
     * If the DELETE parameter does not exist or if the current request is not a DELETE request,
     * the second parameter to this method will be returned.
     * If the DELETE request was tunneled through POST via _method parameter, the POST parameter
     * will be returned instead
     * @param string $name the DELETE parameter name
     * @param string $type filter type (INT, UINT, FLOAT, BOOLEAN, WORD, ALNUM, CMD, BASE64, STRING, ARRAY, PATH, NONE)
     * @param mixed $default the default parameter value if the DELETE parameter does not exist.
     * @return mixed the DELETE parameter value
     */
    public function delete($name, $type = 'STRING', $default =null) {
        return $this->request()->delete($name, $type, $default);
    }
}
