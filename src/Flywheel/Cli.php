<?php
namespace Flywheel;
class Cli {
    private $_script;
    private $_tasklist = array(
        'gen:models',
        'gen:schemas',
        'gen:apps'
    );

    private function _help() {
        return <<<EOD
COMMAND LINE
usage
  command <task> <param>

task list
  - gen:models          generate models from database, type gen:models help for
  - gen:schemas         generate struct for tables special, type gen:schemas help for
    usage
  - gen:schemas         generate struct for tables special, type gen:schemas help for
  usage
EOD;
    }

    public function run($args) {
        date_default_timezone_set(@date_default_timezone_get());
        $this->_script = $args[0];
        $package = null;


        $task = isset($args[1])? $args[1] : 'help';
        if ('help' == $task) {
            echo $this->_help(); exit;
        }

        if (!$this->_checkTaskExists($task)) {
            echo 'not support task, type "command help" to see supported task list';
        }

        if (strpos($task, ':') !== false) {
            $task = explode(':', $task);
            $package = $task[0];
            $task = $task[1];
        }

        $fileTask = dirname(__FILE__) .DIRECTORY_SEPARATOR .'Task' .DIRECTORY_SEPARATOR
            .(($package !== null)? $package .DIRECTORY_SEPARATOR .$task .'.php' : $task .'.php');

        include_once $fileTask;

        call_user_func($task .'_execute', $this->_parseArgs(array_slice($args, 2)));
    }

    /**
     * Check task exists
     *
     * @param string $task task name
     * @return	boolean
     */
    private function _checkTaskExists($task) {
        return in_array($task, $this->_tasklist);
    }

    protected function _parseArgs($args) {
        $parseParams = array();
        for($i = 0, $size = sizeof($args); $i < $size; ++$i) {
            if (strpos($args[$i], '--') === 0) {
                $_p = explode('=', str_replace('--', '', $args[$i]));
                $parseParams[$_p[0]] = $_p[1];
            }
            else {
                $parseParams[$args[$i]] = $args[$i];
            }
        }

        return $parseParams;
    }
}
