<?php

namespace Flywheel\Translation;

use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class Translator extends \Symfony\Component\Translation\Translator {
    protected static $_cache = array();

    /**
     * add resource from file
     * @param $file
     * @param $locale
     * @throws \Symfony\Component\Translation\Exception\InvalidResourceException
     * @throws \Symfony\Component\Translation\Exception\NotFoundResourceException
     */
    public function addResourceFromFile($file, $locale) {
        if (isset(self::$_cache[$locale.$file])) {
            return ;
        }

        if (!stream_is_local($file)) {
            throw new InvalidResourceException(sprintf('This is not a local file "%s".', $file));
        }

        if (!file_exists($file)) {
            throw new NotFoundResourceException(sprintf('File "%s" not found.', $file));
        }

        $resources = require($file);
        foreach($resources as $domain => $message) {
            parent::addResource('array', $message, $locale, $domain);
        }

        self::$_cache[$locale.$file] = true;
    }
}