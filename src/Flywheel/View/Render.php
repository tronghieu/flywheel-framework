<?php
/**
 * Ming View
 *
 * @author		Luu Trong Hieu <hieuluutrong@vccorp.vn>
 * @version		$Id: View.php 4885 2010-10-08 08:33:20Z hieult $
 * @package		Ming
 * @subpackage	View
 * @copyright	VC Corp (c) 2010
 */
namespace Flywheel\View;
use Flywheel\Factory;
use Flywheel\Html\Form;

class Render {
    /**
     * Assign templates vars
     * @var array
     */
    private $_vars = array();

    private $_ext = '.phtml';

    /**
     * Template Path
     * @var string
     */
    public $templatePath;

    public function __construct() {}

    /**
     * get HTMLDocument object
     * @return \Flywheel\Document\Html
     */
    public function document() {
        return Factory::getDocument();
    }

    /**
     * Assign value to templates variable
     * @param string|array $var
     * @param mixed $value    default null
     *
     * @throws Exception
     * @return boolean    true if assign success
     */
    public function assign($var, $value = null) {
        if (is_string($var)) {
            if ($var == '') {
                return false;
            }
            $this->_vars[$var] = $value;
            return true;
        }
        else if (is_array($var)) {
            $this->_vars = array_merge($this->_vars, $var);
            return true;
        }
        throw new Exception('assign() expects a string or array, received ' . gettype($var));
    }

    /**
     * clear all the assigned templates variables.
     */
    public function clearAllAssign() {
        $this->_vars = array();
    }

    /**
     * clear the given assigned templates variable.
     *
     * @param string|array $vars the templates variables to clear
     *
     * @return boolean
     */
    public function clearAssign($vars) {
        if (is_array($vars)) {
            for ($i = 0, $size = sizeof($vars); $i < $size; ++$i) {
                unset($this->_vars[$vars[$i]]);
            }
            return true;
        }
        unset($this->_vars[$vars]);
    }

    /**
     * Render templates with data
     *
     * @param string $file
     * @param array $vars. Default null
     *
     * @return string
     *
     * @throws Exception if file templates not found
     */
    public function render($file, $vars = null) {
        if (is_array($vars)) {
            $this->assign($vars);
        }

        return $this->_render($file);
    }

    /**
     * Display
     *
     * @param string $file
     * @param array $vars
     *
     * @return void
     *
     * @throws Exception if file templates not found
     */
    public function display($file, $vars = null) {
        if (is_array($vars)) {
            $this->assign($vars);
        }

        echo $this->_render($file);
    }

    /**
     * Render
     * @access    private
     * @param string $file
     * @throws Exception
     * @return string
     */
    private function _render($file) {
        $this->templatePath = preg_replace('#/+#', DIRECTORY_SEPARATOR, $this->templatePath);
        if (!file_exists($temFile = $this->templatePath .$file .$this->_ext)) {
            throw new Exception('Template file not found:' .$temFile);
        }

        extract($this->_vars, EXTR_SKIP);
        ob_start();
        include $temFile;
        $ouput = ob_get_clean();
        return $ouput;
    }

    public function setFileExtension($ext) {
        $this->_ext = $ext;
    }

    /**
     * @return \Flywheel\Http\Request
     */
    public function request() {
        return Factory::getRequest();
    }

    public function checkViewFileExist($viewFile) {
        return file_exists($this->templatePath.$viewFile.$this->_ext);
    }

    /**
     * @param $class
     * @param null $params
     * @param bool $return
     * @return string|void
     */
    public function widget($class, $params = null, $return = false) {
        $widget = Factory::getWidget($class, $params, $this);
        $widget->begin();
        $buffer = $widget->end();
        if ($return) {
            return $buffer;
        }

        echo $buffer;
    }

    /**
     * alias call @see WebRouter::createUrl();
     * @param $route
     * @param array $params
     * @param string $ampersand
     * @return mixed
     */
    public function createUrl($route,$params=array(),$ampersand='&') {
        return Factory::getRouter()->createUrl($route, $params, $ampersand);
    }

    public function createFrom($name = null, $action = '', $method = 'POST') {
        return new Form($name, $action, $method);
    }
}