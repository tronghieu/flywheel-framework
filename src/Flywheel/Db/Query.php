<?php
namespace Flywheel\Db;
use \Flywheel\Db\Expression\Composite;
class Query
{
    /* The query types. */
    const SELECT = 0;
    const DELETE = 1;
    const UPDATE = 2;
    const COUNT = 3;
    const SUM = 4;

    /** The builder states. */
    const STATE_DIRTY = 0;
    const STATE_CLEAN = 1;

    /**
     * @var \Flywheel\Db\Connection $_connection
     */
    private $_connection = null;

    /**
     * @var array The array of SQL parts collected.
     */
    private $_sqlParts = array(
        'select'  => array('*'),
        'from'    => array(),
        'join'    => array(),
        'set'     => array(),
        'where'   => null,
        'groupBy' => array(),
        'having'  => null,
        'orderBy' => array()
    );

    /**
     * @var string The complete SQL string for this query.
     */
    private $_sql;

    /**
     * @var array The query parameters.
     */
    private $params = array();

    /**
     * @var array The parameter type map of this query.
     */
    private $paramTypes = array();

    /**
     * @var integer The type of query this is. Can be select, update or delete.
     */
    private $_type = self::SELECT;

    /**
     * @var integer The state of the query object. Can be dirty or clean.
     */
    private $_state = self::STATE_CLEAN;

    /**
     * @var integer The index of the first result to retrieve.
     */
    private $_firstResult = null;

    /**
     * @var integer The maximum number of results to retrieve.
     */
    private $_maxResults = null;

    /**
     * The counter of bound parameters used with {@see bindValue)
     *
     * @var int
     */
    private $_boundCounter = 0;

    private $_selectQueryCallback;

    /**
     * Initializes a new <tt>Query</tt>.
     *
     * @param \Flywheel\Db\Connection $connection Connection
     */
    public function __construct(Connection $connection)
    {
        $this->_connection = $connection;
    }

    /**
     * Get the type of the currently built query.
     *
     * @return integer
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Get the associated DBAL Connection for this query builder.
     *
     * @return \Flywheel\Db\Connection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * Get the state of this query builder instance.
     *
     * @return integer Either Query::STATE_DIRTY or Query::STATE_CLEAN.
     */
    public function getState() {
        return $this->_state;
    }

    /**
     * Execute this query using the bound parameters and their types.
     *
     * Uses {@see Connection::executeQuery} for select statements and {@see Connection::executeUpdate}
     * for insert, update and delete statements.
     *
     * @return \PDOStatement
     */
    public function execute()
    {
        if ($this->_type == self::SELECT || $this->_type == self::COUNT || $this->_type == self::SUM) {
            $result = $this->_connection->executeQuery($this->getSQL(), $this->params, $this->paramTypes);
            if ($this->_type == self::COUNT || $this->_type == self::SUM) {
                $result = $result->fetch(\PDO::FETCH_ASSOC);
                return $result['result'];
            }

            if ($this->_selectQueryCallback) {
                return call_user_func($this->_selectQueryCallback, $result);
            }

            return $result;
        } else {
            return $this->_connection->executeUpdate($this->getSQL(), $this->params, $this->paramTypes);
        }
    }

    /**
     * Get the complete SQL string formed by the current specifications of this Query.
     *
     * <code>
     *     $qb = $em->createQuery()
     *         ->select('u')
     *         ->from('User', 'u')
     *     echo $qb->getSQL(); // SELECT u FROM User u
     * </code>
     *
     * @return string The sql query string.
     */
    public function getSQL()
    {
        if ($this->_sql !== null && $this->_state === self::STATE_CLEAN) {
            return $this->_sql;
        }

        $sql = '';

        switch ($this->_type) {
            case self::DELETE:
                $sql = $this->getSQLForDelete();
                break;

            case self::UPDATE:
                $sql = $this->getSQLForUpdate();
                break;

            case self::SELECT:
            default:
                $sql = $this->getSQLForSelect();
                break;
        }

        $this->_state = self::STATE_CLEAN;
        $this->_sql = $sql;

        return $sql;
    }

    /**
     * Sets a query parameter for the query being constructed.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.id = :user_id')
     *         ->setParameter(':user_id', 1);
     * </code>
     *
     * @param string|integer $key The parameter position or name.
     * @param mixed $value The parameter value.
     * @param string|null $type PDO::PARAM_*
     * @return Query This Query instance.
     */
    public function setParameter($key, $value, $type = null)
    {
        if ($type !== null) {
            $this->paramTypes[$key] = $type;
        }

        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Sets a collection of query parameters for the query being constructed.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.id = :user_id1 OR u.id = :user_id2')
     *         ->setParameters(array(
     *             ':user_id1' => 1,
     *             ':user_id2' => 2
     *         ));
     * </code>
     *
     * @param array $params The query parameters to set.
     * @param array $types  The query parameters types to set.
     * @return Query This Query instance.
     */
    public function setParameters(array $params, array $types = array())
    {
        $this->paramTypes = $types;
        $this->params = $params;

        return $this;
    }

    /**
     * Gets all defined query parameters for the query being constructed.
     *
     * @return array The currently defined query parameters.
     */
    public function getParameters()
    {
        return $this->params;
    }

    /**
     * Gets a (previously set) query parameter of the query being constructed.
     *
     * @param mixed $key The key (index or name) of the bound parameter.
     * @return mixed The value of the bound parameter.
     */
    public function getParameter($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    /**
     * Sets the position of the first result to retrieve (the "offset").
     *
     * @param integer $firstResult The first result to return.
     * @return Query This Query instance.
     */
    public function setFirstResult($firstResult)
    {
        $this->_state = self::STATE_DIRTY;
        $this->_firstResult = $firstResult;
        return $this;
    }

    /**
     * Gets the position of the first result the query object was set to retrieve (the "offset").
     * Returns NULL if {@link setFirstResult} was not applied to this Query.
     *
     * @return integer The position of the first result.
     */
    public function getFirstResult()
    {
        return $this->_firstResult;
    }

    /**
     * Sets the maximum number of results to retrieve (the "limit").
     *
     * @param integer $maxResults The maximum number of results to retrieve.
     * @return Query This Query instance.
     */
    public function setMaxResults($maxResults)
    {
        $this->_state = self::STATE_DIRTY;
        $this->_maxResults = $maxResults;
        return $this;
    }

    /**
     * Gets the maximum number of results the query object was set to retrieve (the "limit").
     * Returns NULL if {@link setMaxResults} was not applied to this query builder.
     *
     * @return integer Maximum number of results.
     */
    public function getMaxResults()
    {
        return $this->_maxResults;
    }

    /**
     * Either appends to or replaces a single, generic query part.
     *
     * The available parts are: 'select', 'from', 'set', 'where',
     * 'groupBy', 'having' and 'orderBy'.
     *
     * @param string  $sqlPartName
     * @param string  $sqlPart
     * @param boolean $append
     * @return Query This Query instance.
     */
    public function add($sqlPartName, $sqlPart, $append = false)
    {
        $isArray = is_array($sqlPart);
        $isMultiple = is_array($this->_sqlParts[$sqlPartName]);

        if ($isMultiple && !$isArray) {
            $sqlPart = array($sqlPart);
        }

        $this->_state = self::STATE_DIRTY;

        if ($append) {
            if ($sqlPartName == "orderBy" || $sqlPartName == "groupBy" || $sqlPartName == "select" || $sqlPartName == "set") {
                foreach ($sqlPart as $part) {
                    $this->_sqlParts[$sqlPartName][] = $part;
                }
            } else if ($isArray && is_array($sqlPart[key($sqlPart)])) {
                $key = key($sqlPart);
                $this->_sqlParts[$sqlPartName][$key][] = $sqlPart[$key];
            } else if ($isMultiple) {
                $this->_sqlParts[$sqlPartName][] = $sqlPart;
            } else {
                $this->_sqlParts[$sqlPartName] = $sqlPart;
            }

            return $this;
        }

        $this->_sqlParts[$sqlPartName] = $sqlPart;

        return $this;
    }

    /**
     * Specifies an item that is to be returned in the query result.
     * Replaces any previously specified selections, if any.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.id', 'p.id')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'u.id = p.user_id');
     * </code>
     *
     * @param mixed $select The selection expressions.
     * @return Query This Query instance.
     */
    public function select($select = null)
    {
        $this->_type = self::SELECT;

        if (empty($select)) {
            return $this;
        }

        $selects = is_array($select) ? $select : func_get_args();

        return $this->add('select', $selects, false);
    }

    public function count($col = '*') {
        $this->_type = self::COUNT;
        return $this->add('select', array("COUNT({$col}) AS result"));
    }

    public function sum($col = 'id'){
        $this->_type = self::SUM;
        return $this->add('select', array("SUM({$col}) AS result"));
    }

    /**
     * Adds an item that is to be returned in the query result.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.id')
     *         ->addSelect('p.id')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'u.id = p.user_id');
     * </code>
     *
     * @param mixed $select The selection expression.
     * @return Query This Query instance.
     */
    public function addSelect($select = null)
    {
        $this->_type = self::SELECT;

        if (empty($select)) {
            return $this;
        }

        $selects = is_array($select) ? $select : func_get_args();

        return $this->add('select', $selects, true);
    }

    /**
     * Turns the query being built into a bulk delete query that ranges over
     * a certain table.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->delete('users', 'u')
     *         ->where('u.id = :user_id');
     *         ->setParameter(':user_id', 1);
     * </code>
     *
     * @param string $delete The table whose rows are subject to the deletion.
     * @param string $alias The table alias used in the constructed query.
     * @return Query This Query instance.
     */
    public function delete($delete = null, $alias = null)
    {
        $this->_type = self::DELETE;

        if ( ! $delete) {
            return $this;
        }

        return $this->add('from', array(
            'table' => $delete,
            'alias' => $alias
        ));
    }

    /**
     * Turns the query being built into a bulk update query that ranges over
     * a certain table
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->update('users', 'u')
     *         ->set('u.password', md5('password'))
     *         ->where('u.id = ?');
     * </code>
     *
     * @param string $update The table whose rows are subject to the update.
     * @param string $alias The table alias used in the constructed query.
     * @return Query This Query instance.
     */
    public function update($update = null, $alias = null)
    {
        $this->_type = self::UPDATE;

        if ( ! $update) {
            return $this;
        }

        return $this->add('from', array(
            'table' => $update,
            'alias' => $alias
        ));
    }

    /**
     * Create and add a query root corresponding to the table identified by the
     * given alias, forming a cartesian product with any existing query roots.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.id')
     *         ->from('users', 'u')
     * </code>
     *
     * @param string $from   The table
     * @param string $alias  The alias of the table
     * @return Query This Query instance.
     */
    public function from($from, $alias = null)
    {
        return $this->add('from', array(
            'table' => $from,
            'alias' => $alias
        ), true);
    }

    /**
     * Creates and adds a join to the query.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->join('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause
     * @param string $join The table name to join
     * @param string $alias The alias of the join table
     * @param string $condition The condition for the join
     * @return Query This Query instance.
     */
    public function join($fromAlias, $join, $alias, $condition = null)
    {
        return $this->innerJoin($fromAlias, $join, $alias, $condition);
    }

    /**
     * Creates and adds a join to the query.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->innerJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause
     * @param string $join The table name to join
     * @param string $alias The alias of the join table
     * @param string $condition The condition for the join
     * @return Query This Query instance.
     */
    public function innerJoin($fromAlias, $join, $alias, $condition = null)
    {
        return $this->add('join', array(
            $fromAlias => array(
                'joinType'      => 'inner',
                'joinTable'     => $join,
                'joinAlias'     => $alias,
                'joinCondition' => $condition
            )
        ), true);
    }

    /**
     * Creates and adds a left join to the query.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause
     * @param string $join The table name to join
     * @param string $alias The alias of the join table
     * @param string $condition The condition for the join
     * @return Query This Query instance.
     */
    public function leftJoin($fromAlias, $join, $alias, $condition = null)
    {
        return $this->add('join', array(
            $fromAlias => array(
                'joinType'      => 'left',
                'joinTable'     => $join,
                'joinAlias'     => $alias,
                'joinCondition' => $condition
            )
        ), true);
    }

    /**
     * Creates and adds a right join to the query.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->rightJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause
     * @param string $join The table name to join
     * @param string $alias The alias of the join table
     * @param string $condition The condition for the join
     * @return Query This Query instance.
     */
    public function rightJoin($fromAlias, $join, $alias, $condition = null)
    {
        return $this->add('join', array(
            $fromAlias => array(
                'joinType'      => 'right',
                'joinTable'     => $join,
                'joinAlias'     => $alias,
                'joinCondition' => $condition
            )
        ), true);
    }

    /**
     * Sets a new value for a column in a bulk update query.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->update('users', 'u')
     *         ->set('u.password', md5('password'))
     *         ->where('u.id = ?');
     * </code>
     *
     * @param string $key The column to set.
     * @param string $value The value, expression, placeholder, etc.
     * @return Query This Query instance.
     */
    public function set($key, $value)
    {
        return $this->add('set', $key .' = ' . $value, true);
    }

    /**
     * Specifies one or more restrictions to the query result.
     * Replaces any previously specified restrictions, if any.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->where('u.id = ?');
     *
     *     // You can optionally programatically build and/or expressions
     *     $qb = $conn->createQuery();
     *
     *     $or = $qb->expr()->orx();
     *     $or->add($qb->expr()->eq('u.id', 1));
     *     $or->add($qb->expr()->eq('u.id', 2));
     *
     *     $qb->update('users', 'u')
     *         ->set('u.password', md5('password'))
     *         ->where($or);
     * </code>
     *
     * @param mixed $predicates The restriction predicates.
     * @return Query This Query instance.
     */
    public function where($predicates)
    {
        if ( ! (func_num_args() == 1 && $predicates instanceof Composite) ) {
            $predicates = new Composite(Composite::TYPE_AND, func_get_args());
        }

        return $this->add('where', $predicates);
    }

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * conjunction with any previously specified restrictions.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.username LIKE ?')
     *         ->andWhere('u.is_active = 1');
     * </code>
     *
     * @param mixed $where The query restrictions.
     * @return Query This Query instance.
     * @see where()
     */
    public function andWhere($where)
    {
        $where = $this->getQueryPart('where');
        $args = func_get_args();

        if ($where instanceof Composite && $where->getType() === Composite::TYPE_AND) {
            $where->addMultiple($args);
        } else {
            array_unshift($args, $where);
            $where = new Composite(Composite::TYPE_AND, $args);
        }

        return $this->add('where', $where, true);
    }

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * disjunction with any previously specified restrictions.
     *
     * <code>
     *     $qb = $em->createQuery()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->where('u.id = 1')
     *         ->orWhere('u.id = 2');
     * </code>
     *
     * @param mixed $where The WHERE statement
     * @return Query $qb
     * @see where()
     */
    public function orWhere($where)
    {
        $where = $this->getQueryPart('where');
        $args = func_get_args();

        if ($where instanceof Composite && $where->getType() === Composite::TYPE_OR) {
            $where->addMultiple($args);
        } else {
            array_unshift($args, $where);
            $where = new Composite(Composite::TYPE_OR, $args);
        }

        return $this->add('where', $where, true);
    }

    /**
     * Specifies a grouping over the results of the query.
     * Replaces any previously specified groupings, if any.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.id');
     * </code>
     *
     * @param mixed $groupBy The grouping expression.
     * @return Query This Query instance.
     */
    public function groupBy($groupBy)
    {
        if (empty($groupBy)) {
            return $this;
        }

        $groupBy = is_array($groupBy) ? $groupBy : func_get_args();

        return $this->add('groupBy', $groupBy, false);
    }


    /**
     * Adds a grouping expression to the query.
     *
     * <code>
     *     $qb = $conn->createQuery()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.lastLogin');
     *         ->addGroupBy('u.createdAt')
     * </code>
     *
     * @param mixed $groupBy The grouping expression.
     * @return Query This Query instance.
     */
    public function addGroupBy($groupBy)
    {
        if (empty($groupBy)) {
            return $this;
        }

        $groupBy = is_array($groupBy) ? $groupBy : func_get_args();

        return $this->add('groupBy', $groupBy, true);
    }

    /**
     * Specifies a restriction over the groups of the query.
     * Replaces any previous having restrictions, if any.
     *
     * @param mixed $having The restriction over the groups.
     * @return Query This Query instance.
     */
    public function having($having)
    {
        if ( ! (func_num_args() == 1 && $having instanceof Composite)) {
            $having = new Composite(Composite::TYPE_AND, func_get_args());
        }

        return $this->add('having', $having);
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * conjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to append.
     * @return Query This Query instance.
     */
    public function andHaving($having)
    {
        $having = $this->getQueryPart('having');
        $args = func_get_args();

        if ($having instanceof Composite && $having->getType() === Composite::TYPE_AND) {
            $having->addMultiple($args);
        } else {
            array_unshift($args, $having);
            $having = new Composite(Composite::TYPE_AND, $args);
        }

        return $this->add('having', $having);
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * disjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to add.
     * @return Query This Query instance.
     */
    public function orHaving($having)
    {
        $having = $this->getQueryPart('having');
        $args = func_get_args();

        if ($having instanceof Composite && $having->getType() === Composite::TYPE_OR) {
            $having->addMultiple($args);
        } else {
            array_unshift($args, $having);
            $having = new Composite(Composite::TYPE_OR, $args);
        }

        return $this->add('having', $having);
    }

    /**
     * Specifies an ordering for the query results.
     * Replaces any previously specified orderings, if any.
     *
     * @param string $sort The ordering expression.
     * @param string $order The ordering direction.
     * @return Query This Query instance.
     */
    public function orderBy($sort, $order = null)
    {
        return $this->add('orderBy', $sort . ' ' . (! $order ? 'ASC' : $order), false);
    }

    /**
     * Adds an ordering to the query results.
     *
     * @param string $sort The ordering expression.
     * @param string $order The ordering direction.
     * @return Query This Query instance.
     */
    public function addOrderBy($sort, $order = null)
    {
        return $this->add('orderBy', $sort . ' ' . (! $order ? 'ASC' : $order), true);
    }

    /**
     * Get a query part by its name.
     *
     * @param string $queryPartName
     * @return mixed $queryPart
     */
    public function getQueryPart($queryPartName)
    {
        return $this->_sqlParts[$queryPartName];
    }

    /**
     * Get all query parts.
     *
     * @return array $sqlParts
     */
    public function getQueryParts()
    {
        return $this->_sqlParts;
    }

    /**
     * Reset SQL parts
     *
     * @param array $queryPartNames
     * @return Query
     */
    public function resetQueryParts($queryPartNames = null)
    {
        if (is_null($queryPartNames)) {
            $queryPartNames = array_keys($this->_sqlParts);
        }

        foreach ($queryPartNames as $queryPartName) {
            $this->resetQueryPart($queryPartName);
        }

        return $this;
    }

    /**
     * Reset single SQL part
     *
     * @param string $queryPartName
     * @return Query
     */
    public function resetQueryPart($queryPartName)
    {
        $this->_sqlParts[$queryPartName] = is_array($this->_sqlParts[$queryPartName])
            ? array() : null;

        $this->_state = self::STATE_DIRTY;

        return $this;
    }

    private function getSQLForSelect()
    {
        $query = 'SELECT ' . implode(', ', $this->_sqlParts['select']) . ' FROM ';

        $fromClauses = array();

        // Loop through all FROM clauses
        foreach ($this->_sqlParts['from'] as $from) {
            $table = $from['table'];
            if( strpos($table, '`') === false ) $table = '`'.$table.'`';

            $fromClause = $table . ' ' . $from['alias'];

            if (isset($this->_sqlParts['join'][$from['alias']])) {
                foreach ($this->_sqlParts['join'][$from['alias']] as $join) {
                    $fromClause .= ' ' . strtoupper($join['joinType'])
                        . ' JOIN ' . $join['joinTable'] . ' ' . $join['joinAlias']
                        . ' ON ' . ((string) $join['joinCondition']);
                }
            }

            $fromClauses[$from['alias']] = $fromClause;
        }

        // loop through all JOIN clasues for validation purpose
        foreach ($this->_sqlParts['join'] as $fromAlias => $joins) {
            if ( ! isset($fromClauses[$fromAlias]) ) {
                throw new Exception("The given alias '{$fromAlias}' is not part of " .
                    "any FROM clause table. The currently registered FROM-clause " .
                    "aliases are: " . implode(", ", array_keys($fromClauses)) . ". Join clauses " .
                    "are bound to from clauses to provide support for mixing of multiple " .
                    "from and join clauses.");
            }
        }

        $query .= implode(', ', $fromClauses)
            . ($this->_sqlParts['where'] !== null ? ' WHERE ' . ((string) $this->_sqlParts['where']) : '')
            . ($this->_sqlParts['groupBy'] ? ' GROUP BY ' . implode(', ', $this->_sqlParts['groupBy']) : '')
            . ($this->_sqlParts['having'] !== null ? ' HAVING ' . ((string) $this->_sqlParts['having']) : '')
            . ($this->_sqlParts['orderBy'] ? ' ORDER BY ' . implode(', ', $this->_sqlParts['orderBy']) : '');

        return ($this->_maxResults === null && $this->_firstResult == null)
            ? $query
            : $this->_connection->getAdapter()->applyLimit($query, $this->_firstResult, $this->_maxResults);
    }

    /**
     * Converts this instance into an UPDATE string in SQL.
     *
     * @return string
     */
    private function getSQLForUpdate()
    {
        $table = $this->_sqlParts['from']['table'] . ($this->_sqlParts['from']['alias'] ? ' ' . $this->_sqlParts['from']['alias'] : '');
        $query = 'UPDATE ' . $table
            . ' SET ' . implode(", ", $this->_sqlParts['set'])
            . ($this->_sqlParts['where'] !== null ? ' WHERE ' . ((string) $this->_sqlParts['where']) : '');

        $query = ($this->_maxResults === null && $this->_firstResult == null)
            ? $query
            : $this->_connection->getAdapter()->applyLimit($query, $this->_firstResult, $this->_maxResults);

        return $query;
    }

    /**
     * Converts this instance into a DELETE string in SQL.
     *
     * @return string
     */
    private function getSQLForDelete()
    {
        $table = $this->_sqlParts['from']['table'] . ($this->_sqlParts['from']['alias'] ? ' ' . $this->_sqlParts['from']['alias'] : '');
        $query = 'DELETE FROM ' . $table . ($this->_sqlParts['where'] !== null ? ' WHERE ' . ((string) $this->_sqlParts['where']) : '');

        $query = ($this->_maxResults === null && $this->_firstResult == null)
            ? $query
            : $this->_connection->getAdapter()->applyLimit($query, $this->_firstResult, $this->_maxResults);

        return $query;
    }

    /**
     * Gets a string representation of this Query which corresponds to
     * the final SQL query being constructed.
     *
     * @return string The string representation of this Query.
     */
    public function __toString()
    {
        return $this->getSQL();
    }

    /**
     * Create a new named parameter and bind the value $value to it.
     *
     * This method provides a shortcut for PDOStatement::bindValue
     * when using prepared statements.
     *
     * The parameter $value specifies the value that you want to bind. If
     * $placeholder is not provided bindValue() will automatically create a
     * placeholder for you. An automatic placeholder will be of the name
     * ':dcValue1', ':dcValue2' etc.
     *
     * For more information see {@link http://php.net/pdostatement-bindparam}
     *
     * Example:
     * <code>
     * $value = 2;
     * $q->eq( 'id', $q->bindValue( $value ) );
     * $stmt = $q->executeQuery(); // executed with 'id = 2'
     * </code>
     *
     * @license New BSD License
     * @link http://www.zetacomponents.org
     * @param mixed $value
     * @param mixed $type
     * @param string $placeHolder the name to bind with. The string must start with a colon ':'.
     * @return string the placeholder name used.
     */
    public function createNamedParameter( $value, $type = \PDO::PARAM_STR, $placeHolder = null )
    {
        if ( $placeHolder === null ) {
            $this->_boundCounter++;
            $placeHolder = ":flValue" . $this->_boundCounter;
        }
        $this->setParameter(substr($placeHolder, 1), $value, $type);

        return $placeHolder;
    }

    /**
     * Create a new positional parameter and bind the given value to it.
     *
     * Attention: If you are using positional parameters with the query builder you have
     * to be very careful to bind all parameters in the order they appear in the SQL
     * statement , otherwise they get bound in the wrong order which can lead to serious
     * bugs in your code.
     *
     * Example:
     * <code>
     *  $qb = $conn->createQuery();
     *  $qb->select('u.*')
     *     ->from('users', 'u')
     *     ->where('u.username = ' . $qb->createPositionalParameter('Foo', PDO::PARAM_STR))
     *     ->orWhere('u.username = ' . $qb->createPositionalParameter('Bar', PDO::PARAM_STR))
     * </code>
     *
     * @param  mixed $value
     * @param  mixed $type
     * @return string
     */
    public function createPositionalParameter($value, $type = \PDO::PARAM_STR)
    {
        $this->_boundCounter++;
        $this->setParameter($this->_boundCounter, $value, $type);
        return "?";
    }

    /**
     * @param callable $callback
     * @return $this
     * @throws Exception
     */
    public function setSelectQueryCallback($callback) {
        if (!is_callable($callback)) {
            throw new Exception("select query callback require callable method");
        }

        $this->_selectQueryCallback = $callback;
        return $this;
    }
}