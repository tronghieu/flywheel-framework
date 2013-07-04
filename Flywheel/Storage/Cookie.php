<?php
/**
 * Cookie Handler
 * 	cookie's management, support secure write and read
 *
 * @author Luu Trong Hieu <tronghieu1012@yahoo.com>
 * @version	$Id$
 * @package	t90
 * @subpackage Cookie
 */
namespace Flywheel\Storage;
use \Flywheel\Filter\Input as Input;
class Cookie {
    private $_secret = '';
    private $_exception = false;
    private $_basename = false;

    public function __construct($config) {
        $this->_secret		= $config['cookie_secret'];
        $this->_exception	= (boolean) $config['cookie_exception'];
        if (isset($config['cookie_basename']) && null != $config['cookie_basename']) {
            $this->_basename = $config['cookie_basename'];
        }
    }

    public function getName($name) {
        if (false == $this->_basename) {
            return $name;
        }

        return hash('crc32b', $name .$this->_basename);
    }

    /**
     * write the given cookie
     * @link http://www.php.net/manual/en/function.setcookie.php
     *
     * @param string	$name
     * @param mixed		$value
     * @param integer	$expire	expire unix timestamp
     * @param string	$path	cookie path
     * @param string	$domain	cookie domain
     *
     * @return boolean
     */
    public function write($name, $value, $expire = 2592000, $path = '/', $domain = null) {
        $liveTime = time() + $expire;
        if (null == $domain) {
            $cookieInfo = session_get_cookie_params();
            if (!empty($cookieInfo['domain'])) {
                $domain = $cookieInfo['domain'];
            }
        }
        return setcookie($this->getName($name), $value, $liveTime, $path, $domain);
    }

    /**
     * read
     * get the value of the cookie with the given name in format $type,
     * else return $default
     *
     * @param string $name
     * @param int|string $type \Flywheel\Filter\Input::TYPE_* @see \Flywheel\Filter\Input
     * @param mixed $default
     *
     * @return mixed
     */
    public function read($name, $type = 'STRING', $default = null) {
        $name = $this->getName($name);
        if (!isset($_COOKIE[$name]))
            return $default;
        return Input::clean($_COOKIE[$name], $type);
    }

    /**
     * write cookie secure
     *
     * Sign and timestamp a cookie so it cannot be forged using cookie_secret in session.ini.
     * Secure cookie must read by Cookie::readSecure() method
     *
     * @param string	$name the cookie name
     * @param mixed		$value the cookie value
     * @param integer	$expires
     * @param string	$path
     * @param string	$domain
     *
     * @return boolean
     */
    public function writeSecure($name, $value, $expires = 2592000, $path='/', $domain = null) {
        return $this->write($this->getName($name), $this->_createSignedValue($name, $value), $expires, $path, $domain);
    }

    /**
     * read secure cookie
     * return the given signed cookie if it validated.
     * if cookie_exception turn on @throw new Exception when timestamp and signature not valid
     *
     * @param string $name
     * @param int|string $type
     * @param mixed $default
     *
     * @throws Exception
     * @return mixed the cookie value
     */
    public function readSecure($name, $type = 'STRING', $default = null) {
        $value = $this->read($this->getName($name), $type, $default);

        if ($value == $default) {
            return $default;
        }

        $value = explode('|', $value);
        if (sizeof($value) != 3) {
            return null;
        }

        $signature = $this->_cookieSignature($name, $value[0], $value[1]);
        //check cookie timestamp
        if ($value[1] > time()) {
            if (true == $this->_exception) {
                throw new Exception('Invalid cookie timestamp!');
            }
            return null;
        }

        //check cookie signature
        if(!$this->_timeIndependentEquals($value[2], $signature)) {
            if (true == $this->_exception) {
                throw new Exception('Invalid cookie timestamp!');
            }
            return null;
        }

        return base64_decode($value[0]);
    }

    /**
     * kill cookie
     *
     * @param string	$name
     * @param string	$path
     * @param string	$domain
     *
     * @return boolean
     */
    public function clear($name, $path="/", $domain = null) {
        return $this->write($this->getName($name), null, time() - 1000, $path, $domain);
    }

    /**
     * clear all cookie
     */
    public function clearAll() {
        foreach($_COOKIE as $cookie=>$value) {
            $this->clear($cookie);
        }
    }

    private function _createSignedValue($name, $value) {
        $value	= base64_encode($value);
        $time	= time();
        $signature = $this->_cookieSignature($name, $value, $time);
        $value	= implode('|', array($value, $time, $signature));
        return $value;
    }

    private function _cookieSignature($name, $value, $time) {
        $data	= $name .'&' .$value .'&' .$time;
        $result = base64_encode(hash_hmac('sha1', $data, $this->_secret, true));
        return $result;
    }

    private function _timeIndependentEquals($a, $b) {
        if (strlen($a) != strlen($b)) {
            return false;
        }

        $result = 0;
        $zipped = array_zip((array)$a, (array)$b);
        for ($i = 0, $size = sizeof($zipped); $i < $size; ++$i) {
            $result |= ord($zipped[$i][0]) ^ ord($zipped[$i][0]);
        }

        return $result == 0;
    }
}