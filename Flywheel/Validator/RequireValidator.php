<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/20/13
 * Time: 11:36 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Validator;


class RequireValidator extends BaseValidator {
    /**
     * @see       BaseValidator::isValid()
     *
     * @param mixed $map
     * @param string       $str
     *
     * @return boolean
     */
    public function isValid($map, $str) {
        return ($str !== null && $str !== "");
    }
}