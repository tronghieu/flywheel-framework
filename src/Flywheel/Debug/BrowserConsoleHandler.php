<?php
namespace Flywheel\Debug;

use Flywheel\Event\Event;
use Flywheel\Http\Response;
use Flywheel\Object;
use Flywheel\Util\Inflection;

class BrowserConsoleHandler extends Object implements IHandler {
    protected static $_initialized = false;
    protected static $_records = [];
    protected static $_jsVar = [];
    protected static $_jquery = true;

    protected $_options = [];

    protected $_is_xhr = false;

    public function __construct($options = []) {
        $this->_is_xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';

        if (isset($options['jquery_support'])) {
            self::$_jquery = (bool) $options['jquery_support'];
        }

        foreach ($options as $opt=>$value) {
            $this->$opt = $value;
        }

        if (PHP_SAPI !== 'cli' && !self::$_initialized) {
            self::$_initialized = true;
            register_shutdown_function(array('\Flywheel\Debug\BrowserConsoleHandler', 'send'));
        }

        if ($this->_is_xhr) {
            $this->getEventDispatcher()->addListener('onAfterSendHttpHeader', array($this, 'handlerXhrResponse'));
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'BrowserConsoleHandler';
    }

    /**
     * Write debug data
     * @param $records
     */
    public function write($records)
    {
        if (!$this->_is_xhr) {
            $this->_writeNormalRequest($records);
        }
    }

    /**
     * Write data for normal request
     * @param $records
     */
    protected function _writeNormalRequest($records)
    {
        //serialize
        self::$_records[] = '-----------BEGIN PROFILE ' .$_SERVER['REQUEST_URI'] .'----------';
        self::$_records[] = date('Y-m-d H:i:s') .' [' .$records['SERVER_ADDRESS'] .']';
        self::$_records[] = 'Memory (MB): ' .$records['memory']['memory_usage']
            .' MB/' .$records['memory']['max_memory_allow']
            .' MB ('.round($records['memory']['memory_usage_percent'], 2) .'%)';

        self::$_records[] = 'Total execute time: ' .$records['total_exec_time'] .' seconds';

        self::$_records[] = 'Server variables';

        self::$_records['argv'] = $records['argv'];
        self::$_records['argc'] = $records['argc'];
        self::$_records['cookies'] = $records['cookies'];
        self::$_records['session'] = $records['session'];

        self::$_records['Requests'] = $records['requests'];

        self::$_records['Marks'] = $records['activities'];

        self::$_records[] = 'Total queries: ' .$records['sql_queries']['total_queries']
            . ', Execute time: ' .$records['sql_queries']['total_exec_time'] .' seconds';

        self::$_records['SQL Queries'] = [];
        foreach($records['sql_queries']['queries'] as $q) {
            self::$_records['SQL Queries'][$q['query']] = [
                'info' => "\tTime: {$q['exec_time']} ({$q['memory']} MB)",
                'parameters' => (isset($q['parameters']) && !empty($q['parameters']))? $q['parameters'] : null,
            ];
        }

        self::$_records[] = 'Total ' .sizeof($records['included_files']) .' included files';
        self::$_records[] = '-----------END PROFILE----------';
    }

    /**
     * Handler xhr response data and add debug for it
     * @param Event $event
     */
    public function handlerXhrResponse(Event $event) {
        /** @var Response $response */
        $response = $event->sender;
        $records = Profiler::getInstance()->getProfileData();
        $this->_writeNormalRequest($records);

        $body = $response->getBody();
        if (($t = json_decode($body, true))) {
            $t['debug'] = self::$_records;
            $body = json_encode($t);
        }

        $response->setBody($body);
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
            if (self::$_jquery) {
                echo '<script>
                    $(document).ajaxComplete(function (event, xhr) {
                        if (xhr.responseJSON && xhr.responseJSON.debug) {
                            console.log(xhr.responseJSON.debug);
                        }
                    });
                </script>';
            }

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
        foreach (self::$_records as $key => $record) {
            if (is_numeric($key)) {
                $script[] = self::_callArray('log', [self::_quote($record)]);
            } else {
                $var_name = self::_makeJsVarName($key);
                if (is_array($record)) {
                    self::$_jsVar[$var_name][$key] = $record;
                }
                $script[] = self::_callArray('log', [$var_name]);
            }
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
        return "(function (c) {if (c && c.groupCollapsed) {\n" .self::_genJsVars() . implode("\n", $script) . "\n}})(console);";
    }

    /**
     * @param $key
     * @return string
     */
    private static function _makeJsVarName($key) {
        return strtolower(str_replace(' ', '_', $key));
    }

    /**
     * @return string
     */
    private static function _genJsVars() {
        $code = '';
        if (!empty(self::$_jsVar)) {
            foreach (self::$_jsVar as $name => $value) {
                $code .= "var {$name} = ".json_encode($value) .";\n";
            }
        }

        return $code;
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

    public function __set($name, $value) {
        $this->_options[$name] = $value;
    }

    public function __get($name) {
        return (isset($this->_options[$name]))? $this->_options[$name] : null;
    }
}