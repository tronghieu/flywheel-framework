<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/25/13
 * Time: 10:07 AM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Html;

use Flywheel\Factory;
use Flywheel\Html\Form\Checkbox;
use Flywheel\Html\Form\Input;
use Flywheel\Html\Form\RadioButton;
use Flywheel\Html\Form\SelectOption;
use Flywheel\Html\Form\TextArea;

class Form extends Html {
    public $name = '';
    public $action = '';
    public $method = 'POST';

    public function __construct($name = '', $action = '', $method = 'POST') {
        $this->action = $action;
        $this->method = $method;
        $this->name = $name;
    }

    /**
     * generate beginning form html tag
     */
    public function beginForm() {
        echo "<form name=\"{$this->name}\" action=\"{$this->action}\" method=\"{$this->method}\""
            . $this->_serializeHtmlOption()
        .">";
    }

    public function endForm($csrfProtection = true) {
        $s = '';
        if ($csrfProtection) {
            $s .= '<input type="hidden" name="' .Factory::getRequest()->getCsrfToken() .'" value=1>';
        }
        $s .= '</form>';

        echo $s;
    }

    /**
     * Add radio button
     *
     * @param $name
     * @param string $checkValue
     * @param array $htmlOptions
     * @return RadioButton
     */
    public function radioButton($name, $checkValue = '', $htmlOptions = array()) {
        return new RadioButton($name, $checkValue, $htmlOptions);
    }

    /**
     * Add select option
     *
     * @param $name
     * @param array $selectValues
     * @param array $htmlOptions
     * @return SelectOption
     */
    public function selectOption($name, $selectValues = array(), $htmlOptions = array()) {
        return new SelectOption($name, $selectValues, $htmlOptions);
    }

    /**
     * Add input
     *
     * @param $name
     * @param $value
     * @param array $htmlOptions
     * @return Input
     */
    public function input($name, $value, $htmlOptions = []) {
        return new Input($name, $value, $htmlOptions);
    }

    /**
     * Add textarea
     *
     * @param $name
     * @param $value
     * @param array $htmlOptions
     * @return TextArea
     */
    public function textArea($name, $value, $htmlOptions = []) {
        return new TextArea($name, $value, $htmlOptions);
    }

    /**
     * Checkbox
     *
     * @param $name
     * @param $value
     * @param string $checkValue
     * @param array $htmlOptions
     * @return Checkbox
     */
    public function checkbox($name, $value, $checkValue = "" , $htmlOptions = []){
        $cb = new Checkbox($name, $value, $htmlOptions);
        $cb->setExpectValue($checkValue);
        return $cb;
    }
}