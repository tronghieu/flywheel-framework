<?php
namespace Flywheel\Debug;

interface IHandler {
    public function getName();

    public function write($records);
} 