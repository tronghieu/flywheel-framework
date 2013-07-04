<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/20/13
 * Time: 11:35 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Validator;


class MinValueValidator extends BaseValidator {
    /**
     * @see       BaseValidator::isValid()
     *
     * @param $map
     * @param mixed        $value
     *
     * @return boolean
     */
    public function isValid($map, $value) {
        if (is_null($value) == false && is_numeric($value)) {
            return intval($value) >= intval($map);
        }

        return false;
    }
}