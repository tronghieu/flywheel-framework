<?php
namespace Flywheel\Validator;


class MatchValidator extends BaseValidator {
    /**
     * Prepares the regular expression entered in the XML
     * for use with preg_match().
     *
     * @param  string $exp
     *
     * @return string Prepared regular expression.
     */
    private function prepareRegexp($exp) {
        // remove surrounding '/' marks so that they don't get escaped in next step
        if ($exp{0} !== '/' || $exp{strlen($exp) - 1} !== '/') {
            $exp = '/' . $exp . '/';
        }

        // if they did not escape / chars; we do that for them
        $exp = preg_replace('/([^\\\])\/([^$])/', '$1\/$2', $exp);

        return $exp;
    }

    /**
     * Whether the passed string matches regular expression.
     *
     * @param mixed $map
     * @param string       $str
     *
     * @return boolean
     */
    public function isValid($map, $str) {
        if ($str) {
            return (preg_match($this->prepareRegexp($map), $str) != 0);
        }
        return true;
    }
}