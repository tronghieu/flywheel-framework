<?php
namespace Flywheel\Html\Form;
use Flywheel\Html\Html;

class RadioButton extends Html {
    public $name;
    public $checkValue = '';
    public $label = array();

    public function __construct($name, $checkValue = '', $label = array()) {
        $this->name = $name;
        $this->checkValue = $checkValue;
        $this->label = $label;
    }

    public function add($value, $label, $htmlOptions = array(), $inputOptions = array()) {
        $this->label[] = array(
            'value' => $value,
            'label' => $label,
            'options' => $htmlOptions,
            'input_options' => $inputOptions
        );

        return $this;
    }

    public function display() {
        $s = '';

        for ($i = 0, $size = sizeof($this->label); $i < $size; ++$i) {
            $s .= '<label ' .$this->_serializeHtmlOption($this->label[$i]['options']) .">\n"
                . '<input ' .$this->_serializeHtmlOption($this->label[$i]['input_options']) .' type="radio" name="' .$this->name .'" value="' .$this->label[$i]['value'] .'"'
                . (('' !== $this->checkValue && $this->checkValue == $this->label[$i]['value'])? ' checked="checked" ': '')
                . ">\n"
                . $this->label[$i]['label']
                . "\n</label>\n";
        }

        echo $s;
    }
}