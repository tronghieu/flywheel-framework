<?php
use Flywheel\Factory;

if (false == function_exists('array_zip')) {
    /**
     * @return array
     */
    function array_zip() {
        $args = func_get_args();
        $zipped = array();
        $n = count($args);
        for ($i=0; $i<$n; ++$i) {
            reset($args[$i]);
        }
        while ($n) {
            $tmp = array();
            for ($i=0; $i<$n; ++$i) {
                if (key($args[$i]) === null) {
                    break 2;
                }
                $tmp[] = current($args[$i]);
                next($args[$i]);
            }
            $zipped[] = $tmp;
        }
        return $zipped;
    }
}

/**
 * @param $id
 * @param array $parameters
 * @param string $domain
 * @param null $locale
 * @return string
 */
function t($id, array $parameters = array(), $domain = 'messages', $locale = null) {
    if (($translator = Factory::getTranslator())) {
        return $translator = Factory::getTranslator()->trans($id, $parameters, $domain, $locale);
    }

    return $id;
}

/**
 * display translation message @see t()
 * @param $id
 * @param array $parameters
 * @param string $domain
 * @param null $locale
 */

function td($id, array $parameters = array(), $domain = 'messages', $locale = null) {
    echo t($id, $parameters, $domain, $locale);
}