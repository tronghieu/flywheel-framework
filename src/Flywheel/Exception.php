<?php
namespace Flywheel;
class Exception extends \Exception
{

    /**
     * Emulates wrapped exceptions for PHP < 5.3
     *
     * @param string    $message
     * @param int $code
     * @param \Exception $previous
     * @return \Flywheel\Exception
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        if ($previous === null && $message instanceof \Exception) {
            $previous = $message;
            $message = '';
        }

        if ($previous !== null) {
            $message .= " [wrapped: " . $previous->getMessage() ."]";
            parent::__construct($message, 0, $previous);
        } else {
            parent::__construct($message);
        }
    }

    /**
     * Get the previous Exception
     * We can't override getPrevious() since it's final
     *
     * @return \Exception The previous exception
     */
    public function getCause()
    {
        return $this->getPrevious();
    }

    public static function printExceptionInfo(\Exception $e) {
        static::printStackTrace($e);
    }

    public static function printStackTrace(\Exception $e) {
        while (ob_get_level()) {
            if (!ob_end_clean()) {
                break;
            }
        }

        if (!headers_sent()) {
            header('HTTP/1.0 500 Internal Server Error');
        }
        $exceptionInfo = self::outputStackTrace($e);

        if (Base::ENV_DEV == Base::getEnv()) {
            echo $exceptionInfo;
        } else {
            /**
             * removed since version 1.0.2, application custom write log exception.
             */
            //error_log("\n" .self::outputStackTrace($e, 'txt'));
        }
    }

    /**
     * output stack trace
     * @param \Exception $exception
     * @param string $format
     * @param int $limit
     * @return string
     */
    public static function outputStackTrace(\Exception $exception, $format = 'html', $limit = 6) {
        $traceData = $exception->getTrace();
        array_unshift($traceData, array(
            'function' => '',
            'file'     => $exception->getFile() != null ? $exception->getFile() : null,
            'line'     => $exception->getLine() != null ? $exception->getLine() : null,
            'args'     => array(),
        ));

        if (0 == strncasecmp(PHP_SAPI, 'cli', 3)) {
            $format = 'txt';
        }

        if ($format == 'html') {
            echo
            '<script type="text/javascript" src="http://code.jquery.com/jquery-1.10.2.min.js"></script>
            <script type="text/javascript">
                function toggle(id) {
                    $("#" +id).toggle();
                };
            </script>
            ';
        }

        $traces = array ();
        if ($format == 'html') {
            $lineFormat = '<p>at <strong>%s%s%s</strong>(%s)<br />in <em>%s</em> line %s <a href="#" onclick="toggle(\'%s\'); return false;">...</a><br /><ul class="code" id="%s" style="display: %s">%s</ul></p>';
        } else {
            $lineFormat = "%d\tat %s%s%s(%s) in %s line %s";
        }

        $traceData = array_slice($traceData, 0, $limit);

        $count = count($traceData);
        $client = self::getClientIp();

        for($i = 0; $i < $count; ++$i) {
            $line = isset($traceData[$i]['line'])?
                $traceData[$i]['line'] : null;
            $file = isset($traceData[$i]['file'])?
                $traceData[$i]['file'] : null;
            $args = isset($traceData[$i]['args'])?
                $traceData[$i]['args'] : array();

            if ($format == 'html') {
                $traces[] = sprintf($lineFormat,
                    (isset($traceData[$i]['class'])? $traceData[$i]['class'] : ''),
                    (isset($traceData[$i]['type'] )? $traceData[$i]['type'] : ''),
                    $traceData[$i]['function'],
                    self::_formatArgs($args, false, $format),
                    self::_formatFile($file, $line, $format, null === $file ? 'n/a' : $file),
                    ((null === $line)? 'n/a' : $line),
                    'trace_' . $i, 'trace_' . $i,
                    $i == 0 ? 'block' : 'none',
                    self::_fileExcerpt($file, $line, $format));
            } else {
                $traces[] = sprintf($i,
                    $lineFormat,
                    (isset($traceData[$i]['class'])? $traceData[$i]['class'] : ''),
                    (isset($traceData[$i]['type'] )? $traceData[$i]['type'] : ''),
                    $traceData[$i]['function'],
                    self::_formatArgs($args, false, $format),
                    self::_formatFile($file, $line, $format, null === $file ? 'n/a' : $file));
            }
        }
        $message = null === $exception->getMessage() ? 'n/a' : $exception->getMessage();
        $name    = get_class($exception);

        if ($format == 'html') {
            $bufferFormat = '[%s] [client %s]<br/><h3>%s</h3>Message: "%s"<br />%s<br/>Referer:%s';
            $traces = implode('', $traces);
        } else {
            $bufferFormat = "[%s] [client %s] %sMessage: \"%s\". %s\nReferer:%s";
            $traces = implode("\n", $traces);
        }

        return sprintf($bufferFormat, date('Y-m-d H:i:s'), $client, get_class($exception), $message, $traces, @$_SERVER['HTTP_REFERER']);
    }

    public static function getClientIp() {
        $ipAddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipAddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipAddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipAddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipAddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipAddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipAddress = getenv('REMOTE_ADDR');
        else
            $ipAddress = 'UNKNOWN';

        return $ipAddress;
    }

    /**
     * Formats an array as a string.
     *
     * @param array   $args     The argument array
     * @param boolean $single
     * @param string  $format   The format string (html or txt)
     *
     * @return string
     */
    protected static function _formatArgs($args, $single = false, $format = 'html') {
        $result = array ();

        $single and $args = array($args);

        foreach ($args as $key => $value) {
            if (is_object($value)) {
                $formattedValue = ($format == 'html'? '<em>object</em>' : 'object') . sprintf("('%s')", get_class ($value));
            } else if(is_array($value)) {
                $formattedValue = ($format == 'html'? '<em>array</em>' : 'array') . sprintf( "(%s)", self::_formatArgs($value));
            } else if(is_string($value)) {
                $formattedValue = ($format == 'html'? sprintf( "'%s'", self::_escape($value)) : "'$value'");
            } else if (null === $value) {
                $formattedValue = ($format == 'html'? '<em>null</em>' : 'null');
            } else {
                $formattedValue = $value;
            }

            $result[] = is_int($key)?
                $formattedValue : sprintf ("'%s' => %s", self::_escape($key), $formattedValue);
        }

        return implode(', ', $result );
    }

    /**
     * Formats a file path.
     *
     * @param  string  $file   An absolute file path
     * @param  integer $line   The line number
     * @param  string  $format The output format (txt or html)
     * @param  string  $text   Use this text for the link rather than the file path
     *
     * @return string
     */
    protected static function _formatFile($file, $line, $format = 'html', $text = null) {
        if (null === $text) {
            $text = $file;
        }

        if ('html' == $format && $file && $line && $linkFormat = ini_get('xdebug.file_link_format')) {
            $link = strtr($linkFormat, array('%f' => $file, '%l' => $line ) );
            $text = sprintf('<a href="%s" title="Click to open this file" class="file_link">%s</a>', $link, $text);
        }

        return $text;
    }

    /**
     * Escapes a string value with html entities
     *
     * @param  string  $value
     *
     * @return string
     */
    protected static function _escape($value) {
        if (!is_string($value)) {
            return $value;
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Returns an excerpt of a code file around the given line number.
     *
     * @param string $file  A file path
     * @param int $line  The selected line number
     *
     * @param $format
     * @return string An HTML string
     */
    static protected function _fileExcerpt($file, $line, $format) {
        if ($format == 'txt') {
            return '';
        }

        if (is_readable($file)) {
            $content = preg_split( '#<br />#', highlight_file($file, true));

            $lines = array ();
            for($i = max($line - 3, 1 ), $max = min($line + 3, count($content )); $i <= $max; $i ++) {
                $lines [] = '<li' .($i == $line ? ' class="selected"' : '') .'>' .$content [$i - 1] .'</li>';
            }

            return '<ol start="' . max($line - 3, 1) .'">' .implode("\n", $lines) .'</ol>';
        }
    }
}
