<?php
namespace Flywheel\Behavior;
abstract class BaseBehavior implements IBehavior
{
    public $options = array();
    protected $_owner;
    protected $_enable = false;

    public function init() {}

    public function setup($options = array()) {
        foreach ($options as $option=>$value) {
            if (property_exists($this, $option)) {
                $this->{$option} = $value;
                unset($options[$option]);
            }
        }

        $this->options = $options;
    }

    /**
     * Set owner this behavior
     * @param $owner
     */
    public function setOwner($owner) {
        $this->_owner = $owner;
    }

    /**
     * Get owner of this behavior
     * @return mixed
     */
    public function getOwner() {
        return $this->_owner;
    }

    /**
     * @return bool
     */
    public function getEnable()
    {
        return $this->_enable;
    }

    /**
     * @param $enable
     * @return bool
     */
    public function setEnable($enable)
    {
        $this->_enable = $enable;
        return true;
    }
}
