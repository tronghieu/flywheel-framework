<?php
namespace Flywheel\Event;
class Event
{
    /**
     * @var Boolean Whether no further event listeners should be triggered
     */
    private $_propagationStopped = false;

    /**
     * @var \Flywheel\Event\Dispatcher Dispatcher that dispatched this event
     */
    private $_dispatcher;

    /**
     * @var string This event's name
     */
    private $name;

    /**
     * @var object the sender of this event
     */
    public $sender;

    /**
     * @var mixed additional event parameters.
     */
    public $params;

    public function __construct($sender = null, $params = null) {
        $this->sender = $sender;
        $this->params = $params;

        $this->_init();


    }

    protected function _init() {}

    /**
     * Returns whether further event listeners should be triggered.
     *
     * @see Event::stopPropagation
     * @return Boolean Whether propagation was already stopped for this event.
     *
     * @api
     */
    public function isPropagationStopped()
    {
        return $this->_propagationStopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     *
     * @api
     */
    public function stopPropagation()
    {
        $this->_propagationStopped = true;
    }

    /**
     * Stores the EventDispatcher that dispatches this Event
     *
     * @param \Flywheel\Event\IDispatcher $dispatcher
     *
     * @api
     */
    public function setDispatcher(\Flywheel\Event\IDispatcher $dispatcher)
    {
        $this->_dispatcher = $dispatcher;
    }

    /**
     * Returns the EventDispatcher that dispatches this Event
     *
     * @return \Flywheel\Event\IDispatcher
     *
     * @api
     */
    public function getDispatcher()
    {
        return $this->_dispatcher;
    }

    /**
     * Gets the event's name.
     *
     * @return string
     *
     * @api
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the event's name property.
     *
     * @param string $name The event name.
     *
     * @api
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}
