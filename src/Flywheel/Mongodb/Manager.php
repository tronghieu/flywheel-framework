<?php
namespace Flywheel\Mongodb;
use Flywheel\Object;

class Manager  extends Object{
 
private static $configuration;

public static function initialize() { 
        if (null === self::$configuration)
            self::initConfig();

       
    }
public static function initConfig() { 
        self::loadConfig(ROOT_PATH .'/config.cfg.php');   //config.cfg .example.php
    }

public static function loadConfig($configFile) {
        $configuration = include($configFile);
        if (false === $configuration)
            throw new Exception("Unable to open Mongo configuration file: " . var_export($configFile, true));
       // echo 'configuration))))))';
        //var_dump($configuration);
        self::$configuration = $configuration['database']['mongodb'];
    }
public function getConnection(){
    self::initialize();
    $host=self::$configuration['host']; 
    $port=self::$configuration['port']; 
    $db=self::$configuration['db']; 
    $conn = new Connection($host,$port,$db);
    return  $conn;
    }


}