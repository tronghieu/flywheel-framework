<?php
namespace Flywheel\Validator;

interface IValidator {
    /**
     * Determine whether a value meets the criteria specified
     *
     * @param mixed $map A column map object for the column to be validated.
     * @param string       $str a <code>String</code> to be tested
     *
     * @return mixed TRUE if valid, error message otherwise
     */
    public function isValid($map, $str);
}