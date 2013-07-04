<?php

namespace Flywheel;
use Flywheel\Application\BaseApp;

class Base
{
    const ENV_DEV = 1, ENV_TEST = 2, ENV_PRO = 3;
    private static $_app;
    private static $_env;
    private static $_appPath;

    /**
     * @return \Flywheel\Application\BaseApp
     */
    public static function getApp() {
        return self::$_app;
    }

    public static function getEnv() {
        return self::$_env;
    }

    /**
     * @param $config
     * @param $env
     * @param bool $debug
     * @return \Flywheel\Application\WebApp
     */
    public static function createWebApp($config, $env, $debug = false) {
        return self::_createApplication('\Flywheel\Application\WebApp', $config, $env, $debug, BaseApp::TYPE_WEB);
    }

    /**
     * @param $config
     * @param $env
     * @param bool $debug
     * @return \Flywheel\Application\ApiApp
     */
    public static function createApiApp($config, $env, $debug = false) {
        return self::_createApplication('\Flywheel\Application\ApiApp', $config, $env, $debug, BaseApp::TYPE_API);
    }

    /**
     * @param $config
     * @param $env
     * @param bool $debug
     * @return \Flywheel\Application\ConsoleApp
     */
    public static function createConsoleApp($config, $env, $debug = false) {
        return self::_createApplication('\Flywheel\Application\ConsoleApp', $config, $env, $debug, BaseApp::TYPE_CONSOLE);
    }

    private static function _createApplication($class, $config, $env, $debug, $type) {

        self::$_env = $env;
        \Flywheel\Config\ConfigHandler::set('debug', $debug);
        self::$_app = new $class($config, $type);
        return self::$_app;
    }

    public static function setAppPath($path) {
        if((self::$_appPath=realpath($path))===false || !is_dir(self::$_appPath))
            throw new Exception("Application: Base path \"{$path}\" is not a valid directory.");
    }

    public static function getAppPath() {
        return self::$_appPath;
    }

    public static function end($mess = null) {
        self::getApp()->getEventDispatcher()->dispatch('onEndRequest', new \Flywheel\Event\Event(self::getApp()));
        exit($mess);
    }

    /**
     * check application run from console
     * @return bool
     */
    public static function isCli() {
        return PHP_SAPI === 'cli';
    }
}
