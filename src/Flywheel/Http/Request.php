<?php
namespace Flywheel\Http;
use Flywheel\Filter\Input;
abstract class Request {
    const POST = 'POST', GET = 'GET', PUT = 'PUT', DELETE = 'DELETE', HEAD = 'HEAD';
    /**
     * handler request method
     * @var string
     */
    protected $_method;

    protected $_requestUri;
    protected $_pathInfo;
    protected $_scriptFile;
    protected $_scriptUrl;
    protected $_hostInfo;
    protected $_baseUrl;
    protected $_cookies;
    protected $_preferredLanguage;
    protected $_deleteParams;
    protected $_putParams;
    protected $_restParams;
    protected $_securePort;
    protected $_port;

    protected $_cleaned = array(
        'GET' => array(),
        'POST' => array(),
        'PUT' => array(),
        'DELETE' => array(),
        'REQUEST' => array(),
    );

    public function __construct() {
        $this->_method = isset($_SERVER['REQUEST_METHOD'])?
            $_SERVER['REQUEST_METHOD'] : 'GET';
        self::_normalizeRequest();
        $this->init();
    }

    public function init(){}

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * Returns the named GET or POST parameter value.
     * If the GET or POST parameter does not exist, the second parameter to this method will be returned.
     * If both GET and POST contains such a named parameter, the GET parameter takes precedence.
     * @param string $name the GET parameter name
     * @param mixed $default the default parameter value if the GET parameter does not exist.
     * @return mixed the GET parameter value
     */
    public function getParam($name, $default = null)
    {
        return isset($_GET[$name])? $_GET[$name] : (isset($_POST[$name])) ? $_POST[$name] : $default;
    }

    /**
     * Returns the named GET parameter value.
     * If the GET parameter does not exist, the second parameter to this method will be returned.
     * @param string $name the GET parameter name
     * @param string $type filter type (INT, UINT, FLOAT, BOOLEAN, WORD, ALNUM, CMD, BASE64, STRING, ARRAY, PATH, NONE)
     * @param mixed $default the default parameter value if the GET parameter does not exist.
     * @return mixed the GET parameter value
     */
    public function get($name, $type = 'STRING', $default = null)
    {
        if (!isset($_GET[$name]))
            return $default;

        if (!isset($this->_cleaned['GET'][$name])) {
            $this->_cleaned['GET'][$name] = Input::clean($_GET[$name], $type);
        }
        return $this->_cleaned['GET'][$name];
    }

    /**
     * Returns the named REQUEST parameter value.
     * If the REQUEST parameter does not exist, the second parameter to this method will be returned.
     * @param string $name the REQUEST parameter name
     * @param string $type filter type (INT, UINT, FLOAT, BOOLEAN, WORD, ALNUM, CMD, BASE64, STRING, ARRAY, PATH, NONE)
     * @param mixed $default the default parameter value if the REQUEST parameter does not exist.
     * @return mixed the REQUEST parameter value
     */
    public function request($name, $type = 'STRING', $default = null)
    {
        if (!isset($_REQUEST[$name]))
            return $default;

        if (!isset($this->_cleaned['REQUEST'][$name])) {
            $this->_cleaned['REQUEST'][$name] = Input::clean($_REQUEST[$name], $type);
        }
        return $this->_cleaned['REQUEST'][$name];
    }

    /**
     * /**
     * Returns the named POST parameter value.
     * If the POST parameter does not exist, the second parameter to this method will be returned.
     * @param string $name the POST parameter name
     * @param string $type filter type (INT, UINT, FLOAT, BOOLEAN, WORD, ALNUM, CMD, BASE64, STRING, ARRAY, PATH, NONE)
     * @param mixed $default the default parameter value if the POST parameter does not exist.
     * @return array|bool|float|int|null|string
     */
    public function post($name, $type = 'STRING', $default = null)
    {
        if (!isset($_POST[$name]))
            return $default;

        if (!isset($this->_cleaned['POST'][$name])) {
            $this->_cleaned['POST'][$name] = Input::clean($_POST[$name], $type);
        }
        return $this->_cleaned['POST'][$name];
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
    public function put($name, $type = 'STRING', $default=null)
    {
        if (!isset($this->_cleaned['PUT'][$name])) {
            if($this->_getIsPutViaPostRequest())
                return $this->post($name, $type, $default);

            if($this->_putParams===null)
                $this->_putParams = $this->isPutRequest() ? $this->getRestParams() : array();

            if (!isset($this->_putParams[$name]))
                return $default;

            $this->_cleaned['PUT'][$name] = Input::clean($this->_putParams[$name], $type);
        }
        return $this->_cleaned['PUT'][$name];
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
    public function delete($name, $type = 'STRING', $default =null)
    {
        if (!isset($this->_cleaned['DELETE'][$name])) {
            if($this->_getIsDeleteViaPostRequest())
                return $this->post($name, $type, $default);

            if($this->_deleteParams===null)
                $this->_deleteParams=$this->isDeleteRequest() ? $this->getRestParams() : array();

            if(!isset($this->_deleteParams[$name]))
                return $default;

            $this->_cleaned['DELETE'][$name] = Input::clean($this->_deleteParams[$name], $type);
        }
        return $this->_cleaned['DELETE'][$name];
    }

    /**
     * Returns whether this is a POST request.
     * @return boolean whether this is a POST request.
     */
    public function isPostRequest()
    {
        return isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'],'POST');
    }

    /**
     * Returns whether this is a PUT request.
     * @return boolean whether this is a PUT request.
     */
    public function isPutRequest()
    {
        return (isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'],'PUT')) || $this->_getIsPutViaPostRequest();
    }

    /**
     * Returns whether this is a PUT request which was tunneled through POST.
     * @return boolean whether this is a PUT request tunneled through POST.
     */
    protected function _getIsPutViaPostRequest()
    {
        return isset($_POST['_method']) && !strcasecmp($_POST['_method'],'PUT');
    }

    /**
     * Returns whether this is a DELETE request.
     * @return boolean whether this is a DELETE request.
     */
    public function isDeleteRequest()
    {
        return (isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'],'DELETE')) || $this->_getIsDeleteViaPostRequest();
    }

    /**
     * Returns whether this is a DELETE request which was tunneled through POST.
     * @return boolean whether this is a DELETE request tunneled through POST.
     */
    protected function _getIsDeleteViaPostRequest()
    {
        return isset($_POST['_method']) && !strcasecmp($_POST['_method'],'DELETE');
    }

    /**
     * Returns the PUT or DELETE request parameters.
     * @return array the request parameters
     */
    public function getRestParams()
    {
        if (null == $this->_restParams) {
            $this->_restParams=array();
            if(function_exists('mb_parse_str'))
                mb_parse_str(file_get_contents('php://input'), $this->_restParams);
            else
                parse_str(file_get_contents('php://input'), $this->_restParams);
        }
        return $this->_restParams;
    }

    /**
     * Returns whether this is an Adobe Flash or Adobe Flex request.
     * @return boolean whether this is an Adobe Flash or Adobe Flex request.
     * @since 1.1.11
     */
    public function isFlashRequest()
    {
        return isset($_SERVER['HTTP_USER_AGENT'])
            && (stripos($_SERVER['HTTP_USER_AGENT'],'Shockwave')!==false
                || stripos($_SERVER['HTTP_USER_AGENT'],'Flash')!==false);
    }

    protected static function _normalizeRequest() {
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            if (isset($_GET))
                $_GET = self::stripSlashes($_GET);
            if (isset($_POST))
                $_POST = self::stripSlashes($_POST);
            if (isset($_REQUEST))
                $_REQUEST = self::stripSlashes($_REQUEST);
            if (isset($_COOKIE))
                $_COOKIE = self::stripSlashes($_COOKIE);
        }
    }

    /**
     * Returns true if the current request is secure (HTTPS protocol).
     *
     * @return boolean
     */
    public function isSecure() {
        return (
            (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == 1))
                ||
                (isset($_SERVER['HTTP_SSL_HTTPS']) && (strtolower($_SERVER['HTTP_SSL_HTTPS']) == 'on' || $_SERVER['HTTP_SSL_HTTPS'] == 1))
                ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
        );
    }

    /**
     * Returns the schema and host part of the application URL.
     * The returned URL does not have an ending slash.
     * By default this is determined based on the user request information.
     * You may explicitly specify it by setting the {@link setHostInfo hostInfo} property.
     * @param string $schema schema to use (e.g. http, https). If empty, the schema used for the current request will be used.
     * @return string schema and hostname part (with port number if needed) of the request URL (e.g. http://www.domain.com)
     * @see setHostInfo
     */
    public function getHostInfo($schema='') {
        $secure = $this->isSecure();
        if(null == $this->_hostInfo) {
            $http = ($secure)? 'https' : 'http';

            if(isset($_SERVER['HTTP_HOST'])) {
                $this->_hostInfo = $http.'://'.$_SERVER['HTTP_HOST'];
            } else {
                $this->_hostInfo=$http.'://'.$_SERVER['SERVER_NAME'];
                $port = ($secure)? $this->getSecurePort() : $this->getPort();
                if(($port!==80 && !$secure) || ($port!==443 && $secure)) {
                    $this->_hostInfo .= ':'.$port;
                }
            }
        }

        if('' !== $schema) {
            if($secure && $schema==='https' || !$secure && $schema==='http')
            {
                return $this->_hostInfo;
            }

            $port = $schema === 'https' ? $this->getSecurePort() : $this->getPort();
            if($port!==80 && $schema==='http' || $port!==443 && $schema==='https') {
                $port=':'.$port;
            } else {
                $port='';
            }

            $pos=strpos($this->_hostInfo,':');
            return $schema.substr($this->_hostInfo, $pos, strcspn($this->_hostInfo,':',$pos+1)+1) .$port;
        } else {
            return $this->_hostInfo;
        }
    }

    /**
     * Returns the port to use for insecure requests.
     * Defaults to 80, or the port specified by the server if the current
     * request is insecure.
     * You may explicitly specify it by setting the {@link setPort port} property.
     * @return integer port number for insecure requests.
     */
    public function getPort() {
        if(null == $this->_port) {
            $this->_port=!$this->isSecure() && isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 80;
        }
        return $this->_port;
    }

    /**
     * Returns the port to use for secure requests.
     * Defaults to 443, or the port specified by the server if the current
     * request is secure.
     * You may explicitly specify it by setting the {@link setSecurePort securePort} property.
     * @return integer port number for secure requests.
     */
    public function getSecurePort() {
        if(null == $this->_securePort) {
            $this->_securePort=$this->isSecure() && isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 443;
        }
        return $this->_securePort;
    }

    /**
     * Returns Uri prefix, including protocol, hostname and server port.
     *
     * @return string Uniform resource identifier prefix
     */
    public function getUriPrefix() {
        if ($this->isSecure()) {
            $standardPort = '443';
            $protocol = 'https';
        } else {
            $standardPort = '80';
            $protocol = 'http';
        }

        $host = explode(':', $_SERVER['HTTP_HOST']);
        if (count($host) == 1) {
            $host[] = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '';
        }

        if ($host[1] == $standardPort || empty($host[1])) {
            unset($host[1]);
        }

        return $protocol.'://'.implode(':', $host);
    }

    /**
     * See if the client is using absolute uri
     *
     * @return boolean true, if is absolute uri otherwise false
     */
    public function isAbsUri() {
        return isset($_SERVER['REQUEST_URI']) ? preg_match('/^http/', $_SERVER['REQUEST_URI']) : false;
    }

    /**
     * Retrieves the uniform resource identifier for the current web request.
     *
     * @return string Unified resource identifier
     */
    public function getUri() {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        return $this->isAbsUri()? $uri : $this->getUriPrefix().$uri;
    }

    /**
     * @param $name
     * @param string $prefix
     * @return mixed|null
     */
    public function getHttpHeader($name, $prefix = 'http') {
        if ($prefix) {
            $prefix = strtoupper($prefix).'_';
        }

        $name = $prefix.strtoupper(strtr($name, '-', '_'));
        return isset($_SERVER[$name]) ? self::stripSlashes($_SERVER[$name]) : null;
    }

    /**
     * Strip Slashes
     *
     * @param mixed $data 		Data to be processed
     * @return mixed 			Processed data
     */
    public static function stripSlashes(&$data) {
        return is_array($data)? array_map(array(self,'stripSlashes'),$data) : stripslashes($data);
    }



    /**
     * Get request client ip
     *
     * @return string
     */
    public static function getClientIp() {
        if (getenv('HTTP_CLIENT_IP')) {
            $ipAddress = getenv('HTTP_CLIENT_IP');
        }
        else if(getenv('HTTP_X_FORWARDED_FOR')) {
            $ipAddress = getenv('HTTP_X_FORWARDED_FOR');
        }
        else if(getenv('HTTP_X_FORWARDED')) {
            $ipAddress = getenv('HTTP_X_FORWARDED');
        }
        else if(getenv('HTTP_FORWARDED_FOR')) {
            $ipAddress = getenv('HTTP_FORWARDED_FOR');
        }
        else if(getenv('HTTP_FORWARDED')) {
            $ipAddress = getenv('HTTP_FORWARDED');
        }
        else if(getenv('REMOTE_ADDR')) {
            $ipAddress = getenv('REMOTE_ADDR');
        }
        else {
            $ipAddress = 'UNKNOWN';
        }

        return $ipAddress;
    }
}
