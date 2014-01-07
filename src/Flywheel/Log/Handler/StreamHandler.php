<?php
namespace Flywheel\Log\Handler;

class StreamHandler extends \Monolog\Handler\StreamHandler {
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