<?php
namespace Flywheel\Log\Handler;


class RotatingFileHandler extends \Monolog\Handler\RotatingFileHandler {
    /**
     * {@inheritdoc}
     */
    protected function write(array $record) {
        if (!file_exists($this->url)) {
            touch($this->url); // Create blank file
            chmod($this->url, 0777);
        }

        parent::write($record);
    }
}