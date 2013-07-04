<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/20/13
 * Time: 11:35 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Validator;


class NotMatchValidator extends BaseValidator {
    /**
     * Prepares the regular expression entered in the XML
     * for use with preg_match().
     *
     * @param  string $exp
     *
     * @return string
     */
    private function _prepareRegexp($exp) {
        // remove surrounding '/' marks so that they don't get escaped in next step
        if ($exp{0} !== '/' || $exp{strlen($exp) - 1} !== '/') {
            $exp = '/' . $exp . '/';
        }

        // if they did not escape / chars; we do that for them
        $exp = preg_replace('/([^\\\])\/([^$])/', '$1\/$2', $exp);

        return $exp;
    }

    /**
     * @see       BaseValidator::isValid()
     *
     * @param mixed $map
     * @param string       $str
     *
     * @return boolean
     */
    public function isValid($map, $str) {
        return (preg_match($this->_prepareRegexp($map), $str) == 0);
    }
}