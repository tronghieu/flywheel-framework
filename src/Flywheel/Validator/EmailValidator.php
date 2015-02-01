<?php
namespace Flywheel\Validator;

class EmailValidator extends BaseValidator {
    /**
     * @see       BaseValidator::isValid()
     *
     * @param mixed $map
     * @param string       $str
     *
     * @return boolean
     */

    public function isValid($map, $str) {
        if ($str) {
            return preg_match('/^([a-z0-9]+([_\.\-]{1}[a-z0-9]+)*){1}([@]){1}([a-z0-9]+([_\-]{1}[a-z0-9]+)*)+(([\.]{1}[a-z]{2,6}){0,3}){1}$/i', $str);
        }
        return true;
    }
}