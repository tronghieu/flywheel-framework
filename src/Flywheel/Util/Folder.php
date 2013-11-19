<?php
namespace Flywheel\Util;
class Folder {
    /**
     * Check exists folder
     *
     * @param string $path
     * @return boolean
     */
    public static function exists($path) {
        return is_dir(self::clean($path));
    }

    public static function create($path, $mode = 0755) {
        // Check if dir already exists
        if (self::exists($path)) {
            return true;
        }
        $path = self::clean($path);
        static $nested = 0;

        //Check parent directory
        $parent = dirname($path);
        if(!self::exists($parent)) {
            $nested++;

            if (($nested > 20) || ($parent == $path)) {
                $nested--;
                return false;
            }

            //create parent
            if (self::create($parent, $mode) !== true) {
                $nested--;
                return false;
            }
            $nested--;
        }

        mkdir($path, $mode, true);
        return true;
    }

    /**
     * Clean
     *  strip '/', '\' trong path
     *
     * @static
     * @param	string	$path	Duong dan
     * @param	string	$ds		Directory separator
     * @return	string	The cleaned path
     */
    public static function clean($path, $ds = DIRECTORY_SEPARATOR) {
        $path = trim($path);

        if (empty($path)) {
            $path = ''; //wait for define
        } else {
            // Remove double slashes and backslahses and convert all slashes and backslashes to DS
            $path = preg_replace('#[/\\\\]+#', $ds, $path);
        }

        return $path;
    }

    public static function cleanFileName($filename) {
        $bad = array(
            "<!--",
            "-->",
            "'",
            "<",
            ">",
            '"',
            '&',
            '$',
            '=',
            ';',
            '?',
            '/',
            "%20",
            "%22",
            "%3c",		// <
            "%253c", 	// <
            "%3e", 		// >
            "%0e", 		// >
            "%28", 		// (
            "%29", 		// )
            "%2528", 	// (
            "%26", 		// &
            "%24", 		// $
            "%3f", 		// ?
            "%3b", 		// ;
            "%3d"		// =
        );

        $filename = str_replace($bad, '', $filename);

        return stripslashes($filename);
    }
}
