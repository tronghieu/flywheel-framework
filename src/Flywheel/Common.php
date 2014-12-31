<?php
use Flywheel\Factory;
use Flywheel\Translation\Translator;

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

if (!function_exists('env')) {
    /**
     * Gets an environment variable from available sources, and provides emulation
     * for unsupported or inconsistent environment variables (i.e. DOCUMENT_ROOT on
     * IIS, or SCRIPT_NAME in CGI mode). Also exposes some additional custom
     * environment information.
     *
     * @param string $key Environment variable name.
     * @return string|bool|null Environment variable setting.
     */
    function env($key) {
        if ($key === 'HTTPS') {
            if (isset($_SERVER['HTTPS'])) {
                return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            }
            return (strpos(env('SCRIPT_URI'), 'https://') === 0);
        }
        if ($key === 'SCRIPT_NAME') {
            if (env('CGI_MODE') && isset($_ENV['SCRIPT_URL'])) {
                $key = 'SCRIPT_URL';
            }
        }
        $val = null;
        if (isset($_SERVER[$key])) {
            $val = $_SERVER[$key];
        } elseif (isset($_ENV[$key])) {
            $val = $_ENV[$key];
        } elseif (getenv($key) !== false) {
            $val = getenv($key);
        }
        if ($key === 'REMOTE_ADDR' && $val === env('SERVER_ADDR')) {
            $addr = env('HTTP_PC_REMOTE_ADDR');
            if ($addr !== null) {
                $val = $addr;
            }
        }
        if ($val !== null) {
            return $val;
        }
        switch ($key) {
            case 'DOCUMENT_ROOT':
                $name = env('SCRIPT_NAME');
                $filename = env('SCRIPT_FILENAME');
                $offset = 0;
                if (!strpos($name, '.php')) {
                    $offset = 4;
                }
                return substr($filename, 0, -(strlen($name) + $offset));
            case 'PHP_SELF':
                return str_replace(env('DOCUMENT_ROOT'), '', env('SCRIPT_FILENAME'));
            case 'CGI_MODE':
                return (PHP_SAPI === 'cgi');
            case 'HTTP_BASE':
                $host = env('HTTP_HOST');
                $parts = explode('.', $host);
                $count = count($parts);
                if ($count === 1) {
                    return '.' . $host;
                } elseif ($count === 2) {
                    return '.' . $host;
                } elseif ($count === 3) {
                    $gTLD = array(
                        'aero',
                        'asia',
                        'biz',
                        'cat',
                        'com',
                        'coop',
                        'edu',
                        'gov',
                        'info',
                        'int',
                        'jobs',
                        'mil',
                        'mobi',
                        'museum',
                        'name',
                        'net',
                        'org',
                        'pro',
                        'tel',
                        'travel',
                        'xxx'
                    );
                    if (in_array($parts[1], $gTLD)) {
                        return '.' . $host;
                    }
                }
                array_shift($parts);
                return '.' . implode('.', $parts);
        }
        return null;
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
    if (($translator = Translator::getInstance())) {
        return Translator::getInstance()->trans($id, $parameters, $domain, $locale);
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