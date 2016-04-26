<?php
/**
 * Created by PhpStorm.
 * User: luuhieu
 * Date: 4/26/16
 * Time: 11:26
 */

namespace Flywheel\Profiler\Writer;


use Flywheel\Object;
use Flywheel\Profiler\BaseProfiler;

abstract class BaseWriter extends Object implements IWriter
{
    /** @var  BaseProfiler */
    protected $_owner;

    /**
     * Set owner for writer
     *
     * @param BaseProfiler $profiler
     *
     * @author LuuHieu
     */
    public function setOwner(BaseProfiler $profiler)
    {
        $this->_owner = $profiler;
    }

    /**
     *
     * Get Writer's owner
     *
     * @return BaseProfiler
     *
     * @author LuuHieu
     */
    public function getOwner()
    {
        return $this->_owner;
    }


}