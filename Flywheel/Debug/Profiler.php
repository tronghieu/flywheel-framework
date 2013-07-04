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
class Debug_Profiler {
	private $_start;
	
	private $_buffer = array();
	
	private function __construct() {
		$this->_start = $_SERVER['REQUEST_TIME'];
	}
	
	/**
	 * Get Instance
	 * 
	 * @static 
	 * @return Ming_Debug_Profiler
	 */
	public static function getInstance() {
		static $instance;
		if ($instance == null) {
			$instance = new Ming_Debug_Profiler();
		}
		
		return $instance;
	}
	
	public static function mark($label, $package) {
		$profiler = self::getInstance();
		$mark = 'Package: <strong>' . $package . '</strong>. ';
		$mark .= $label .'. <strong>&raquo;</strong> ';
		$mark .= sprintf('Time %.5f', $profiler->_getMicrotime() - $profiler->_start) . ' seconds';
		$mark .= ', '.sprintf('%0.3f', memory_get_usage() / 1048576 ).' MB.';           
		$profiler->_buffer[] = $mark;
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
	
	public static function pageSpeedTracking() {		
		try {
			$profiler = self::getInstance();
			$time = $profiler->_getMicrotime() - $profiler->_start;
			$request = $_SERVER['REQUEST_URI'];
			$tracker = Ming_Redis_Client::getInstance('system');			
			$key = 'pst_' .md5($request);
			$trackItem = $tracker->get($key);
			
			#track total time process
			if (null == $trackItem) {
				$trackItem = array(
					'request' => $request,
					'from_time' => date('d/m/Y H:i:s', time()),
					'count'	=> 0,
					'total_time' => 0
				);
			}
			++$trackItem['count'];
			$trackItem['total_time'] += $time;
			$trackItem['avg'] = $trackItem['total_time']/$trackItem['count'];
			$trackItem['last_time'] = date('d/m/Y H:i:s', time());
			
			#track each server
			if (!isset($trackItem[$_SERVER['SERVER_ADDR']])) {
				$trackItem[$_SERVER['SERVER_ADDR']] = array(
					'count'	=> 0,
					'total_time' => 0
				);
			}
			
			++$trackItem[$_SERVER['SERVER_ADDR']]['count'];
			$trackItem[$_SERVER['SERVER_ADDR']]['total_time'] += $time;
			$trackItem[$_SERVER['SERVER_ADDR']]['avg'] = $trackItem[$_SERVER['SERVER_ADDR']]['total_time']/$trackItem[$_SERVER['SERVER_ADDR']]['count'];
			
			//store tracking result
			$tracker->set($key, $trackItem);
			$tracker->expire($key, 2592000); //30 days
		} catch (Exception $e) {}
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
	
	/**
	 * Get the current time.
         *
         * @access public
         * @return float The current time
         */
	private function _getMicrotime() {
		list( $usec, $sec ) = explode( ' ', microtime() );
		return ((float)$usec + (float)$sec);
	}	
}