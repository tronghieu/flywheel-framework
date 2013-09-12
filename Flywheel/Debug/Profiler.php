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
            'label' => "{$label}:{$package}",
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

    public function writePlainText($path = null) {
        if (null == $path) {
            $path = RUNTIME_PATH .'/log';
        }
        @mkdir($path, 777);
        if(!($id = session_id())) {
            session_start();
            $id = session_id();
        }
        $filename = date('Y-M-d').'.' .$id .'.profile';

        $log = "\n\nPROFILE INFO:" .date('Y-M-d H:i');
        $log .= "\nServer Address:{$_SERVER['SERVER_ADDR']}" ;
		$log .= sprintf("\nTotal Memory Usage: %0.3f", (memory_get_usage(true) / ini_get('memory_limit')) * 100);
        $log .= sprintf("\nTotal execute time: %.3f seconds" ,self::getInstance()->_pevTime);

        $log .= "\nSERVER ENVIRONMENT:\n";
        foreach($_SERVER as $server => $value) {
            $log .= sprintf("%s: %s\n", $server, $value);
        }

        if (isset($argv)) {
            $log .= "argv:" .var_export($argv, true);
        }

        if (isset($argc)) {
            $log .= "argc:" .var_export($argc, true);
        }

        if (isset($_COOKIE)) {
            $log .= "\nCOOKIES:" .var_export($_COOKIE);
        }

        if (isset($_SESSION)) {
            $log .= "\nSESSION:" .var_export($_SESSION);
        }

        //Activities
        $log .= "\nACTIVITIES:\n";
        $buffers = $this->getBuffer();
        //serialize to string
        foreach ($buffers as $buffer) {
            $mark = sprintf(
                "%s\n%s %.3f seconds (+%.3f); %0.2f MB (%s%0.3f). Peak:%0.2f MB\n",
                $buffer['label'],
                $buffer['time'],
                $buffer['next_time'],
                $buffer['memory'],
                ($buffer['next_memory'] > 0) ? '+' : '-',
                $buffer['next_memory'],
                $buffer['memory_get_peak_usage'] / 1048576
            );
            $log .= $mark;
        }

        //file include
        $files = get_included_files();
        $log .= "\nIncluded files:";
        $log .= implode("\n", $files);

        @file_put_contents($path.'/'.$filename, $log, FILE_APPEND);
    }
	
	/**
	 * Debug
	 * draw ra khối debug.
	 *
	 * @return HTML content
	 */
	public static function debug() 	{				
		if (!Ming_Config::get('debug') || is_cli()) {
			return;
		}
			
		$profiler = self::getInstance();
		ob_start();		
		echo '<style type="text/css">
	#system-debug{width: 100% !important; color:#555555; line-height: 1.5em;} 
	#system-debug div#sql-report strong{ color:#993333}; .blue {color:#00CC00;};
</style><div id="system-debug" class="clearfix"><h3>IN DEBUG ENVIROMENT</h3>';
		
		#List Mark
		echo '<h4>Activities Information</h4>';
		echo '<ol>';
		$marks = $profiler->getBuffer();		
		for ($i = 0, $msize = sizeof($marks); $i < $msize; ++$i)
		{
			echo '<li>' . $marks[$i] .'</li>';
		}
		echo '</ol>';		
		echo '<div>';
		echo '<h4>Memory Usage</h4>';
		$memoryLimit = (int) ini_get('memory_limit');
		$perMem = sprintf('%0.3f', ($profiler->getMemUsage() / $memoryLimit) * 100);
		if ($perMem >= 75) {
			$perMem = '<strong>' .$perMem .' %</strong>';			
		}
		else {
			$perMem = '<span class="blue">' . $perMem .'%</span>';
		}
		echo $profiler->getMemUsage() .' / ' . (int) ini_get('memory_limit') .' MB (' .$perMem  .')';
		echo '</div>';
		
		echo '<div><h4>Server Id</h4>' . $_SERVER['SERVER_ADDR'] .'</div>';
		
		#Show log SQL queries
		$newlineSQLKeywords = '/<strong>'
				.'(FROM|LEFT|INNER|OUTER|WHERE|SET|VALUES|ORDER|GROUP|HAVING|LIMIT|ON|AND|OR)'
				.'<\\/strong>/i';
		$sqlKeyword = array (
			'ASC', 'AS',  'ALTER', 'AND', 'AGAINST',
			'BETWEEN', 'BOOLEAN', 'BY', 
			'COUNT', 
			'DESC',  'DISTINCT', 'DELETE',
			'EXPLAIN',
			'FOR', 'FROM',
			'GROUP',
			'HAVING',
			'INSERT', 'INNER', 'INTO', 'IN',
			'JOIN',
			'LIKE', 'LIMIT', 'LEFT',
			'MATCH', 'MODE', 
			'NOT',
			'ORDER', 'OR', 'OUTER', 'ON',
			'REPLACE', 'RIGHT',
			'STRAIGHT_JOIN', 'SELECT', 'SET',
			'TO', 'TRUNCATE',
			'UPDATE',
			'VALUES',
			'WHERE',);
		
		$sqlReplaceKeyword = array (
			'<strong>ASC</strong>', '<strong>AS</strong>',  '<strong>ALTER</strong>', '<strong>AND</strong>', '<strong>AGAINST</strong>',
			'<strong>BETWEEN</strong>', '<strong>BOOLEAN</strong>', '<strong>BY</strong>',
			'<strong>COUNT</strong>',
			'<strong>DESC</strong>',  '<strong>DISTINCT</strong>', '<strong>DELETE</strong><br />',
			'<strong>EXPLAIN</strong>',
			'<strong>FOR</strong>', '<strong>FROM</strong>',
			'<strong>GROUP</strong>',
			'<strong>HAVING</strong>',
			'<strong>INSERT</strong>', '<strong>INNER</strong>', '<strong>INTO</strong>', '<strong>IN</strong>',
			'<strong>JOIN</strong>',
			'<strong>LIKE</strong>', '<strong>LIMIT</strong>', '<strong>LEFT</strong>',
			'<strong>MATCH</strong>', '<strong>MODE</strong>',
			'<strong>NOT</strong>',
			'<strong>ORDER</strong>', '<strong>OR</strong>', '<strong>OUTER</strong>', '<strong>ON</strong>',
			'<strong>REPLACE</strong><br />', '<strong>RIGHT</strong>',
			'<strong>STRAIGHT_JOIN</strong>', '<strong>SELECT</strong><br />', '<strong>SET</strong>',
			'<strong>TO</strong>', '<strong>TRUNCATE</strong><br />',
			'<strong>UPDATE</strong><br />',
			'<strong>VALUES</strong>',
			'<strong>WHERE</strong>');
		
		$daObjs = Ming_Db::getInstances();
		$queriesLogNo = 0;
		$executeTime = 0;
		echo '<div id="sql-report"><h4>Queries Logged</h4><ol>';
		foreach ($daObjs as $daObj)
		{
			$queries = $daObj->getLog();
			for ($i = 0, $qsize = sizeof($queries); $i < $qsize; ++$i)
			{								
				$sql = str_replace($sqlKeyword, $sqlReplaceKeyword, $queries[$i]['sql']);
				$sql = preg_replace('/\"([^\"])+\"/','<font color="#ff000;">\\0</font>', $sql);
				$sql = preg_replace('/\'([^\'])+\'/','<font color="#ff000;">\\0</font>', $sql);
				$sql = preg_replace($newlineSQLKeywords, '<br />&nbsp;&nbsp;\\0', $sql);
				$track = '<ol style="font-family: Courier, \'Courier New\', monospace">';
				foreach ($queries[$i]['track'] as $_t) {
					$track .= '<li>' .$_t['call']  .' in <em>' .$_t['file'].':' .$_t['line'] .'</em></li>';					
				}
				$track .= '</ol>';
				
				echo '<li>' . $sql . '<br />&nbsp; at ' .$track .'&nbsp; <em>execute time: ' 
							. $queries[$i]['time'] . ' seconds</em></li>';
				$executeTime += $queries[$i]['time'];								
			}
			$queriesLogNo += $qsize;
		}
		echo '</ol><br />Total <strong>' . $queriesLogNo . '</strong> SQL queries logged take <em>' 
						. $executeTime .'</em> seconds exec</h4>';
			
		echo '<div><h4>Included files</h4><ol>';
		$files = get_included_files();
		for ($i = 0, $size = sizeof($files); $i < $size; ++$i)
		{
			echo '<li>' . $files[$i] . '</li>';
		}
		echo '</ol><br />Total <strong>' . $size . '</strong> included files.</div>';
		echo '</div>';
		
		$debug = ob_get_clean();
//		file_put_contents(_LOG_PATH_.'log'. time().'.txt', $debug);		
		return $debug;
	}
}