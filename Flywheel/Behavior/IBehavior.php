<?php
namespace Flywheel\Behavior;
interface IBehavior
{
    public function setup($options = array());

    public function setOwner($owner);

    public function getOwner();

    public function getEnable();

    public function setEnable($enable);
}
