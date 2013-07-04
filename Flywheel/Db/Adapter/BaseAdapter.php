<?php
namespace Flywheel\Db\Adapter;
abstract class BaseAdapter {
    /**
     * Propel driver to Propel adapter map.
     * @var array
     */
    private static $adapters = array(
        'mysql'  => '\Flywheel\Db\Adapter\MySQLi',
        'mysqli' => '\Flywheel\Db\Adapter\MySQLi',
        'mssql'  => '\Flywheel\Db\Adapter\MSSQL',
        'sqlsrv' => '\Flywheel\Db\Adapter\SQLSRV',
        'oracle' => '\Flywheel\Db\Adapter\Oracle',
        'oci'    => '\Flywheel\Db\Adapter\Oracle',
        'pgsql'  => '\Flywheel\Db\Adapter\Postgres',
        'sqlite' => '\Flywheel\Db\Adapter\SQLite',
        ''       => 'None',
    );

    /**
     * Creates a new instance of the database adapter associated
     * with the specified Propel driver.
     *
     * @param string $driver The name of the Propel driver to create a new adapter instance
     *                            for or a shorter form adapter key.
     *
     * @throws \Flywheel\Db\Exception
     * @return BaseAdapter An instance of a database adapter
     */
    public static function factory($driver)
    {
        $adapterClass = isset(self::$adapters[$driver]) ? self::$adapters[$driver] : null;
        if ($adapterClass !== null) {
            $a = new $adapterClass();
            return $a;
        } else {
            throw new \Flywheel\Db\Exception("Unsupported driver: " . $driver . ": Check your configuration file");
        }
    }

    /**
     * This method is called after a connection was created to run necessary
     * post-initialization queries or code.
     *
     * If a charset was specified, this will be set before any other queries
     * are executed.
     *
     * This base method runs queries specified using the "query" setting.
     *
     * @see       setCharset()
     *
     * @param \PDO   $con      A PDO connection instance.
     * @param array $settings An array of settings.
     */
    public function initConnection(\PDO $con, array $settings)
    {
		$this->setCharset($con, 'utf8');
        /*if (isset($settings['charset']['value'])) {
            $this->setCharset($con, $settings['charset']['value']);
        }*/
        if (isset($settings['queries']) && is_array($settings['queries'])) {
            foreach ($settings['queries'] as $queries) {
                foreach ((array) $queries as $query) {
                    $con->exec($query);
                }
            }
        }
    }

    /**
     * Sets the character encoding using SQL standard SET NAMES statement.
     *
     * This method is invoked from the default initConnection() method and must
     * be overridden for an RDMBS which does _not_ support this SQL standard.
     *
     * @see       initConnection()
     *
     * @param \PDO    $con     A $PDO PDO connection instance.
     * @param string $charset The $string charset encoding.
     */
    public function setCharset(\PDO $con, $charset)
    {
        $con->exec("SET NAMES '" . $charset . "'");
    }

    /**
     * Returns the character used to indicate the beginning and end of
     * a piece of text used in a SQL statement (generally a single
     * quote).
     *
     * @return string The text delimeter.
     */
    public function getStringDelimiter()
    {
        return '\'';
    }

    /**
     * Quotes database objec identifiers (table names, col names, sequences, etc.).
     * @param  string $text The identifier to quote.
     * @return string The quoted identifier.
     */
    public function quoteIdentifier($text)
    {
        return '"' . $text . '"';
    }

    /**
     * Quotes a database table which could have space seperating it from an alias, both should be identified seperately
     * This doesn't take care of dots which separate schema names from table names. Adapters for RDBMs which support
     * schemas have to implement that in the platform-specific way.
     *
     * @param  string $table The table name to quo
     * @return string The quoted table name
     **/
    public function quoteIdentifierTable($table)
    {
        return implode(" ", array_map(array($this, "quoteIdentifier"), explode(" ", $table) ) );
    }

    /**
     * Returns timestamp formatter string for use in date() function.
     *
     * @return string
     */
    public function getTimestampFormatter()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Returns date formatter string for use in date() function.
     *
     * @return string
     */
    public function getDateFormatter()
    {
        return "Y-m-d";
    }

    /**
     * Returns time formatter string for use in date() function.
     *
     * @return string
     */
    public function getTimeFormatter()
    {
        return "H:i:s";
    }

    /**
     * Modifies the passed-in SQL to add LIMIT and/or OFFSET.
     *
     * @param string  $sql
     * @param integer $offset
     * @param integer $limit
     */
    abstract public function applyLimit(&$sql, $offset, $limit);

    /**
     * Gets the SQL string that this adapter uses for getting a random number.
     *
     * @param mixed $seed (optional) seed value for databases that support this
     */
    abstract public function random($seed = null);

    /**
     * This method is used to ignore case.
     *
     * @param  string $in The string to transform to upper case.
     * @return string The upper case string.
     */
    abstract public function toUpperCase($in);

    /**
     * This method is used to ignore case.
     *
     * @param  string $in The string whose case to ignore.
     * @return string The string in a case that can be ignored.
     */
    abstract public function ignoreCase($in);

    abstract public function getPDOParam($dbType);
}
