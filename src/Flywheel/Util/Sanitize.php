<?php
namespace Flywheel\Util;

/** Washes strings from unwanted noise.
 *
 * Helpful methods to make unsafe strings usable.
 */
class Sanitize {
    /**
     * Removes any non-alphanumeric characters.
     *
     * @param string $string String to sanitize
     * @param array $allowed An array of additional characters that are not to be removed.
     * @return string Sanitized string
     */
    public static function sanitizeParanoid($string, $allowed = [])
    {
        $allow = null;
        if (!empty($allowed)) {
            foreach ($allowed as $value) {
                $allow .= "\\$value";
            }
        }

        if (is_array($string)) {
            $cleaned = array();
            foreach ($string as $key => $clean) {
                $cleaned[$key] = preg_replace("/[^{$allow}a-zA-Z0-9_-]/", '', $clean);
            }
        } else {
            $cleaned = preg_replace("/[^{$allow}a-zA-Z0-9]/", '', $string);
        }
        return $cleaned;
    }

    /**
     * Strips extra whitespace from output
     *
     * @param string $str String to sanitize
     * @return string whitespace sanitized string
     */
    public static function stripWhitespace($str) {
        return str_replace(' ', '', preg_replace('/\s{2,}/u', ' ', preg_replace('/[\n\r\t]+/', '', $str)));
    }

    /**
     * Strips image tags from output
     *
     * @param string $str String to sanitize
     * @return string Sting with images stripped.
     */
    public static function stripImages($str) {
        $str = preg_replace('/(<a[^>]*>)(<img[^>]+alt=")([^"]*)("[^>]*>)(<\/a>)/iu', '$1$3$5<br />', $str);
        $str = preg_replace('/(<img[^>]+alt=")([^"]*)("[^>]*>)/iu', '$2<br />', $str);
        $str = preg_replace('/<img[^>]*>/i', '', $str);
        return $str;
    }

    /**
     * Strips given text of all links (<a href=....).
     *
     * @param string $text Text
     * @return string The text without links
     */
    public static function stripLinks($text) {
        return preg_replace('|<a\s+[^>]+>|im', '', preg_replace('|<\/a>|im', '', $text));
    }

    /**
     * Strips scripts and stylesheets from output
     *
     * @param string $str String to sanitize
     * @return string String with <script>, <style>, <link> elements removed.
     */
    public static function stripScripts($str) {
        $str = preg_replace('/(<link[^>]+rel="[^"]*stylesheet"[^>]*>|style="[^"]*")|<script[^>]*>.*?<\/script>|<style[^>]*>.*?<\/style>|<!--.*?-->/ius', '', $str);
        return $str;
    }

    /**
     * Strips newline included <br> and \n
     * @param $str
     * @return mixed
     */
    public static function stripNewline($str) {
        $str = preg_replace('<br />', ' ', $str);
        $str = preg_replace('<\n>', ' ', $str);
        $str = str_replace(array("&lt;", "&gt;","&amp;lt;","&amp;gt;"), array("","","",""), htmlspecialchars($str, ENT_NOQUOTES, "UTF-8"));
        return $str;
    }

    /**
     * Strips extra whitespace, images, scripts and stylesheets from output
     *
     * @param string $str String to sanitize
     * @return string sanitized string
     */
    public static function stripAll($str) {
        $str = self::stripWhitespace($str);
        $str = self::stripImages($str);
        $str = self::stripImages($str);
        $str = self::stripNewline($str);
        return $str;
    }

    public static function br2nl($text){
        /* Remove XHTML linebreak tags. */
        $text = str_replace("&lt;br /&gt;","<br />",$text);
        //$text = str_replace("<br />","",$text);
        /* Remove HTML 4.01 linebreak tags. */
        //$text = str_replace("<br>","",$text);
        /* Return the result. */
        return $text;
    }

    /**
     * Strip quote
     *
     * @param $text
     * @return mixed
     */
    public static function stripQuote($text){
        /* Remove XHTML linebreak tags. */
        $text = str_replace("&amp;quot;","",$text);
        return $text;
    }

    /**
     * Sanitizes given array or value for safe input. Use the options to specify
     * the connection to use, and what filters should be applied (with a boolean
     * value). Valid filters:
     *
     * - odd_spaces - removes any non space whitespace characters
     * - encode - Encode any html entities. Encode must be true for the `remove_html` to work.
     * - dollar - Escape `$` with `\$`
     * - carriage - Remove `\r`
     * - unicode -
     * - backslash -
     * - remove_html - Strip HTML with strip_tags. `encode` must be true for this option to work.
     *
     * @param mixed $data Data to sanitize
     * @param mixed $options If string, DB connection being used, otherwise set of options
     * @return mixed Sanitized data
     */
    public static function clean($data, $options = array()) {
        if (empty($data)) {
            return $data;
        }

        if (!is_array($options)) {
            $options = array();
        }

        $options = array_merge(array(
            'odd_spaces' => true,
            'remove_html' => false,
            'dollar' => true,
            'carriage' => true,
            'unicode' => true,
            'backslash' => true
        ), $options);

        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = self::clean($val, $options);
            }
            return $data;
        } else {
            if ($options['odd_spaces']) {
                $data = str_replace(chr(0xCA), '', str_replace(' ', ' ', $data));
            }
            if ($options['dollar']) {
                $data = str_replace("\\\$", "$", $data);
            }
            if ($options['carriage']) {
                $data = str_replace("\r", "", $data);
            }

            $data = str_replace("'", "'", str_replace("!", "!", $data));

            if ($options['unicode']) {
                $data = preg_replace("/&amp;#([0-9]+);/s", "&#\\1;", $data);
            }
            if ($options['backslash']) {
                $data = preg_replace("/\\\(?!&amp;#|\?#)/", "\\", $data);
            }
            return $data;
        }
    }


    /**
     * @param $string
     * @return mixed
     */
    public static function smFeedHtmlToText($string){
        $search = array (
            "'<script[^>]*?>.*? </script>'si", // Strip out javascript
            "'<[\/\!]*?[^<>]*?>'si", // Strip out html tags
            "'([\r\n])[\s]+'", // Strip out white space
            "'&(quot|#34);'i", // Replace html entities
            "'&(amp|#38);'i",
            "'&(lt|#60);'i",
            "'&(gt|#62);'i",
            "'&(nbsp|#160);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i",
            "'&(reg|#174);'i",
            "'™'i",
            "'•'i",
            "'—'i",
            "'>'i",
            "'<'i",
            "'&#(\d+);'e"
        ); // evaluate as php
        $replace = array (
            " ",
            " ",
            "\\1",
            "\"",
            "&",
            " ",
            " ",
            " ",
            "&iexcl;",
            "&cent;",
            "&pound;",
            "&copy;",
            "&reg;",
            "<sup><small>TM</small></sup>",
            "&bull;",
            "-",
            "",
            "",
            "uchr(\\1)"
        );

        $text = preg_replace ($search, $replace, $string);
        return $text;
    }
} 