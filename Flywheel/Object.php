<?php
namespace Flywheel;
use Flywheel\Behavior\BaseBehavior;
use Flywheel\Behavior\IBehavior;
use Flywheel\Event\Dispatcher;
use Flywheel\Event\Event as EventCommon;

abstract class Object {
    /**
     * @var \Flywheel\Behavior\BaseBehavior[]
     */
    protected $_behaviors = array();

    protected $_privateDispatcher;

    protected static $_dispatcher;

    public function getPrivateEventDispatcher() {
        if (null == $this->_privateDispatcher) {
            $this->_privateDispatcher = new Dispatcher();
        }

        return $this->_privateDispatcher;
    }

    /**
     * Get event dispatcher
     * @return Dispatcher
     */
    public static function getEventDispatcher() {
        if (null == self::$_dispatcher) {
            self::$_dispatcher = new Dispatcher();
        }

        return self::$_dispatcher;
    }

    /**
     * Attaches a behavior to this component.
     * This method will create the behavior object based on the given
     * configuration. After that, the behavior object will be initialized
     * by calling its {@link IBehavior::setOwner} method.
     * @param string $name the behavior's name. It should uniquely identify this behavior.
     * @param mixed $behavior the behavior object or class path of behavior
     * @param array $option
     * @throws Exception
     * @param array $option
     * @internal param array $options optional parameter @see IBehavior::setup
     * @return IBehavior the behavior object
     *
     */
    public function attachBehavior($name, $behavior, $option = array()) {
        if (is_string($behavior)) {
            Loader::import($behavior);
            $behavior = new $behavior();
            /* @var BaseBehavior $behavior */
        }

        if (!($behavior instanceof IBehavior)) {
            throw new Exception("Behavior was attached must extends from \\Flywheel\\Behavior\\BaseBehavior");
        }


        $behavior->setEnable(true);
        $behavior->setOwner($this);
        $behavior->init();
        $behavior->setup($option);
        $this->_behaviors[$name] = $behavior;
    }



    public function attacheBehaviors($behaviors) {
        foreach($behaviors as $name=>$options) {
            if (!isset($options['class'])) {
                throw new Exception("Missing parameter 'class'. This parameter is required for define object behavior");
            }

            $behavior = $options['class'];
            unset($options['class']);

            $behavior = $options['class'];
            $this->attachBehavior($behavior, $options);
        }
    }

    /**
     * detach behavior by name
     * @param $name
     */
    public function detachBehavior($name) {
        unset($this->_behaviors[$name]);
    }

    /**
     * detach all behaviors
     */
    public function detachAllBehaviors() {
        $this->_behaviors = array();
    }

    /**
     * enable behavior by name
     * @param $name
     * @return bool
     */
    public function enableBehavior($name) {
        if (!isset($this->_behaviors[$name])) {
            return false;
        }

        return $this->_behaviors[$name]->setEnable(true);
    }

    /**
     * enable all behaviors
     */
    public function enableAllBehaviors() {
        for ($name = array_keys($this->_behaviors), $i = 0, $size = sizeof($name); $i < $name; ++$i) {
            $this->enableBehavior($name[$i]);
        }
    }

    /**
     * disable behavior by name
     * @param $name
     * @return bool
     */
    public function disableBehavior($name) {
        if (!isset($this->_behaviors[$name])) {
            return false;
        }

        return $this->_behaviors[$name]->setEnable(false);
    }

    /**
     * disable all behaviors
     */
    public function disableAllBehaviors() {
        for ($name = array_keys($this->_behaviors), $i = 0, $size = sizeof($name); $i < $name; ++$i) {
            $this->disableBehavior($name[$i]);
        }
    }

    /**
     * shortcut call self::getEventDispatcher()->dispatch() method
     *
     * @see IDispatcher::dispatch
     *
     * @api
     */
    public function dispatch($eventName, EventCommon $event = null) {
        return $this->getEventDispatcher()->dispatch($eventName, $event);
    }

    public function __call($method, $params) {
        foreach ($this->_behaviors as $behavior) {
            if($behavior->getEnable() && method_exists($behavior, $method)) {
                return call_user_func_array(array($behavior, $method), $params);
            }
        }
    }
}
