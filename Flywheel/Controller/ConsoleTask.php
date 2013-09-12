<?php
namespace Flywheel\Controller;
use Flywheel\Event\Event;
use Flywheel\Util\Inflection;
use Flywheel\Base;

abstract class ConsoleTask extends BaseController
{
    public function getParam($index) {
        $params = $this->getParams();
        return isset($params[$index])? $params[$index] : null;
    }

    public function getParams () {
        if ($app = Base::getApp()) {
            /* @var \Flywheel\Application\ConsoleApp $app */
            return $app->getParams();
        }
        return array();
    }

    public function beforeExecute() {}

    final public function execute($action) {
        $action = 'execute' .Inflection::hungaryNotationToCamel($action);
        if (method_exists($this, $action)) {
            $this->getEventDispatcher()->dispatch('onBeginControllerExecute', new Event($this, array('action' => $action)));
            $this->beforeExecute();
            $this->$action();
            $this->afterExecute();
            $this->getEventDispatcher()->dispatch('onAfterControllerExecute', new Event($this, array('action' => $action)));
        } else {
            Base::end('ERROR: task ' .Inflection::hungaryNotationToCamel($this->_name) .':' .$action .' not existed!' .PHP_EOL);
        }
    }
    
    public function afterExecute() {}
}
