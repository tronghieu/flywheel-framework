<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/20/13
 * Time: 11:33 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Validator;


class MaxValueValidator extends BaseValidator {
    /**
     * @see       BaseValidator::isValid()
     *
     * @param mixed $map
     * @param mixed        $value
     *
     * @return boolean
     */
    public function isValid($map, $value) {
        if (is_null($value) == false && is_numeric($value) == true) {
            return intval($value) <= intval($map);
        }

        return false;
    }
}