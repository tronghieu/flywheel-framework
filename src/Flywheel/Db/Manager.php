<?php
namespace Flywheel\Db;
use Flywheel\Config\ConfigHandler;
use Flywheel\Event\Event;
use Flywheel\Object;

class Manager extends Object{
    const __MASTER__ = 'master',
            __SLAVE__ = 'slave';

    private static $configuration;
    private static $connectionMap = array();
    private static $slavesTable = array();
    private static $init = false;
    private static $adapterMap = array();

    public static function initialize() {
        if (null === self::$configuration)
            self::initConfig();

        self::$connectionMap = array(); //reset
        self::$init = true;
    }

    /**
     * Init database's configuration
     */
    public static function initConfig() {
        self::$configuration = ConfigHandler::get('database');
    }

    /**
     * @param null $name
     * @param string $mode
     * @return \Flywheel\Db\Connection
     */
    public static function getConnection($name = null, $mode = self::__MASTER__) {
        if (!self::$init)
            self::initialize();
        if (null == $name || !isset(self::$configuration[$name]))
            $name = self::getDefaultDB();
        return ($mode != self::__SLAVE__)? self::getMasterConnection($name) :
                self::getRandomSlaveConnection($name);
    }

    /**
     * Gets an already-opened write PDO connection or opens a new one for passed-in db name.
     *
     * @param string $name The datasource name that is used to look up the DSN
     *                          from the runtime configuation file. Empty name not allowed.
     *
     * @return \Flywheel\Db\Connection A database connection
     *
     * @throws Exception - if connection cannot be configured or initialized.
     */
    public static function getMasterConnection($name) {
        if (!isset(self::$connectionMap[$name]))
            self::$connectionMap[$name] = array();
        if (!isset(self::$connectionMap[$name]['master'])) {
            $conn = self::initConnection($name, self::$configuration[$name]);
            self::getEventDispatcher()->dispatch('afterCreateMasterConnection', new Event($conn, array('connection_name' => $name)));
            self::$connectionMap[$name]['master'] = $conn;
        }

        return self::$connectionMap[$name]['master'];
    }

    /**
     * Gets an already-opened read PDO connection or opens a new one for passed-in db name.
     * random in slaves
     *
     * @param string $name The datasource name that is used to look up the DSN
     *                          from the runtime configuation file. Empty name not allowed.
     *
     * @return \Flywheel\Db\Connection A database connection
     *
     * @throws Exception - if connection cannot be configured or initialized.
     */
    public static function getRandomSlaveConnection($name) {
        if(!isset(self::$configuration[$name]['slaves']) || empty(self::$configuration[$name]['slaves']))
            return self::getMasterConnection($name);

        if(null != self::$connectionMap[$name]['slave']['__current__'])
            return self::$connectionMap[$name]['slave']['__current__'];

        if (!isset(self::$slavesTable[$name])) {
            foreach (self::$configuration['slaves'] as $slave => $config) {
                $config['weight'] = isset($config['weight'])? $config['weight'] : 1;
                for($i = 0; $i < $config['weight']; $i++)
                    self::$slavesTable[$name][] = $slave;
            }
        }
        $index = array_rand(self::$slavesTable[$name]);
        return self::getSlaveConnection($name, self::$slavesTable[$name][$index]);
    }

    /**
     * Gets an already-opened read PDO connection or opens a new one for passed-in db name.
     *
     * @param string $name The datasource name that is used to look up the DSN
     *                          from the runtime configuation file. Empty name not allowed.
     *
     * @param $slaveName
     * @param bool $setCurrent
     * @return \Flywheel\Db\Connection A database connection
     *
     */
    public static function getSlaveConnection($name, $slaveName, $setCurrent = true) {
        if (!isset(self::$connectionMap[$name]['slave'][$slaveName])) {
            $conn = self::initConnection($name, self::$configuration[$name]['slaves'][$slaveName]);
            self::getEventDispatcher()->dispatch('afterCreateSlaveConnection', new Event($conn, array('connection_name' => $name)));
            if ($setCurrent)
                self::$connectionMap[$name]['slave']['__current__'] = $conn;
        }

        return self::$connectionMap[$name]['slave'][$slaveName];
    }

    public static function initConnection($name, $config) {
        $adapter = self::getAdapter($name);

        if (!isset($config['dsn']) || null === $config['dsn'])
            throw new Exception('No dsn specified in your connection parameters in config '.$name);

        $dbUser = isset($config['db_user'])? $config['db_user'] : null;
        $dbPass = isset($config['db_pass'])? $config['db_pass'] : null;
        $options = isset($config['options'])? $config['options'] : array();
        try {
            $conn = new Connection($config['dsn'], $dbUser, $dbPass, $options);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(Connection::ATTR_CONNECTION_NAME, $name);
        } catch (Exception $dbe) {
            throw new Exception('Unable to open PDO connection', $dbe);
        }

        $adapter->initConnection($conn, isset($config['settings']) && is_array($config['settings']) ? $config['settings'] : array());
        return $conn;
    }

    /**
     * Returns database adapter for a specific datasource.
     *
     * @param string $name The datasource name.
     *
     * @return \Flywheel\Db\Adapter\BaseAdapter The corresponding database adapter.
     * @throws Exception If unable to find Adapter for specified db.
     */
    public static function getAdapter($name = null) {
        if (null == $name || !isset(self::$configuration[$name]))
            $name = self::getDefaultDB();

        if (!isset(self::$adapterMap[$name])) {
            if (!isset(self::$configuration[$name]['adapter']))
                throw new Exception("Unable to find adapter in configuration {$name}");
            $db = \Flywheel\Db\Adapter\BaseAdapter::factory(self::$configuration[$name]['adapter']);
            self::$adapterMap[$name] = $db;
        }

        return self::$adapterMap[$name];
    }

    /**
     * Returns the name of the default database.
     *
     * @return string Name of the default DB
     */
    public static function getDefaultDB() {
        return self::$configuration['__default__'];
    }
}
