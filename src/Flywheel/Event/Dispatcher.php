<?php
namespace Flywheel\Event;
class Dispatcher implements IDispatcher
{
    private $_listeners = array();
    private $_sorted = array();

    /**
     * @see IDispatcher::dispatch
     *
     * @api
     */
    public function dispatch($eventName, \Flywheel\Event\Event $event = null)
    {
        if (null === $event) {
            $event = new Event();
        }

        $event->setDispatcher($this);
        $event->setName($eventName);

        if (!isset($this->_listeners[$eventName])) {
            return $event;
        }

        $this->doDispatch($this->getListeners($eventName), $eventName, $event);

        return $event;
    }

    /**
     * @see IDispatcher::getListeners
     */
    public function getListeners($eventName = null)
    {
        if (null !== $eventName) {
            if (!isset($this->_sorted[$eventName])) {
                $this->sortListeners($eventName);
            }

            return $this->_sorted[$eventName];
        }

        foreach (array_keys($this->_listeners) as $eventName) {
            if (!isset($this->_sorted[$eventName])) {
                $this->sortListeners($eventName);
            }
        }

        return $this->_sorted;
    }

    /**
     * @see IDispatcher::hasListeners
     */
    public function hasListeners($eventName = null)
    {
        return (Boolean) count($this->getListeners($eventName));
    }

    /**
     * @see Ming_Event_IDispatcher::addListener
     *
     * @api
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        $this->_listeners[$eventName][$priority][] = $listener;
        unset($this->_sorted[$eventName]);
    }

    /**
     * @see IDispatcher::removeListener
     */
    public function removeListener($eventName, $listener)
    {
        if (!isset($this->_listeners[$eventName])) {
            return;
        }

        foreach ($this->_listeners[$eventName] as $priority => $listeners) {
            if (false !== ($key = array_search($listener, $listeners))) {
                unset($this->_listeners[$eventName][$priority][$key], $this->_sorted[$eventName]);
            }
        }
    }

    /**
     * @see IDispatcher::addSubscriber
     *
     * @api
     */
    public function addSubscriber(ISubscriber $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->addListener($eventName, array($subscriber, $params));
            } elseif (is_string($params[0])) {
                $this->addListener($eventName, array($subscriber, $params[0]), isset($params[1]) ? $params[1] : 0);
            } else {
                foreach ($params as $listener) {
                    $this->addListener($eventName, array($subscriber, $listener[0]), isset($listener[1]) ? $listener[1] : 0);
                }
            }
        }
    }

    /**
     * @see IDispatcher::removeSubscriber
     */
    public function removeSubscriber(ISubscriber $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_array($params) && is_array($params[0])) {
                foreach ($params as $listener) {
                    $this->removeListener($eventName, array($subscriber, $listener[0]));
                }
            } else {
                $this->removeListener($eventName, array($subscriber, is_string($params) ? $params : $params[0]));
            }
        }
    }

    /**
     * Triggers the listeners of an event.
     *
     * This method can be overridden to add functionality that is executed
     * for each listener.
     *
     * @param array[callback] $listeners The event listeners.
     * @param string          $eventName The name of the event to dispatch.
     * @param \Flywheel\Event\Event $event The event object to pass to the event handlers/listeners.
     */
    protected function doDispatch($listeners, $eventName, Event $event)
    {
        foreach ($listeners as $listener) {
            call_user_func($listener, $event);
            if ($event->isPropagationStopped()) {
                break;
            }
        }
    }

    /**
     * Sorts the internal list of listeners for the given event by priority.
     *
     * @param string $eventName The name of the event.
     */
    private function sortListeners($eventName)
    {
        $this->_sorted[$eventName] = array();

        if (isset($this->_listeners[$eventName])) {
            krsort($this->_listeners[$eventName]);
            $this->_sorted[$eventName] = call_user_func_array('array_merge', $this->_listeners[$eventName]);
        }
    }
}
