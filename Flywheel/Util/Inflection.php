<?php
namespace Flywheel\Util;
class Inflection
{
    /**
     * Convert word from Camel Case to Hungary Notation. "ModelName" to "model_name"
     * @param string $word camel case word
     * @return string word by Hungary Notation
     */
    public static function hungaryNotationToCamel($word) {
        $word = preg_replace('/[$]/', '_', $word);
        return preg_replace_callback('~(_?)(_)([\w])~', "self::classifyCallback", ucfirst(strtolower($word)));
    }

    /**
     * convert word from Camel Case to Hungary Notation.
     *  e.g: "ModelName" to "model_name"
     *
     * @static
     * @param $word
     * @return string
     */
    public static function camelCaseToHungary($word) {
        return strtolower(preg_replace('~(?<=\\w)([A-Z])~', '_$1', $word));
    }

    /**
     * camelize
     * alias of @see self::hungaryNotationToCamel()
     * @param string	$words
     *
     * @return string
     */
    public static function camelize($words) {
        $words = ucwords(str_replace('_' , ' ', $words));
        $words = str_replace(' ', '', $words);
        return ltrim($words, '_');
    }

    /**
     * Callback function to classify a classname properly.
     *
     * @param  array  $matches  An array of matches from a pcre_replace call
     * @return string $string   A string with matches 1 and matches 3 in upper case.
     */
    public static function classifyCallback($matches)
    {
        return $matches[1] . strtoupper($matches[3]);
    }
}
