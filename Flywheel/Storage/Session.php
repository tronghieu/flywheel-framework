<?php
/**
 * Session
 * 
 * @author		Luu Trong Hieu <hieuluutrong@vccorp.vn>
 * @version		$Id: Session.php 16860 2011-10-28 03:23:05Z hieult $
 * @package		t90
 * @subpackage	Session
 *
 */
namespace Flywheel\Storage;
use Flywheel\Event\Event;
use Flywheel\Factory;
use Flywheel\Object;

class Session extends Object {

    protected $_state;
    /**
	 * Session started
	 * @var $_started boolean
	 */
	protected $_started = false;
	
	protected $_handler;
	protected $_config = array(
		'lifetime' => 3600,						
	);
	
	public function __construct($config) {
		/*if (session_id()) {
			session_unset();
			session_destroy();
		}*/
		$this->_config = array_merge($this->_config, $config);
		//ini_set('session.save_handler', 'files');
		//ini_set('session.use_trans_sid', '0');
		
		if (isset($this->_config['handler']) && $this->_config['handler']) {
			$handlerClass = '\\Flywheel\\Storage\\Handler\\' .$this->_config['handler'];
			unset($this->_config['handler']);
			$handler = new $handlerClass($this->_config);
			
			session_set_save_handler(
				array(&$handler, 'open'),
            	array(&$handler, 'close'),
            	array(&$handler, 'read'),
            	array(&$handler, 'write'),
            	array(&$handler, 'destroy'),
            	array(&$handler, 'gc')
			);
			
			$this->_handler = $handler;
		}
		
		if (isset($this->_config['name'])) {
			session_name($this->_config['name']);
		}
		
		ini_set('session.gc_maxlifetime', 	$this->_config['lifetime']);		
		
		//define the lifetime of the cookie
        if (isset($this->_config['cookie_ttl']) 
        	|| isset($this->_config['cookie_domain']) || isset($this->_config['cookie_path'])) {        	
            // cross subdomain validity is default behavior
            $ttl = (isset($this->_config['cookie_ttl'])) ? 
            				(int) $this->_config['cookie_ttl'] : 0;
            $domain = (isset($this->_config['cookie_domain'])) ? 
            				$this->_config['cookie_domain'] : '.'. Factory::getRouter()->getDomain();
            $path = (isset($this->_config['cookie_path'])) ? 
            				'/'.trim($this->_config['cookie_path'], '/').'/' : '/';
            session_set_cookie_params($ttl, $path, $domain);
        } else {
        	$cookie	=	session_get_cookie_params();        	
        	session_set_cookie_params($cookie['lifetime'], $cookie['path'], $cookie['domain']);
        }
        
		if (Factory::getRequest()->isSecure()) {
			ini_set('session.cookie_secure', true);        		
        }
		ini_set('session.use_only_cookies',	1);

        if (isset($handlerClass)) {
            $this->dispatch('onAfterInitSessionConfig', new Event($this, array('handler' => $handlerClass)));
        } else {
            $this->dispatch('onAfterInitSessionConfig', new Event($this, array('handler' => 'default')));
        }
	}
	
	/**
	 * Start session
	 */
	public function start() {
		if (true === $this->_started) {
			return false;
		}
		session_cache_limiter('none');
		$this->_started = session_start();

        $this->_state = 'active';

		// Send modified header for IE 6.0 Security Policy
		header('P3P: CP="NOI ADM DEV PSAi COM NAV OUR OTRo STP IND DEM"');
		
		if (isset($_SESSION['__flash/new__'])) {
			$_SESSION['__flash/old__'] = array_keys($_SESSION['__flash/new__']);				
		} else {
			$_SESSION['__flash/old__'] = array();			
		}
		
		$this->_setCounter();
		$this->_setTimers();
//		$this->_validate();
        $this->_afterStart();
		return $this->_started;
	}

    /**
     * @return mixed
     */
    public function getState() {
        return $this->_state;
    }

    protected function _afterStart() {
        //Delete 'old' flash data
        $this->_flashDataSweep();

        //mark all new flash data as old (data)
        $this->_flashDataMark();

        $this->dispatch('afterStartSession', new Event($this));
    }
	
	/**
	 * Get
	 * 
	 * @param string $name session param name
	 * @param mixed	$default default value if session data is not set
	 * 
	 * @return mixed value of session data	 
	 */
	public function get($name, $default = null) {
        if ($this->_state !== 'active' && $this->_state !== 'expired') {
            // @TODO :: generated error here
            return null;
        }

		$deep 	= explode('\\', $name);
		$data	= isset($_SESSION)?$_SESSION:array();
		$size	= sizeof($deep);
		$i 		= 0;
		do {			
			if (!isset($data[$deep[$i]])) {
				return $default;
			}
			$data	= $data[$deep[$i]];
			$i++;
		} while ($i < $size);

		return $data;
	}
	
	/**
	 * Has
	 * 
	 * @param string $name session param name
     * @return bool|null
     */
	public function has($name) {
        if ($this->_state !== 'active') {
            // @TODO :: generated error here
            return null;
        }
		return isset($_SESSION[$name]);
	}
	
	/**
	 * Set
	 * 
	 * @param string $name session param name
	 * @param mixed $data
     * @return null
     */
	public function set($name, $data) {
        if ($this->_state !== 'active') {
            // @TODO :: generated error here
            return null;
        }

		$_SESSION[$name] = $data;
	}
	
	/**
	 * Remove
	 *
	 * @param string $name
	 * @return void
	 */
	public function remove($name) {
        if ($this->_state !== 'active') {
            // @TODO :: generated error here
            return null;
        }
		unset($_SESSION[$name]);
	}
	
	/**
	 * set counter of session
	 * 
	 * @return boolean true on success
	 */
	protected function _setCounter() {
		$counter	= $this->get('session.counter', 0);
		++$counter;

		$this->set('session.counter', $counter);
		return true;
	}
	
	/**
	 * set timers of session
	 */
	protected function _setTimers() {
		if (!$this->has('session.timer.start')) {
			$start = time();
			$this->set('session.timer.start' , $start);
			$this->set('session.timer.last'  , $start);
			$this->set('session.timer.now'   , $start);
			return true;			
		} 
		
		$this->set('session.timer.last', $this->get('session.timer.now'));
		$this->set('session.timer.now', time());
		
		return true;
	}
	
	/**
	 * validate session
	 */
	protected function _validate() {
        $ip = $this->get('session.client.address');

        if ($ip === null) {
            $this->set('session.client.address', $_SERVER['REMOTE_ADDR']);
        } elseif ($_SERVER['REMOTE_ADDR'] !== $ip) {
            $this->_state = 'error';
            return false;
        }

        // Record proxy forwarded for in the session in case we need it later
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $this->set('session.client.forwarded', $_SERVER['HTTP_X_FORWARDED_FOR']);
        }

        return true;
	}

    public function id() {
        if ('active' !== $this->_state) {
            return null;
        }

        return session_id();
    }
	
	/**
	* Create a token-string
	*
	* @access protected
	* @param int $length lenght of string
	* @return string $id generated token
	*/
	public function createToken($length = 32) {
		$chars	=	'!@#$%^&*()0123456789abcdef';
		$max			=	strlen( $chars ) - 1;
		$token			=	'';
		$name 			=  session_name();
		for( $i = 0; $i < $length; ++$i ) {
			$token .=	$chars[ (rand( 0, $max )) ];
		}

		return sha1($token.$name.php_uname());
	}
	
	/**
	 * Get Token
	 *
	 * @param boolean $reset. default = false
	 * @return string
	 */
	public function getToken($reset = false) {
		if (false === ($this->has('token')) 
				|| (null === ($token = $this->get('token'))) 
				|| (true === $reset)) {
			$token = $this->createToken();
			$this->set('token', $token);
		}
		
		return $token;
	}	
	
	/**
	 * Get Session Data
	 *
	 * @param string $namespace
	 * @return array
	 */
	public function getSessionData($namespace = null) {
		if ($namespace === null) return $_SESSION;		
		if (isset($_SESSION[$namespace])) {
			return $_SESSION[$namespace];
		}
		
		return array();
	}

    /**
     * Set Flash
     *
     * @param mixed $data flash data
     * @param string $value
     * @internal param string $name
     */
	public function setFlash($data, $value = '') {
        if (is_string($data)) {
            $data = array($data => $value);
        }
        if (count($data) > 0) {
            foreach ($data as $k=>$v) {
                $this->set('__flash/new__'.$k, $v);
            }
        }
	}

    public function keepFlash($key) {
        if (is_array($key)) {
            foreach($key as $k) {
                $this->keepFlash($k);
            }
        } else {
            $this->set('__flash/new__'.$key, $this->get('__flash/old__' .$key));
        }
    }
	
	/**
	 * Get Flash
	 * @param string $name
     * @return array
	 */
	public function getFlash($name) {
		return $this->get('__flash/old__'.$name);
	}

    /**
     * Removes all flash data marked as 'old'
     *
     * @return	void
     */
    protected function _flashDataSweep() {
        if ($_SESSION) {

        }
        $data = array_keys($_SESSION);
        foreach ($data as $key) {
            if (strpos($key, '__flash/old__') === 0) {
                $this->remove($key);
            }
        }
    }

    /**
     * Identifies flash data as 'old' for removal
     * when _flash data_sweep() runs.
     *
     * @return	void
     */
    protected function _flashDataMark()
    {
        foreach ($_SESSION as $name => $value)
        {
            $parts = explode('__flash/new__', $name);

            if (count($parts) === 2)
            {
                $new_name = '__flash/old__'.$parts[1];
                $this->set($new_name, $value);
                $this->remove($name);
            }
        }
    }
	
	/**
	 * Create Session Id
	 *
	 * @return md5 string
	 */
	private function _createSessionId() {
		$id = 0;
		while (strlen($id) < 32)  {
			$id .= mt_rand(0, mt_getrandmax());
		}

		$id	= 'sse_' . md5(uniqid($id, true) .$_SERVER['SERVER_ADDR'] .$_SERVER['SERVER_NAME']);
		return $id;		
	}
	
	/**
	 * Close
	 */
	public function close() {
		if (isset($_SESSION['__flash/old__'])) {
			for ($i = 0, $size = sizeof($_SESSION['__flash/old__']); $i < $size; ++$i) {
				unset($_SESSION['__flash/new__'][$_SESSION['__flash/old__'][$i]]);
			}
		}
		session_write_close();
        $this->dispatch('onSessionWriteAndClose', new Event($this));
	}
	
	public function __destruct() {
		$this->close();
	}
}
