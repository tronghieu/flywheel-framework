<?php

namespace Flywheel\Translation;

use Flywheel\Config\ConfigHandler;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageSelector;

class Translator extends \Symfony\Component\Translation\Translator {
    protected static $_cache = array();

    protected static $_instance;

    /**
     * factory Translator object
     * @return null|Translator
     */
    public static function getInstance() {
        $i18nCfg = ConfigHandler::get('i18n');
        if (!$i18nCfg['enable']) {
            return null;
        }

        if (null == static::$_instance) {
            $translator = new Translator($i18nCfg['default_locale'], new MessageSelector());
            $translator->setFallbackLocales($i18nCfg['default_fallback']);
            $translator->addLoader('array', new ArrayLoader());

            //add init resource
            if (isset($i18nCfg['resource']) && is_array($i18nCfg['resource'])) {
                foreach($i18nCfg['resource'] as $locale => $files) {
                    for ($i = 0, $size = sizeof($files); $i < $size; ++$i) {
                        $translator->addResourceFromFile($files[$i], $locale);
                    }
                }
            }

            static::$_instance = $translator;
        }

        return static::$_instance;
    }

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

        $info = new \SplFileInfo($file);
        if($info->getExtension() == 'php') {
            $resources = require($file);
            foreach($resources as $domain => $message) {
                parent::addResource('array', $message, $locale, $domain);
            }

            self::$_cache[$locale.$file] = true;
        }
    }
}