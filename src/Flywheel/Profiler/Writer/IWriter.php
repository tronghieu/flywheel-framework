<?php

namespace Flywheel\Profiler\Writer;

use Flywheel\Profiler\IProfiler;

interface IWriter
{
    /**
     * Set Writer's Profile Owner
     * Using Owner for Writer access profile's data
     *
     * @param IProfiler $profiler
     * @return void
     */
    public function setOwner(IProfiler $profiler);

    /**
     * Write profile data
     *
     * @return void
     */
    public function write();
}