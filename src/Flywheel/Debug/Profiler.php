<?php
/**
 * Ming Debug Profiler
 * 
 * @author		Luu Trong Hieu <hieuluutrong@vccorp.vn>
 * @version		$Id: Profiler.php 17396 2011-11-08 09:54:05Z hieult $
 * @package		Ming
 * @subpackage	Debug
 * @copyright	VCCorp (c) 2010 
 *
 */
namespace Flywheel\Debug;
use Flywheel\Config\ConfigHandler;
use Flywheel\Event\Event;
use Flywheel\Object;

class Profiler extends Object {
	private $_start;
	
	private $_buffer = array();

    private $_pevTime = 0.0;

    private $_pevMem = 0.0;

    private $_sqlLog = array();

    /** @var IHandler[] */
    private $_handlers = [];

    private static $_init = false;
	
	private function __construct() {
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->_start = $_SERVER['REQUEST_TIME_FLOAT'];
        } else {
            $this->_start = $_SERVER['REQUEST_TIME'];
        }
	}

    /**
     * @param $handler
     * @param array $otp
     */
    public function addHandler($handler, $otp = []) {
        $obj = new $handler($otp);
        if ($obj instanceof IHandler) {
            $this->_handlers[$obj->getName()] = $obj;
        } else {
            /**
             * @FIXME need error log or better report
             */
        }
    }

    /**
     * @param $handler
     */
    public function removeHandler($handler) {
        unset($this->_handlers[$handler]);
    }

    /**
     * @return IHandler[]
     */
    public function getHandlers() {
        return $this->_handlers;
    }

    /**
     * Init handling system event message
     */
    public static function init() {
        if (self::$_init) {
            return;
        }

        self::getInstance();
        self::getEventDispatcher()->addListener('onBeginRequest', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onBeginWebRouterParsingUrl', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onAfterWebRouterParsingUrl', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onAfterInitSessionConfig', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onBeginControllerExecute', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onBeforeControllerRender', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onAfterControllerRender', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onAfterControllerExecute', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('afterCreateMasterConnection', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('afterCreateSlaveConnection', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onAfterSendHttpHeader', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onAfterSendContent', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::getEventDispatcher()->addListener('onEndRequest', array('\Flywheel\Debug\Profiler', 'handleSystemEvent'));
        self::$_init = true;
    }

    /**
     * Handling system event
     * @param Event $event
     * @return array|null
     */
    public static function handleSystemEvent(Event $event) {
        $package = (isset($event->sender) && is_object($event->sender))?  get_class($event->sender): null;
        $label = $event->getName();
        if ($event->getName() == 'onBeginControllerExecute' || $event->getName() == 'onAfterControllerExecute') {
            $package .= '.' .$event->params['action'];
        } else if ($event->getName() == 'onAfterInitSessionConfig') {
            $label .= '. Handler:' .$event->params['handler'];
        } else if ($event->getName() == 'afterCreateSlaveConnection' || $event->getName() == 'afterCreateMasterConnection') {
            $label .= '. Connection name: ' .$event->params['connection_name'];
        }
        return self::mark($label, $package);
    }
	
	/**
	 * Get Instance
	 * 
	 * @static 
	 * @return Profiler
	 */
	public static function getInstance() {
		static $instance;
		if ($instance == null) {
			$instance = new self();
		}
		
		return $instance;
	}

    /**
     * @param $label
     * @param null $package
     * @return array|null
     */
    public static function mark($label, $package = null) {
        if (ConfigHandler::get('debug')) {
            return self::getInstance()->_mark($label, $package);
        }
        return null;
	}

    /**
     * @param $label
     * @param $package
     * @return array
     */
    private function _mark($label, $package) {
        $current = microtime(true) - $this->_start;
        $currentMem = memory_get_usage() / 1048576;
        $mark = array(
            'label' => "{$label}: {$package}",
            'microtime' => microtime(true),
            'time' => microtime(true) - $this->_start,
            'next_time' => $current- $this->_pevTime,
            'memory' => $currentMem,
            'next_memory' => $currentMem - $this->_pevMem,
            'memory_get_usage'      => memory_get_usage(),
            'memory_get_peak_usage' => memory_get_peak_usage(),
        );
        $this->_pevTime = $current;
        $this->_pevMem = $currentMem;

        $this->_buffer[] = $mark;

        return $mark;
    }
	
	/**
	 * Get Memory Usage
	 *
	 * @return float MB
	 */
	public function getMemUsage() {
		$mem = sprintf('%0.3f', memory_get_usage() / 1048576 );
		return $mem;
	}

    /**
     * Get Buffer
     *
     * @return array
     */
    public function getBuffer() {
        return $this->_buffer;
    }

    /**
     * @param $query
     * @param $begin
     * @param $end
     * @param array $params
     */
    public static function logSqlQueries($query, $begin, $end, $params = array()) {
        if (!ConfigHandler::get('debug')) {
            return ;
        }

        self::getInstance()->logQueries($query, $begin, $end, $params);
    }

    /**
     * @param $query
     * @param $begin
     * @param $end
     * @param array $params
     */
    public function logQueries($query, $begin, $end, $params = array()) {
        $this->_sqlLog[] = array(
            'query' => $query,
            'begin' => $begin,
            'end' => $end,
            'params' => $params
        );
    }

    /**
     * @return array
     */
    public function getProfileData() {
        if (!ConfigHandler::get('debug')) {
            return [];
        }

        $data = array();
        $data['SERVER_ADDRESS'] = $_SERVER['SERVER_ADDR'];

        $memory = [];
        $memory['max_memory_allow'] = (float) ini_get('memory_limit');
        $memory['memory_usage'] = memory_get_peak_usage(true) / 1048576;
        $memory['memory_usage_percent'] = $memory['memory_usage']/($memory['max_memory_allow'])*100;
        $data['memory'] = $memory;

        $data['total_exec_time'] = round($this->_pevTime, 3);

        $data['argv'] = [];
        $data['argc'] = [];

        if (isset($argv)) {
            $data['argv'] = $argv;
        }

        if (isset($argc)) {
            $data['argc'] = $argc;
        }

        if (isset($_COOKIE)) {
            $data['cookies'] = $_COOKIE;
        }

        if (isset($_SESSION)) {
            $data['session'] = $_SESSION;
        }

        if (isset($_REQUEST)) {
            $data['requests'] = $_REQUEST;
        }

        $activities = [];
        $buffers = $this->getBuffer();
        //serialize to string
        foreach ($buffers as $buffer) {
            $mark = sprintf(
                "%s\n%.3f seconds (+%.3f); %0.2f MB (%s%0.3f). Peak:%.3f MB\n",
                $buffer['label'],
                $buffer['time'],
                $buffer['next_time'],
                $buffer['memory'],
                (($buffer['next_memory'] > 0) ? '+' : '-' . $buffer['next_memory']),
                $buffer['next_memory'],
                $buffer['memory_get_peak_usage'] / 1048576
            );
            $activities[] = $mark;
        }
        $data['activities'] = $activities;

        $sql_queries = [];
        $total_queries = 0;
        $totalExecuteTime = 0;
        $total_memories = 0;
        foreach($this->_sqlLog as $sql) {
            if ($sql['begin'] && $sql['end']) {
                $time = $sql['end']['microtime'] - $sql['begin']['microtime'];
                $memory = ($sql['end']['memory_get_peak_usage'] - $sql['begin']['memory_get_peak_usage']) / 1048576;
                $totalExecuteTime += $time;
                $total_memories +=  $memory;
            } else {
                $time = 0;
                $memory = 0;
            }

            if (isset($sql['query'])) {
                $total_queries++;
            }

            $t = [];
            $t['query'] = $total_queries .'. ' .$sql['query'];
            $t['parameters'] = $sql['params'];
            $t['exec_time'] = ($time < 0.001)? '~0.001' : round($time, 3);
            $t['memory'] = (($memory < 0)? '-' : '+') . round($memory, 3);
            $sql_queries[] = $t;
        }
        $data['sql_queries'] = [
            'total_queries' => $total_queries,
            'total_exec_time' => round($total_memories, 3),
            'queries' => $sql_queries,
        ];

        $data['included_files'] = get_included_files();
        return $data;
    }

    public static function write() {
        if (!ConfigHandler::get('debug')) {
            return ;
        }

        $profiler = self::getInstance();
        $handlers = $profiler->getHandlers();
        foreach($handlers as $handler) {
            $handler->write($profiler->getProfileData());
        }
    }
}