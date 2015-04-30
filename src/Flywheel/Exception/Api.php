<?php
namespace Flywheel\Exception;
use Flywheel\Base;
use Flywheel\Exception;

class Api extends Exception {
    /**
     * @param Exception $e
     */
    public static function printExceptionInfo($e) {
        while (ob_get_level()) {
            if (!ob_end_clean()) {
                break;
            }
        }

        if (!headers_sent()) {
            $code = $e->getCode();
            if (null == $code) {
                $code = '500';
            }

            if ($e instanceof Exception) {
                $code = '400';
            }

            $headMsg = self::_getHeaderMessage($code);

            header("HTTP/1.1 $code $headMsg");
        }

        if (($e instanceof \Exception) || ($e instanceof \Flywheel\Exception\Api)) {
            $response = self::_responseError($e);
            $format = \Flywheel\Factory::getRouter()->getFormat();
            switch ($format) {
                case 'xml' :
                    header('Content-type:text/xml');
                    break;
                case 'text':
                    break;
                default:
                    header('Content-type:application/json');
                    $response = json_encode($response);

            }

            echo $response;
        } else {
            error_log(self::outputStackTrace($e));
        }
    }

    private static function _responseError(\Exception $e) {
        /* @var \Flywheel\Router\ApiRouter $router */
        $router = \Flywheel\Factory::getRouter();
        $response = new \stdClass();
        $response->hash = array(
            'request' => $router->getUri(),
            'error'=> $e->getMessage(),
        );

        if (400 != $e->getCode()) {
            $response->hash['api'] = $router->getApi() .'/' .$router->getMethod();
            $response->hash['format'] = $router->getFormat();
        }

        return $response;
    }

    private static function _getHeaderMessage($code) {
        switch ($code) {
            case 400:
                return 'Bad Request';
            case 401:
                return 'Unauthorized';
            case 403:
                return 'Forbidden';
            case 404:
                return 'Not Found';
            case 406:
                return 'Not Acceptable';
            case 500:
                return 'Internal Server Error';
            case 502:
                return 'Bad Gateway';
            case 503:
                return 'Service Unavailable';
        }
    }
}
