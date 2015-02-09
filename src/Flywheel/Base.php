<?php

namespace Flywheel;
use Flywheel\Application\BaseApp;
use Flywheel\Config\ConfigHandler;
use Flywheel\Debug\Debugger;
use Flywheel\Event\Event;

class Base {
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
        ConfigHandler::set('debug', $debug);
        if ($debug) {
            Debugger::enable();
        }
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
        self::getApp()->getEventDispatcher()->dispatch('onEndRequest', new Event(self::getApp()));
        exit($mess);
    }

    /**
     * Merges two or more arrays into one recursively.
     * If each array has an element with the same string key value, the latter
     * will overwrite the former (different from array_merge_recursive).
     * Recursive merging will be conducted if both arrays have an element of array
     * type and are having the same key.
     * For integer-keyed elements, the elements from the latter array will
     * be appended to the former array.
     * @param array $a array to be merged to
     * @param array $b array to be merged from. You can specify additional
     * arrays via third argument, fourth argument etc.
     * @return array the merged array (the original arrays are not changed.)
     */
    public static function mergeArray($a,$b)
    {
        $args=func_get_args();
        $res=array_shift($args);
        while(!empty($args))
        {
            $next=array_shift($args);
            foreach($next as $k => $v)
            {
                if(is_integer($k))
                    isset($res[$k]) ? $res[$k]=$v : $res[]=$v;
                elseif(is_array($v) && isset($res[$k]) && is_array($res[$k]))
                    $res[$k]=self::mergeArray($res[$k],$v);
                else
                    $res[$k]=$v;
            }
        }
        return $res;
    }

    /**
     * check application run from console
     * @return bool
     */
    public static function isCli() {
        return PHP_SAPI === 'cli';
    }
}