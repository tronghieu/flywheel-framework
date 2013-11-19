<?php
namespace Flywheel\Validator;


class MaxLengthValidator extends BaseValidator {
    /**
     * @param mixed $map
     * @param string       $str
     *
     * @return boolean
     */
    public function isValid($map, $str) {
        $len = function_exists('mb_strlen') ? mb_strlen($str) : strlen($str);
        return $len <= intval($map);
    }
}