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
	
	private function __construct() {
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->_start = $_SERVER['REQUEST_TIME_FLOAT'];
        } else {
            $this->_start = $_SERVER['REQUEST_TIME'];
        }
	}

    public static function init() {
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
    }

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
	
	public static function mark($label, $package = null) {
        if (ConfigHandler::get('debug')) {
            return self::getInstance()->_mark($label, $package);
        }
        return null;
	}

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

    public static function logSqlQueries($query, $begin, $end, $params = array()) {
        if (!ConfigHandler::get('debug')) {
            return ;
        }

        self::getInstance()->logQueries($query, $begin, $end, $params);
    }

    public function logQueries($query, $begin, $end, $params = array()) {
        $this->_sqlLog[] = array(
            'query' => $query,
            'begin' => $begin,
            'end' => $end,
            'params' => $params
        );
    }

    public function getProfileData() {
        if (!ConfigHandler::get('debug')) {
            return ;
        }
        $data = array();

        $data[] = "TIME: " .date('Y-m-d H:i:s');
        $data[] = "SERVER ADDRESS: " .$_SERVER['SERVER_ADDR'];

        $maxMemAllow = (float) ini_get('memory_limit');
        $data[] = "Max memory allow: " .$maxMemAllow ." MB";
        $data[] = "Memory Usage: " .(memory_get_usage(true) / 1048576) ."MB (" . (memory_get_usage(true) / ($maxMemAllow*1048576) * 100) ."%)";
        $data[] = sprintf("\nTotal execute time: %.3f seconds" ,self::getInstance()->_pevTime);

        if (isset($argv)) {
            $data[] = "argv:" .var_export($argv, true);
        }

        if (isset($argc)) {
            $data[] = "argc:" .var_export($argc, true);
        }

        if (isset($_COOKIE)) {
            $data[] = "\nCOOKIES: " .var_export($_COOKIE, true);
        }

        if (isset($_SESSION)) {
            $data[] = "\nSESSION: " .var_export($_SESSION, true);
        }

        if (isset($_REQUEST)) {
            $data[] = "\nREQUEST: " .var_export($_REQUEST, true);
        }

        $data[] = "ACTIVITIES";

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
            $data[] = $mark;
        }

        $data[] = "SQL QUERIES";

        $totalQueries = 0;
        $totalExecuteTime = 0;
        $totalMemories = 0;
        foreach($this->_sqlLog as $sql) {
            if ($sql['begin'] && $sql['end']) {
                $time = $sql['end']['microtime'] - $sql['begin']['microtime'];
                $memory = ($sql['end']['memory_get_usage'] - $sql['begin']['memory_get_usage']) / 1048576;
                $totalExecuteTime += $time;
                $totalMemories +=  $memory;
            } else {
                $time = 0;
                $memory = 0;
            }

            if (isset($sql['query'])) {
                $totalQueries++;
            }
            $data[] = $totalQueries .'. ' .$sql['query'];
            if (!empty($sql['params'])) {
                $data[] = "\n\tParameters:" .json_encode($sql['params']);
            }

            $data[] = "\n\tExec time: " .(($time < 0.001)? '~0.001' : round($time, 3)) .' seconds.'
                . " Memory: " .(($memory < 0)? '-' : '+') .$memory ."MB.\n";
        }

        $data[] = $totalQueries .' queries, take ' .round($totalExecuteTime, 3) .' seconds and ' .$totalMemories ."MB.\n";
        $data[] = sizeof(get_included_files()) ." files included";
        return $data;
    }

    public function writePlainText($path = null) {
        if (!ConfigHandler::get('debug')) {
            return ;
        }

        if (null == $path) {
            $path = RUNTIME_PATH .'/log';
        }
        @mkdir($path, 777);
        if(!($id = session_id())) {
            $id = md5(uniqid() .mt_rand());
        }
        $filename = date('Y-m-d').'.' .$id .'.profile';

        $log = "\n\n**************************";
        $log .= implode("\n", $this->getProfileData());

        @file_put_contents($path.'/'.$filename, $log, FILE_APPEND);
    }
}