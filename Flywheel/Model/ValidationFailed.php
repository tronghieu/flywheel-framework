<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/21/13
 * Time: 12:35 AM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Model;


class ValidationFailed {
    /** Column name in tablename.COLUMN_NAME format */
    private $_colName;

    /** Message to display to user. */
    private $_message = array();

    /** Validator object that caused this to fail. */
    private $_validator;

    /**
     * Construct a new ValidationFailed object.
     *
     * @param string $colName   Column name.
     * @param string $message   Message to display to user.
     * @param object $validator The Validator that caused this column to fail.
     */
    public function __construct($colName, $message, $validator = null)
    {
        $this->_colName = $colName;
        $this->_message[] = $message;
        $this->_validator = $validator;
    }

    /**
     * Set the column name.
     *
     * @param string $v
     */
    public function setColumn($v) {
        $this->_colName = $v;
    }

    /**
     * Gets the column name.
     *
     * @return string Qualified column name (tablename.COLUMN_NAME)
     */
    public function getColumn() {
        return $this->_colName;
    }

    /**
     * Set the message for the validation failure.
     *
     * @param string $v
     */
    public function setMessage($v) {
        $this->_message[] = $v;
    }

    /**
     * Gets the message for the validation failure.
     *
     * @return string
     */
    public function getMessage() {
        return implode('.', $this->_message);
    }

    /**
     * Set the validator object that caused this to fail.
     *
     * @param object $v
     */
    public function setValidator($v) {
        $this->_validator = $v;
    }

    /**
     * Gets the validator object that caused this to fail.
     *
     * @return object
     */
    public function getValidator() {
        return $this->_validator;
    }

    /**
     * "magic" method to get string representation of object.
     * Maybe someday PHP5 will support the invoking this method automatically
     * on (string) cast.  Until then it's pretty useless.
     *
     * @return string
     */
    public function __toString() {
        return $this->getMessage();
    }
}