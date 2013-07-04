<?php
namespace Flywheel\Util;


class Slugify {
    /*
	 * Replace Vietnamese vowels with diacritic and Letter D with Stroke with corresponding English characters
	 */
    public static function replaceVietnameseCharacters($str){
        $str = preg_replace("/[\x{00C0}-\x{00C3}\x{00E0}-\x{00E3}\x{0102}\x{0103}\x{1EA0}-\x{1EB7}]/u", "a", $str);
        $str = preg_replace("/[\x{00C8}-\x{00CA}\x{00E8}-\x{00EA}\x{1EB8}-\x{1EC7}]/u", "e", $str);
        $str = preg_replace("/[\x{00CC}\x{00CD}\x{00EC}\x{00ED}\x{0128}\x{0129}\x{1EC8}-\x{1ECB}]/u", "i", $str);
        $str = preg_replace("/[\x{00D2}-\x{00D5}\x{00F2}-\x{00F5}\x{01A0}\x{01A1}\x{1ECC}-\x{1EE3}]/u", "o", $str);
        $str = preg_replace("/[\x{00D9}-\x{00DA}\x{00F9}-\x{00FA}\x{0168}\x{0169}\x{01AF}\x{01B0}\x{1EE4}-\x{1EF1}]/u", "u", $str);
        $str = preg_replace("/[\x{00DD}\x{00FD}\x{1EF2}-\x{1EF9}]/u", "y", $str);
        $str = preg_replace("/[\x{0110}\x{0111}]/u", "d", $str);

        return $str;
    }

    /*
	 * Remove or Replace special symbols with spaces
	 */
    public static function removeSpecialCharacters($str, $remove=true) {
        // Remove or replace with spaces
        $substitute = $remove ? "": " ";
        $str = preg_replace("/[\x{0021}-\x{002D}\x{002F}\x{003A}-\x{0040}\x{005B}-\x{0060}\x{007B}-\x{007E}\x{00A1}-\x{00BF}]/u", $substitute, $str);
        return $str;
    }

    /*
	 * Remove 5 Vietnamese accent / tone marks if has Combining Unicode characters
	 * Tone marks: Grave (`), Acute(´), Tilde (~), Hook Above (?), Dot Bellow(.)
	 */
    public static function removeAccent($str) {
        $str = preg_replace("/[\x{0300}\x{0301}\x{0303}\x{0309}\x{0323}]/u", "", $str);
        return $str;
    }

    public static function filter($input, $lower = true, $maxLength = null) {
        $input = self::removeAccent($input);
        $input = self::removeSpecialCharacters($input);
        $input = self::replaceVietnameseCharacters($input);

        $input = preg_replace('/(\s|[^A-Za-z0-9\-])+/', '-', trim($input));

        if (null !== $maxLength) {
            $input = substr($input, 0, $maxLength);
        }

        $input = trim($input, '-');

        if ($lower) {
            $input = strtolower($input);
        }

        if (empty($input)) {
            $input = null; // should we return null or an empty string?
        }

        return $input;
    }
}