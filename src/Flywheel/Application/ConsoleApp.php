<?php
namespace Flywheel\Application;
use Flywheel\Base;
use Flywheel\Debug\Profiler;
use Flywheel\Event\Event;
use Flywheel\Exception;
use Flywheel\Factory;
use \Flywheel\Config\ConfigHandler as ConfigHandler;
use Flywheel\Util\Inflection;

class ConsoleApp extends BaseApp
{
    protected $_params = array();
    protected $_task;
    private  $_isCli;
    private $_act;
    protected $_finished = false;
    public $_originalParams = array();

    /**
     * add more params
     * @param $args
     */
    public function addParams($args) {
        foreach ($args as $arg) {
            $this->_params[] = $arg;
        }
    }

    /**
     * set execute task name
     * @param string $task
     */
    public function setTask($task) {
        if ($this->_task != $task) {
            $this->_task = $task;
        }
    }

    public function getParams() {
        return $this->_params;
    }

    /**
     *
     */
    protected function _init() {
        define('TASK_DIR', APP_PATH .'/');
        ini_set('display_errors',
            (Base::ENV_DEV == Base::getEnv() || Base::ENV_TEST == Base::getEnv())
                ? 'on' : 'off');
        //Error reporting
        if (Base::getEnv() == Base::ENV_DEV) {
            error_reporting(E_ALL);
        }
        else if (Base::getEnv() == Base::ENV_TEST) {
            error_reporting(E_ALL ^ E_NOTICE);
        }

        //set timezone
        if (ConfigHandler::has('timezone'))
            date_default_timezone_set(ConfigHandler::get('timezone'));
        else
            date_default_timezone_set(@date_default_timezone_get());

        if (true === $this->isCli()) {
            $argv = $_SERVER['argv'];
            $seek = 1;
            if (null == $this->_task) {
                $this->_task = $argv[$seek];
                ++$seek;
            }

            if (null == $this->_act && isset($argv[$seek])) {
                $this->_act = $argv[$seek];
                ++$seek;
            } else {
                $this->_act = 'default';
            }

            if (isset($argv[$seek])) {
                $this->_originalParams = array_slice($argv, $seek);
                $this->_params = $this->_process($this->_originalParams);
            }

        } else { //run on browser (only for test)
            if (null !== ($task = Factory::getRequest()->get('task'))) {
                $this->_task = $task;
            }

            if (null !== ($act = Factory::getRequest()->get('act'))) {
                $this->_act = $act;
            }
        }
    }

    /**
     * process console argv
     * exp: -param1 value1 -param2 value 2 -param3 value 3
     *  to
     *      array('param1' => 'value1', 'param2' => 'value2', 'param3' => 'value3');
     * @param $original
     * @return array
     */
    private function _process($original) {
        $params = array();
        for ($i = 0, $size = sizeof($original); $i < $size; $i+=2) {
            $key = ltrim($original[$i], '--');
            $params[$key] = $original[$i+1];
        }
        return $params;
    }

    /**
     * check application running on console environment
     * @return bool
     */
    public function isCli() {
        if (null === $this->_isCli) {
            $this->_isCli = (PHP_SAPI === 'cli');
        }
        return $this->_isCli;
    }

    public function run() {
        $this->beforeRun();
        $this->getEventDispatcher()->dispatch('onBeginRequest', new Event($this));

        if (null == $this->_task) {
            throw new Exception("Missing 'task' parameter!");
        }

        $camelName = Inflection::hungaryNotationToCamel($this->_task);

        $class = $this->getAppNamespace() .'\\Task\\' .$camelName;
        $taskPath = TASK_DIR .'Task/' .str_replace('\\', DIRECTORY_SEPARATOR, $camelName) .'.php';
        if (file_exists($taskPath)) {
//            require_once $file;
            $this->_controller = new $class($this->_task, $taskPath);
            $this->_controller->execute($this->_act);
            $this->_finished = true;
        } else {
            Base::end("ERROR: task {$this->_task} ({$taskPath}/{$class}Task.php) not existed" .PHP_EOL);
        }

        $this->getEventDispatcher()->dispatch('onEndRequest', new Event($this));
        $this->afterRun();
    }
}
