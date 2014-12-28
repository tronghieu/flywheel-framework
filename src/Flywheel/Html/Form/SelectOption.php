<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 5/8/13
 * Time: 5:56 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Html\Form;


use Flywheel\Html\Html;

class SelectOption extends Html {
    public $isMultiple = false;
    public $name;
    public $selectValues = array();
    public $size = 1;
    public $options = array();
    public $isDisable = false;

    public function __construct($name, $selectValues = array(), $htmlOptions = array()) {
        $this->name = $name;
        $this->selectValues = (array) $selectValues;
        $this->setHtmlOption($htmlOptions);
    }

    /**
     * Add <option>
     *
     * @param $name
     * @param $value
     * @param array $htmlOptions
     * @return $this
     */
    public function addOption($name, $value, $htmlOptions = array()) {
        $this->options[$name] = array(
            'value' => $value,
            'htmlOptions' => $htmlOptions
        );

        if (isset($htmlOptions) && isset($htmlOptions['disabled']) && $htmlOptions['disabled']) {
            $this->options[$name]['disabled'] = true;
        } else {
            $this->options[$name]['disabled'] = false;
        }
        unset($this->options[$name]['htmlOptions']['disabled']);

        return $this;
    }

    /**
     * Display
     */
    public function display() {
        $s = '<select name="' .$this->name .'"' .($this->isMultiple? ' multiple="multiple"' :'')
            .($this->size > 1? ' size="' .$this->size .'"':'')
            .$this->_serializeHtmlOption($this->_htmlOptions).'>';

        foreach($this->options as $name => $option) {
            $s .= '<option value="' .$option['value'] .'" ' .$this->_serializeHtmlOption($option['htmlOptions'])
                . (in_array($option['value'], $this->selectValues)? ' selected="selected"' : '')
                . ((isset($option['disabled']) && $option['disabled'])? ' disabled' :'')
                . '>' .$name .'</option>';
        }

        $s .='</select>';

        echo $s;
    }
}