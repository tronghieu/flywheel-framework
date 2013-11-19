<?php
namespace Flywheel\Db\Expression;
class Composite implements \Countable
{
    /**
     * Constant that represents an AND composite expression
     */
    const TYPE_AND = 'AND';

    /**
     * Constant that represents an OR composite expression
     */
    const TYPE_OR  = 'OR';

    /**
     * @var string Holds the instance type of composite expression
     */
    private $type;

    /**
     * @var array Each expression part of the composite expression
     */
    private $parts = array();

    /**
     * Constructor.
     *
     * @param string $type Instance type of composite expression
     * @param array $parts Composition of expressions to be joined on composite expression
     */
    public function __construct($type, array $parts = array())
    {
        $this->type = $type;

        $this->addMultiple($parts);
    }

    /**
     * Adds multiple parts to composite expression.
     *
     * @param array $parts
     *
     * @return Composite
     */
    public function addMultiple(array $parts = array())
    {
        foreach ((array) $parts as $part) {
            $this->add($part);
        }

        return $this;
    }

    /**
     * Adds an expression to composite expression.
     *
     * @param mixed $part
     * @return Composite
     */
    public function add($part)
    {
        if ( ! empty($part) || ($part instanceof self && $part->count() > 0)) {
            $this->parts[] = $part;
        }

        return $this;
    }

    /**
     * Retrieves the amount of expressions on composite expression.
     *
     * @return integer
     */
    public function count()
    {
        return count($this->parts);
    }

    /**
     * Retrieve the string representation of this composite expression.
     *
     * @return string
     */
    public function __toString()
    {
        if (count($this->parts) === 1) {
            return (string) $this->parts[0];
        }

        return '(' . implode(') ' . $this->type . ' (', $this->parts) . ')';
    }

    /**
     * Return type of this composite expression (AND/OR)
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
