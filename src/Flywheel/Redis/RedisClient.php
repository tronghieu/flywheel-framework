<?php
namespace Flywheel\Redis;
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 1/17/13
 * Time: 5:11 PM
 * To change this template use File | Settings | File Templates.
 */
class RedisClient {
    /**
     * Options
     */
    const OPT_SERIALIZER = 1;
    const OPT_PREFIX = 2;

    /**
     * Serializers
     */
    const SERIALIZER_NONE = 0;
    const SERIALIZER_PHP = 1;

    protected static $_connections = array();

    /**
     * get rediska instance
     * @param 	string	$connect the connect name
     *
     * @return 	\Flywheel\Redis\Connection
     */
    public static function getConnection($connect = null) {
        $config = self::getServersInfo();
        if (null == $connect || !isset($config[$connect])) {
            $connect = $config['__default__'];
        }

        $options = self::inflectConfigForPhpRedis($connect);
        if (!isset(self::$_connections[$connect])) {
            $conn = new Connection();
            $conn->connect($options['host'], $options['port']);
            if (isset($options['auth']) && $options['auth']) {
                $conn->auth($options['auth']);
            }
            $conn->setOption(Connection::OPT_SERIALIZER, Connection::SERIALIZER_PHP);
            self::$_connections[$connect] = $conn;
        }

        if (isset($options['db'])) {
            self::$_connections[$connect]->select($options['db']);
        }

        return self::$_connections[$connect];

    }

    /**
     * inflecting our config to array config of RedisPhp
     *
     * @param string	$configKey
     * @return array
     */
    public static function inflectConfigForPhpRedis($configKey) {
        $config = self::getServersInfo();
        $option = array();
        $option = array_merge($option, $config[$configKey]);
        $servers = null;
        $servers = $config['__' .$option['servers'] .'__'];

        $option['servers'] = $servers;

        return $servers;
    }

    public static function getServersInfo() {
        return \Flywheel\Config\ConfigHandler::load('global.config.redis', 'redis');
    }
}