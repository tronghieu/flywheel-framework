<?php
namespace Flywheel\Controller;
use Flywheel\Factory;
use Flywheel\Event\Event as Event;
use Flywheel\Util\Inflection;

class Api extends BaseController
{
    public function __construct($name, $params) {
        $this->_name = $name;
        $this->_params = $params;
    }

    public function beforeExecute() {}

    final public function execute($regMethod, $method = null) {
        if (!$method) {
            throw new \Flywheel\Exception\Api('Api not found', 404);
        }

        $apiMethod = strtolower($regMethod) .Inflection::camelize($method);
        $this->getEventDispatcher()->dispatch('onBeginControllerExecute', new Event($this, array('action' => $apiMethod)));
        if (!method_exists($this, $apiMethod)) {
            throw new \Flywheel\Exception\Api('Api '.Factory::getRouter()->getApi() ."/{$method} not found", 404);
        }

        $this->beforeExecute();
        $buffer = $this->$apiMethod();
        $this->afterExecute();

        $this->getEventDispatcher()->dispatch('onAfterControllerExecute', new Event($this, array('action' => $apiMethod)));
        return $buffer;
    }

    public function afterExecute() {}

    public function sendResponse($status = 200, $body = array()) {
        $this->response()->setStatusCode($status);
        return $body;
    }
}
