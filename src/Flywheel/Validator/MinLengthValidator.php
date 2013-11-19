<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/20/13
 * Time: 11:34 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Validator;


class MinLengthValidator extends BaseValidator {
    /**
     * @see BaseValidator::isValid()
     *
     * @param mixed $map
     * @param string       $str
     *
     * @return boolean
     */
    public function isValid($map, $str)
    {
        $len = function_exists('mb_strlen') ? mb_strlen($str) : strlen($str);

        return $len >= intval($map);
    }
}