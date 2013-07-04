<?php
namespace Flywheel\Model\Behavior;
use Flywheel\Db\Type\DateTime;
use Flywheel\Event\Event;

class TimeStamp extends ModelBehavior {
    public $create_attr;
    public $modify_attr;

    public function init() {
        parent::init();
        /* @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        $owner->getPrivateEventDispatcher()->addListener('onBeforeSave', array($this, 'onBeforeSave'));
    }

    public function onBeforeSave(Event $event) {
        /* @var \Flywheel\Model\ActiveRecord $owner */

        $owner = $this->getOwner();
        if ($owner->isNew()) {
            if ($this->create_attr) {
                $owner->{$this->create_attr} = new DateTime();
            }
        }

        if ($this->modify_attr) {
            $owner->{$this->modify_attr} = new DateTime();
        }
    }
}
