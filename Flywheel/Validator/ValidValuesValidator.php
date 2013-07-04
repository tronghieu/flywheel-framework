<?php
namespace Flywheel\Validator;


class ValidValuesValidator extends BaseValidator {
    /**
     * @see BaseValidator::isValid()
     *
     * @param mixed $map
     * @param string       $str
     *
     * @return boolean
     */
    public function isValid($map, $str) {
        return in_array($str, preg_split("/[|,]/", $map));
    }
}