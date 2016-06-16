<?php
namespace Flywheel\Http;
use Flywheel\Config\ConfigHandler;
use Flywheel\Event\Event;
use Flywheel\Object;

abstract class Response extends Object {
    protected $_body = '';
    protected $_options = array();

    static protected $statusTexts = array(
        '100' => 'Continue',
        '101' => 'Switching Protocols',
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '306' => '(Unused)',
        '307' => 'Temporary Redirect',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '407' => 'Proxy Authentication Required',
        '408' => 'Request Timeout',
        '409' => 'Conflict',
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Request Entity Too Large',
        '414' => 'Request-URI Too Long',
        '415' => 'Unsupported Media Type',
        '416' => 'Requested Range Not Satisfiable',
        '417' => 'Expectation Failed',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
        '503' => 'Service Unavailable',
        '504' => 'Gateway Timeout',
        '505' => 'HTTP Version Not Supported',
    );

    protected $_headers = array();
    protected $_statusCode = 200;
    protected $_statusText = 'OK';

    public function __construct($options = array()) {
        $this->_options = array(
            'charset'       => 'utf-8',
            'content_type'  => 'text/html',
            'http_protocol' => 'HTTP/1.0'
        );

        //		$this->setHeader('Cache-Control', 'max-age=315360000', true);
        //		$this->setHeader('Expires', gmdate("D, d M Y H:i:s", time() + 315360000) . " GMT", true );
        array_merge($this->_options, $options);
        $this->_options['content_type'] = $this->_fixContentType(isset($this->_options['content_type']) ? $this->_options['content_type'] : 'text/html');
        $this->init();
    }

    public function init() {}

    public function clearHeaders() {
        $this->_headers = array();
    }

    /**
     * Sets response status code.
     *
     * @param string $code  HTTP status code
     * @param string $name  HTTP status text
     *
     */
    public function setStatusCode($code, $name = null) {
        $this->_statusCode = $code;
        $this->_statusText = null !== $name ? $name : self::$statusTexts[$code];
    }

    /**
     * Retrieves status text for the current web response.
     *
     * @return string Status text
     */
    public function getStatusText() {
        return $this->_statusText;
    }

    /**
     * Sets response content type.
     *
     * @param string $value  Content type
     */
    public function setContentType($value) {
        $this->_headers['Content-Type'] = $this->_fixContentType($value);
    }

    /**
     * Retrieves status code for the current web response.
     *
     * @return integer Status code
     */
    public function getStatusCode() {
        return $this->_statusCode;
    }

    public function setHeader($name, $value, $replace = false) {
        $name = preg_replace('/\-(.)/e', "'-'.strtoupper('\\1')", strtr(ucfirst(strtolower($name)), '_', '-'));
        if ($value == null) {
            unset($this->_headers[$name]);
            return;
        }

        if (!$replace) {
            $$value = (isset($this->_headers[$name])? $this->_headers[$name] .', ':'') . $value;
        }

        $this->_headers[$name] = $value;
    }

    /**
     * Set Response Body
     *
     * @param string $s
     */
    public function setBody($s) {
        $this->_body = $s;
    }

    /**
     * Get response body
     *
     * @return string
     */
    public function getBody() {
        return $this->_body;
    }

    /**
     * Send HTTP Headers
     *
     * @return void
     */
    public function sendHttpHeaders() {
        if (headers_sent())	return;

        //response status
        $status = $this->_options['http_protocol'].' '.$this->_statusCode.' '.$this->_statusText;
        header($status);

        if (substr(php_sapi_name(), 0, 3) == 'cgi') {
            // fastcgi servers cannot send this status information because it was sent by them already due to the HTT/1.0 line
            // so we can safely unset them. see ticket #3191
            unset($this->_headers['Status']);
        }

        if (!isset($this->_headers['Content-Type'])) {
            $this->setContentType($this->_options['content_type']);
        }

        foreach ($this->_headers as $name => $value) {
            header($name .':' . $value);
        }
    }

    /**
     * check, whether client supports compressed data
     *
     * @access	private
     * @return	boolean
     */
    function getClientEncoding() {
        if (!isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            return false;
        }

        $encoding = false;

        if (false !== strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
            $encoding = 'gzip';
        }

        if (false !== strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip')) {
            $encoding = 'x-gzip';
        }

        return $encoding;
    }

    /**
     * Compress the data
     *
     * Checks the accept encoding of the browser and compresses the data before
     * sending it to the client.
     *
     * @access	public
     * @return	string		compressed data
     */
    private function compress() {
        $encoding = $this->getClientEncoding();
        if (!$encoding || !extension_loaded('zlib') || ini_get('zlib.output_compression')
            || headers_sent() || connection_status() !== 0)
            return;
        $this->_body = gzencode($this->_body, 4);
        $this->setHeader('Content-Encoding', $encoding);
        $this->setHeader('X-Content-Encoded-By', 'ming.vn <tronghieu1012@yahoo.com>');
    }

    public function sendContent() {
        echo $this->_body;
    }

    /**
     * Send to client
     *
     */
    public function send() {
        if (ConfigHandler::get('response_compress')
            && !ini_get('zlib.output_compression')
            && ini_get('output_handler')!='ob_gzhandler') {
            $this->compress();
        }

        $this->sendHttpHeaders();
        $this->dispatch('onAfterSendHttpHeader', new Event($this));
        $this->sendContent();
        $this->dispatch('onAfterSendContent', new Event($this));
    }

    /**
     * Fixes the content type by adding the charset for text content types.
     *
     * @param  string $contentType  The content type
     *
     * @return string The content type with the charset if needed
     */
    protected function _fixContentType($contentType) {
        // add charset if needed (only on text content)
        if (false === stripos($contentType, 'charset')
            && (0 === stripos($contentType, 'text/')
                || strlen($contentType) - 3 === strripos($contentType, 'xml'))) {
            $contentType .= '; charset='.$this->_options['charset'];
        }

        // change the charset for the response
        if (preg_match('/charset\s*=\s*(.+)\s*$/', $contentType, $match)) {
            $this->_options['charset'] = $match[1];
        }

        return $contentType;
    }
}
