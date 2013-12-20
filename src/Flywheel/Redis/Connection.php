<?php
namespace Flywheel\Redis;

class Connection extends \Redis {
    protected static $_instances = array();

    /**
     * get redis connection
     * @param $dsn
     * @param array $config
     * @return Connection
     */
    public static function getInstance($dsn, $config = array()) {

        if (!isset(self::$_instances[$dsn])) {
            $timeout = isset($config['timeout'])? $config['timeout'] : 0;

            $t = explode('/',$dsn);
            $db = isset($t[1])? $t[1] : 0;
            $t = explode(':', $t[0]);

            $conn = new \Redis();
            $conn->connect($t[0], $t[1], $timeout);

            if (isset($config['auth']) && $config['auth']) {
                $conn->auth($config['auth']);
            }

            if (isset($config['prefix']) && $config['prefix']) {
                $conn->setOption(\Redis::OPT_PREFIX, $config['prefix']);
            }

            $conn->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE); //don't serialize data
            $conn->select($db);
            self::$_instances[$dsn] = $conn;
        }

        return self::$_instances[$dsn];
    }
}