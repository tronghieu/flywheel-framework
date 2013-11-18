<?php
namespace Flywheel\Db;
use Flywheel\Config\ConfigHandler;
use Flywheel\Debug\Profiler;

class Connection extends \PDO {
    /**
     * attr to be use to set whether to cache prepare statements.
     */
    const ATTR_CACHE_PREPARES = -1;

    const ATTR_CONNECTION_NAME = -2;

    /**
     * the current transaction depth.
     * @var int
     */
    public $nestedTransactionCount = 0;

    /**
     * Cache of prepared statement (PDOStatement) keyed by md5 SQL.
     * @var array[md5(sql) => PDOStatement]
     */
    protected $preparedStatements = array();

    protected $cachePreparedStatements = true;

    protected $isUncommitable = false;

    /**
     * Count of queries performed.
     *
     * @var       integer
     */
    protected $queryCount = 0;

    public $lastExecuteQuery;

    public $useDebug = false;

    public $configuration;

    protected $_connectionName;

    public function __construct($dsn, $username = null, $password = null, $driver_options = array()) {
        parent::__construct($dsn, $username, $password, $driver_options);
    }

    /**
     * Returns the number of queries this DebugPDO instance has performed on the database connection.
     *
     * When using DebugPDOStatement as the statement class, any queries by DebugPDOStatement instances
     * are counted as well.
     *
     * @throws Exception if persistent connection is used (since unable to override PDOStatement in that case).
     * @return integer
     */
    public function getQueryCount()
    {
        // extending PDOStatement is not supported with persistent connections
        if ($this->getAttribute(\PDO::ATTR_PERSISTENT)) {
            throw new Exception('Extending PDOStatement is not supported with persistent connections. Count would be inaccurate, because we cannot count the PDOStatment::execute() calls. Either don\'t use persistent connections or don\'t call Connection::getQueryCount()');
        }

        return $this->queryCount;
    }

    /**
     * Increments the number of queries performed by this DebugPDO instance.
     *
     * Returns the original number of queries (ie the value of $this->queryCount before calling this method).
     *
     * @return integer
     */
    public function incrementQueryCount()
    {
        $this->queryCount++;
    }

    public function isInTransaction() {
        return ($this->nestedTransactionCount > 0);
    }

    public function isCommitable() {
        return $this->isInTransaction() && !$this->isUncommitable;
    }

    public function beginTransaction() {
        $return = true;
        if (!$this->nestedTransactionCount) {
            $return = parent::beginTransaction();
            $this->log('Begin transaction', null, __METHOD__);
            $this->isUncommitable = false;
        }

        $this->nestedTransactionCount++;
        return $return;
    }

    public function commit() {
        $return = true;
        $opCount = $this->nestedTransactionCount;

        if (0 < $opCount) {
            if (1 === $opCount) {
                if ($this->isUncommitable) {
                    throw new Exception('Cannot commit because a nested transaction was rolled back');
                } else {
                    $return = parent::commit();
                    $this->log('Commit transaction', null, __METHOD__);
                }
            }

            $this->nestedTransactionCount--;
        }

        return $return;
    }

    public function rollBack() {
        $return = true;
        $opCount = $this->nestedTransactionCount;
        if (0 < $opCount) {
            if (1 === $opCount) {
                $return = parent::rollBack();
                $this->log('Rollback transaction', null, __METHOD__);
            } else {
                $this->isUncommitable = true;
            }

            $this->nestedTransactionCount--;
        }

        return $return;
    }

    /**
     * Rollback the whole transaction, even if this is a nested rollback
     * and reset the nested transaction count to 0.
     *
     * @return    boolean  Whether operation was successful.
     */
    public function forceRollBack()
    {
        $return = true;
        if ($this->nestedTransactionCount) {
            // If we're in a transaction, always roll it back
            // regardless of nesting level.
            $return = parent::rollBack();
            $this->log('Rollback transaction', null, __METHOD__);

            // reset nested transaction count to 0 so that we don't
            // try to commit (or rollback) the transaction outside this scope.
            $this->nestedTransactionCount = 0;
        }

        return $return;
    }

    /**
     * Sets a connection attribute.
     *
     * This is overridden here to provide support for setting Propel-specific attributes too.
     *
     * @param integer $attribute The attribute to set (e.g. PropelPDO::PROPEL_ATTR_CACHE_PREPARES).
     * @param mixed   $value     The attribute value.
     *
     * @return void
     */
    public function setAttribute($attribute, $value) {
        switch ($attribute) {
            case self::ATTR_CACHE_PREPARES:
                $this->cachePreparedStatements = $value;
                break;
            case self::ATTR_CONNECTION_NAME:
                $this->_connectionName = $value;
                break;
            default:
                parent::setAttribute($attribute, $value);
        }
    }

    /**
     * Gets a connection attribute.
     *
     * This is overridden here to provide support for setting Propel-specific attributes too.
     *
     * @param  integer $attribute The attribute to get (e.g. PropelPDO::PROPEL_ATTR_CACHE_PREPARES).
     * @return mixed
     */
    public function getAttribute($attribute) {
        switch ($attribute) {
            case self::ATTR_CACHE_PREPARES:
                return $this->cachePreparedStatements;
                break;
            case self::ATTR_CONNECTION_NAME:
                return $this->_connectionName;
                break;
            default:
                return parent::getAttribute($attribute);
        }
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * Overrides PDO::prepare() in order to:
     *  - Add logging and query counting if logging is true.
     *  - Add query caching support if the PDO::ATTR_CACHE_PREPARES was set to true.
     *
     * @param string $sql            This must be a valid SQL statement for the target database server.
     * @param array  $driver_options One $array or more key => value pairs to set attribute values
     *                                      for the PDOStatement object that this method returns.
     *
     * @return \PDOStatement
     */
    public function prepare($sql, $driver_options = array()) {
        $debug = $this->getDebugSnapshot();

        if ($this->cachePreparedStatements) {
            $k = md5($sql);
            if (!isset($this->preparedStatements[$k])) {
                $return = parent::prepare($sql, $driver_options);
                $this->preparedStatements[$k] = $return;
            } else {
                $return = $this->preparedStatements[$k];
            }
        } else {
            $return = parent::prepare($sql, $driver_options);
        }

        $this->log('Prepare: ' .$sql, null, __METHOD__, $debug);

        return $return;
    }

    /**
     * Clears any stored prepared statements for this connection.
     */
    public function clearStatementCache()
    {
        $this->preparedStatements = array();
    }

    /**
     * Configures the PDOStatement class for this connection.
     *
     * @param string  $class
     * @param boolean $suppressError Whether to suppress an exception if the statement class cannot be set.
     *
     * @throws Exception if the statement class cannot be set (and $suppressError is false).
     */
    protected function configureStatementClass($class = 'PDOStatement', $suppressError = true) {
        // extending PDOStatement is only supported with non-persistent connections
        if (!$this->getAttribute(\PDO::ATTR_PERSISTENT)) {
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array($class, array($this)));
        } elseif (!$suppressError) {
            throw new Exception('Extending PDOStatement is not supported with persistent connections.');
        }
    }

    public function getAdapter() {
        return Manager::getAdapter($this->getAttribute(self::ATTR_CONNECTION_NAME));
    }

    public function createQuery() {
        return new Query($this);
    }

    public function executeQuery($query, array $params = array(), $types = array())
    {
        try {
            $begin = $this->getDebugSnapshot();
            if ($params) {
                $stmt = $this->prepare($query);
                if ($types) {
                    $this->_bindTypedValues($stmt, $params, $types);
                    $stmt->execute();
                } else {
                    $stmt->execute($params);
                }

            } else {
                $stmt = $this->query($query);

            }
            $end = $this->getDebugSnapshot();
            Profiler::logSqlQueries($query, $begin, $end, $params);
        } catch (\Exception $ex) {
            throw new Exception("An exception occurred while executing '{$query}'"
                .(($params)? ' with params ' .json_encode($params): ''), 500, $ex);
        }

        return $stmt;
    }

    public function executeUpdate($query, array $params = array(), array $types = array()) {
        try {
            $begin = $this->getDebugSnapshot();
            if ($params) {
                $stmt = $this->prepare($query);
                if ($types) {
                    $this->_bindTypedValues($stmt, $params, $types);
                    $stmt->execute();
                } else {
                    $stmt->execute($params);
                }
                $result = $stmt->rowCount();
            } else {
                $result = $this->exec($query);
            }
            $end = $this->getDebugSnapshot();
            Profiler::logSqlQueries($query, $begin, $end, $params);
        } catch (\Exception $ex) {
            throw new Exception("An exception occurred while executing '{$query}'"
                .(($params)? ' with params ' .json_encode($params, JSON_UNESCAPED_UNICODE): ''), 500,  $ex);
        }

        return $result;
    }

    /**
     * Inserts a table row with specified data.
     *
     * @param $table
     * @param array $data An associative array containing column-value pairs.
     * @param array $types
     * @param array $types Types of the inserted data.
     * @internal param string $tableName The name of the table to insert data into.
     * @return integer The number of affected rows.
     */
    public function insert($table, array $data, array $types = array()) {
        $table = $this->getAdapter()->quoteIdentifierTable($table);

        // column names are specified as array keys
        $cols = array();
        $placeholders = array();

        foreach ($data as $columnName => $value) {
            $cols[] = $this->getAdapter()->quoteIdentifier($columnName);
            if ($value instanceof Expression) {
                unset($data[$columnName]);
                $placeholders[] = $value->expression;
            }
            else {
                $placeholders[] = '?';
            }
        }

        $query = 'INSERT INTO ' . $table
            . ' (' . implode(', ', $cols) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        return $this->executeUpdate($query, array_values($data), $types);
    }

    /**
     * Inserts a table many rows in one query.
     *
     * @param $table
     * @param array $data An associative array containing column-value pairs.
     * @internal param array $types Types of the inserted data.
     * @internal param string $tableName The name of the table to insert data into.
     * @return integer The number of affected rows.
     */
    public function insertMulti($table, array $data) {
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data[0]);

        $query = 'INSERT INTO ' .$this->getAdapter()->quoteIdentifierTable($table)
                    .'(' .implode(',', $columns).')'
                    .' VALUES ';
        $params = array();

        for ($i = 0, $size = sizeof($data); $i < $size; ++$i) {
            $placeholders = array();

            foreach ($data[$i] as $column => $value) {
                if ($i == 0) { //first index define specified columns
                    $columns[] = $this->getAdapter()->quoteIdentifierTable($column);
                }

                if ($value instanceof Expression) {
                    $placeholders[] = $value->expression;
                } else {
                    $placeholders[] = '?';
                    $params[] = $value;
                }
            }
            $query.= '(' .implode(',', $placeholders) .')';
            if ($i < ($size-1)) {
                $query .= ',';
            }
        }
        return $this->executeUpdate($query, $params);
    }

    /**
     * Executes an SQL UPDATE statement on a table.
     *
     * @param string $tableName The name of the table to update.
     * @param array $data
     * @param array $identifier The update criteria. An associative array containing column-value pairs.
     * @param array $types Types of the merged $data and $identifier arrays in that order.
     * @return integer The number of affected rows.
     */
    public function update($tableName, array $data, array $identifier, array $types = array())
    {
        $set = array();
        foreach ($data as $columnName => $value) {
            if ($value instanceof Expression) {
                $set[] = $this->getAdapter()->quoteIdentifier($columnName) . ' = ' .$value->expression;
                unset($data[$columnName]);
            } else {
                $set[] = $this->getAdapter()->quoteIdentifier($columnName) . ' = ?';
            }
        }

        $params = array_merge(array_values($data), array_values($identifier));

        $sql  = 'UPDATE ' . $this->getAdapter()->quoteIdentifierTable($tableName) . ' SET ' . implode(', ', $set)
            . ' WHERE ' . implode(' = ? AND ', array_keys($identifier))
            . ' = ?';

        return $this->executeUpdate($sql, $params, $types);
    }

    /**
     * Executes an SQL DELETE statement on a table.
     *
     * @param string $tableName The name of the table on which to delete.
     * @param array $identifier The deletion criteria. An associative array containing column-value pairs.
     * @return integer The number of affected rows.
     */
    public function delete($tableName, array $identifier)
    {
        $criteria = array();

        foreach (array_keys($identifier) as $columnName) {
            $criteria[] = $columnName . ' = ?';
        }

        $query = 'DELETE FROM ' . $this->getAdapter()->quoteIdentifierTable($tableName) . ' WHERE ' . implode(' AND ', $criteria);

        return $this->executeUpdate($query, array_values($identifier));
    }

    /**
     * Binds a set of parameters, some or all of which are typed with a PDO binding type to a given statement.
     *
     * @param \PDOStatement $stmt The statement to bind the values to.
     * @param array $params The map/list of named/positional parameters.
     * @param array $types The parameter types (PDO binding types).
     * @internal Duck-typing used on the $stmt parameter to support driver statements as well as
     *           raw PDOStatement instances.
     */
    private function _bindTypedValues(\PDOStatement $stmt, array $params, array $types)
    {
        // Check whether parameters are positional or named. Mixing is not allowed, just like in PDO.
        if (is_int(key($params))) {
            // Positional parameters
            $typeOffset = array_key_exists(0, $types) ? -1 : 0;
            $bindIndex = 1;
            foreach ($params as $value) {
                $typeIndex = $bindIndex + $typeOffset;
                if (isset($types[$typeIndex])) {
                    $type = $types[$typeIndex];
                    $stmt->bindValue($bindIndex, $value, $type);
                } else {
                    $stmt->bindValue($bindIndex, $value);
                }
                ++$bindIndex;
            }
        } else {
            // Named parameters
            foreach ($params as $name => $value) {
                if (isset($types[$name])) {
                    $type = $types[$name];
                    $stmt->bindValue($name, $value, $type);
                } else {
                    $stmt->bindValue($name, $value);
                }
            }
        }
    }

    /**
     * Returns a snapshot of the current values of some functions useful in debugging.
     *
     * @return array
     *
     * @throws Exception
     */
    public function getDebugSnapshot() {
        if (!ConfigHandler::get('debug')) {
            return null;
        }

        return array(
            'microtime'             => microtime(true),
            'memory_get_usage'      => memory_get_usage(),
            'memory_get_peak_usage' => memory_get_peak_usage(),
        );
    }

    /**
     * Logs the method call or SQL using the Propel::log() method or a registered logger class.
     *
     * @param string  $msg           Message to log.
     * @param integer $level         Log level to use; will use self::setLogLevel() specified level by default.
     * @param string  $methodName    Name of the method whose execution is being logged.
     * @param array   $debugSnapshot Previous return value from self::getDebugSnapshot().
     */
    public function log($msg, $level = null, $methodName = null, array $debugSnapshot = null) {
        // If logging has been specifically disabled, this method won't do anything
        if (!ConfigHandler::get('debug')) {
            return;
        }

        // We won't log empty messages
        if (!$msg) {
            return;
        }
    }
}
