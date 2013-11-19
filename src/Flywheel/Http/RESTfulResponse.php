<?php
namespace Flywheel\Http;
class RESTfulResponse extends Response {
    public $format = 'json';
    public function init() {
        $app = \Flywheel\Base::getApp();
        if (!($app instanceof \Flywheel\Application\ApiApp)) {
            throw new \Flywheel\Exception('Response: application instance not is a "\Flywheel\Application\ApiApp"');
        }
        $this->format = strtolower($app->getFormat());
        switch ($this->format) {
            case 'json':
                $this->setHeader('Content-type', 'application/json', true);
                break;
            case 'xml':
                $this->setHeader('Content-type', 'text/xml', true);
                break;
            default:
        }
    }

    public function sendContent() {
        echo ('xml' == $this->format)? $this->formatXml($this->_body) :
                $this->formatJson($this->_body);
    }

    /**
     * format data to json
     *
     * @param string $data json object
     * @return string
     */
    public function formatJson($data) {
        return json_encode($data);
    }

    /**
     * format data to xml
     *
     * @param string $data xml
     * @return string
     */
    public function formatXml($data) {
        return $data;
    }
}
