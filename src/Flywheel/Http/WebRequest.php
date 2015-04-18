<?php
namespace Flywheel\Http;
use Flywheel\Base;
use Flywheel\Factory;

class WebRequest extends Request
{
    /**
     * @var boolean whether cookies should be validated to ensure they are not tampered. Defaults to false.
     */
    public $enableCookieValidation=false;

    /**
     * @var array the property values (in name-value pairs) used to initialize the CSRF cookie.
     * Any property of {@link CHttpCookie} may be initialized.
     * This property is effective only when {@link enableCsrfValidation} is true.
     */
    public $csrfCookie;

    protected $_csrfToken;

    public function init() {}

    /**
     * Returns true if the request is a XMLHttpRequest.
     *
     * It works if your JavaScript library set an X-Requested-With HTTP header.
     * Works with Prototype, Mootools, jQuery, and perhaps others.
     *
     * @return bool true if the request is an XMLHttpRequest, false otherwise
     */
    public function isXmlHttpRequest() {
        return ($this->getHttpHeader('X_REQUESTED_WITH') == 'XMLHttpRequest');
    }

    /**
     * Returns information about the capabilities of user browser.
     * @param string $userAgent the user agent to be analyzed. Defaults to null, meaning using the
     * current User-Agent HTTP header information.
     * @return array user browser capabilities.
     * @see http://www.php.net/manual/en/function.get-browser.php
     */
    public function getBrowser($userAgent=null)
    {
        return get_browser($userAgent,true);
    }

    /**
     * Returns the content type of the current request.
     * @param  Boolean $trim If false the full Content-Type header will be returned
     * @return string
     */
    public function getContentType($trim = true)
    {
        $contentType = $this->getHttpHeader('Content-Type', null);
        if ($trim && false !== $pos = strpos($contentType, ';'))
            $contentType = substr($contentType, 0, $pos);
        return $contentType;
    }

    /**
     * @return string
     */
    protected function _generateCsrfToken() {
        return md5(uniqid() .mt_rand());
    }

    /**
     * @param bool $autoGen set auto generate token if not exist
     * @return mixed|string
     */
    public function getCsrfToken($autoGen = true) {
        $cookie = Factory::getCookie();
        $token = $cookie->readSecure('csrf');
        if (null == $token && $autoGen) {
            $token = $this->_generateCsrfToken();
            $cookie->writeSecure('csrf', $token, 7200);
        }

        return $token;
    }

    /**
     * Performs the CSRF validation.
     * The default implementation will compare the CSRF token obtained
     * from a cookie and from a POST field. If they are different, a CSRF attack is detected.
     */
    public function validateCsrfToken() {
        if (($this->isPostRequest() || $this->isPutRequest() || $this->isDeleteRequest()) && !$this->isXmlHttpRequest()) {
            $cookie = Factory::getCookie();
            $token = $this->getCsrfToken(false);
            $method = $this->getMethod();
            if (!$token) {
                return false;
            }

            $user_token_value = false;

            switch($method) {
                case 'POST':
                    $user_token_value = $this->post($token, 'BOOLEAN', false);
                    break;
                case 'PUT':
                    $user_token_value=$this->put($token, 'BOOLEAN', false);
                    break;
                case 'DELETE':
                    $user_token_value=$this->delete($token, 'BOOLEAN', false);
            }

            return (true === $user_token_value);
        }

        return true;
    }

    /**
     * check csrf token
     *
     * @param $token
     * @return bool
     */
    public function checkCsrfToken($token) {
        if (null == $token) {
            return false;
        }

        return $token === $this->getCsrfToken(false);
    }

    /**
     * Redirects the browser to the specified URL.
     * @param string $url URL to be redirected to. If the URL is a relative one, the base URL of
     * the application will be inserted at the beginning.
     * @param int $code the HTTP status code. Defaults to 302. See {@link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html}
     * @param bool $end whether to terminate the current application
     */
    public static function redirect($url, $code = 302, $end = true, $absolute = true) {
        $baseUrl = Factory::getRouter()->getBaseUrl();
        $domain = Factory::getRouter()->getDomain();

        if (strpos($url, 'http') !== 0) { //not entire url
            $baseUrl = Factory::getRouter()->getBaseUrl();
            if (false === strpos($baseUrl, '.php')) {
                $baseUrl = rtrim($baseUrl, '/') .'/';
            }
            $url = $baseUrl .ltrim($url,'/');
        }

        header('Location: '.$url, true, $code);
        if (true == $end)
            Base::end();
    }

    /**
     * get browser request agent
     *
     * @return string
     */
    public static function getBrowserAgent() {
        return (isset($_SERVER['HTTP_USER_AGENT']))? $_SERVER['HTTP_USER_AGENT'] : 'UNKNOWN';
    }
}
