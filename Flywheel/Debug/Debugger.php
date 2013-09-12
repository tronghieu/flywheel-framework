<?php
namespace Flywheel\Debug;


use Flywheel\Object;

class Debugger extends Object {
    public static function enable() {
        Profiler::init();
    }
}