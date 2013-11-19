<?php
namespace Flywheel\Db\Adapter;
class MySQLi extends BaseAdapter
{
    /**
     * @see       DBAdapter::quoteIdentifier()
     *
     * @param  string $text
     * @return string
     */
    public function quoteIdentifier($text)
    {
        return '`' . $text . '`';
    }

    /**
     * @see       DBAdapter::quoteIdentifierTable()
     *
     * @param  string $table
     * @return string
     */
    public function quoteIdentifierTable($table)
    {
        // e.g. 'database.table alias' should be escaped as '`database`.`table` `alias`'
        return '`' . strtr($table, array('.' => '`.`', ' ' => '` `')) . '`';
    }

    /**
     * This method is used to ignore case.
     *
     * @param  string $in The string to transform to upper case.
     * @return string The upper case string.
     */
    public function toUpperCase($in)
    {
        return "UPPER(" . $in . ")";
    }

    /**
     * This method is used to ignore case.
     *
     * @param  string $in The string whose case to ignore.
     * @return string The string in a case that can be ignored.
     */
    public function ignoreCase($in)
    {
        return "UPPER(" . $in . ")";
    }


    /**
     * @see \Flywheel\DB\Adapter\BaseAdapter::applyLimit()
     *
     * @param string  $sql
     * @param integer $offset
     * @param integer $limit
     * @return string
     */
    public function applyLimit(&$sql, $offset, $limit)
    {
        if ($offset > 0) {
            $sql .= " LIMIT " . $offset .(($limit > 0 )? ',' . $limit : '');
        } elseif ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        return $sql;
    }

    /**
     * @see       DBAdapter::random()
     *
     * @param  string $seed
     * @return string
     */
    public function random($seed = null)
    {
        return 'rand('.((int) $seed).')';
    }

    /**
     * @param $dbType
     * @return \PDO::PARAM_* int
     */
    public function getPDOParam($dbType) {
        switch($dbType) {
            case 'integer':
                return \PDO::PARAM_INT;
            case 'boolean':
                return \PDO::PARAM_BOOL;
            case 'blob':
            case 'text':
            case 'string':
            default:
                return \PDO::PARAM_STR;
        }
    }
}
