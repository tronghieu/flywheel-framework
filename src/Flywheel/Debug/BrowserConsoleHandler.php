<?php
namespace Flywheel\Debug;


use Flywheel\Log\Logger;

class BrowserConsoleHandler implements IHandler {
    protected static $_initialized = false;
    protected static $_records = array();

    public function __construct() {
        register_shutdown_function(array('\Flywheel\Log\Logger\BrowserConsoleHandler', 'send'));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'BrowserConsoleHandler';
    }

    public function write($records)
    {
        //serialize
        self::$_records[] = '&para;' .date('Y-m-d H:i:s') .' [' .$records['SERVER_ADDRESS'] .']';
        self::$_records[] = 'Memory (MB): ' .$records['memory']['memory_usage']
            .'/' .$records['memory']['max_memory_allow']
            .' ('.round($records['memory']['memory_usage_percent'], 2) .'%)';

        self::$_records[] = 'Total execute time: ' .$records['total_exec_time'] .' seconds';
        self::$_records[] = json_encode([
            'Server variables' => [
                'argv' => $records['argv'],
                'argc' => $records['argc'],
                'cookies' => $records['cookies'],
                'session' => $records['session'],
            ]
        ]);
        self::$_records[] = json_encode([
            'Requests' => $records['requests']
        ]);

        self::$_records[] = json_encode([
            'Marks' => $records['activities']
        ]);

        self::$_records[] = 'Total queries: ' .$records['sql_queries']['total_queries']
            . ', Execute time: ' .$records['sql_queries']['total_exec_time'] .' seconds';
        self::$_records[] = json_encode([
            'SQL Queries' => [
                $records['sql_queries']['queries']
            ]
        ]);

        self::$_records[] = 'Total ' .sizeof($records['included_files']) .' included files';
    }

    /**
     * Convert records to javascript console commands and send it to the browser.
     * This method is automatically called on PHP shutdown if output is HTML.
     */
    public static function send()
    {
        // Check content type
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0) {
                if (stripos($header, 'text/html') === false) {
                    // This handler only works with HTML outputs
                    return;
                }
                break;
            }
        }
        if (count(self::$_records)) {
            echo '<script>' . self::_generateScript() . '</script>';
            self::reset();
        }
    }

    /**
     * Forget all logged records
     */
    public static function reset()
    {
        self::$_records = array();
    }

    private static function _generateScript()
    {
        $script = array();
        for($i = 0, $s = sizeof(self::$_records); $i < $s; ++$i) {
            $script[] = self::_callArray('log', self::_handleStyles(self::$_records[$i]));
        }

        /*
        $script = array();
        foreach (self::$records as $record) {
            $context = self::_dump('Context', $record['context']);
            $extra = self::_dump('Extra', $record['extra']);
            if (empty($context) && empty($extra)) {
                $script[] = self::_callArray('log', self::_handleStyles($record['formatted']));
            } else {
                $script = array_merge($script,
                    array(self::_callArray('groupCollapsed', self::_handleStyles($record['formatted']))),
                    $context,
                    $extra,
                    array(self::_call('groupEnd'))
                );
            }
        }
         */
        return "(function (c) {if (c && c.groupCollapsed) {\n" . implode("\n", $script) . "\n}})(console);";
    }

    private static function _handleStyles($formatted)
    {
        $args = array(self::_quote('font-weight: normal'));
        $format = '%c' . $formatted;
        preg_match_all('/\[\[(.*?)\]\]\{([^}]*)\}/s', $format, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach (array_reverse($matches) as $match) {
            $args[] = self::_quote(self::_handleCustomStyles($match[2][0], $match[1][0]));
            $args[] = '"font-weight: normal"';
            $pos = $match[0][1];
            $format = substr($format, 0, $pos) . '%c' . $match[1][0] . '%c' . substr($format, $pos + strlen($match[0][0]));
        }
        array_unshift($args, self::_quote($format));
        return $args;
    }

    private static function _handleCustomStyles($style, $string)
    {
        static $colors = array('blue', 'green', 'red', 'magenta', 'orange', 'black', 'grey');
        static $labels = array();
        return preg_replace_callback('/macro\s*:(.*?)(?:;|$)/', function ($m) use ($string, &$colors, &$labels) {
            if (trim($m[1]) === 'autolabel') {
                // Format the string as a label with consistent auto assigned background color
                if (!isset($labels[$string])) {
                    $labels[$string] = $colors[count($labels) % count($colors)];
                }
                $color = $labels[$string];
                return "background-color: $color; color: white; border-radius: 3px; padding: 0 2px 0 2px";
            }
            return $m[1];
        }, $style);
    }

    private static function _dump($title, array $dict)
    {
        $script = array();
        $dict = array_filter($dict);
        if (empty($dict)) {
            return $script;
        }
        $script[] = self::_call('log', self::_quote('%c%s'), self::_quote('font-weight: bold'), self::_quote($title));
        foreach ($dict as $key => $value) {
            $value = json_encode($value);
            if (empty($value)) {
                $value = self::_quote('');
            }
            $script[] = self::_call('log', self::_quote('%s: %o'), self::_quote($key), $value);
        }
        return $script;
    }

    private static function _quote($arg)
    {
        return '"' . addcslashes($arg, "\"\n") . '"';
    }

    private static function _call()
    {
        $args = func_get_args();
        $method = array_shift($args);
        return self::_callArray($method, $args);
    }

    private static function _callArray($method, array $args)
    {
        return 'c.' . $method . '(' . implode(', ', $args) . ');';
    }

}