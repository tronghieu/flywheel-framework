<?php

namespace Flywheel\Profiler\Writer;

use Flywheel\Profiler\BaseProfiler;

interface IWriter
{
    /**
     * Set Writer's Profile Owner
     * Using Owner for Writer access profile's data
     *
     * @param BaseProfiler $profiler
     * @return void
     */
    public function setOwner(BaseProfiler $profiler);

    /**
     * Write profile data
     *
     * @return void
     */
    public function write();
}