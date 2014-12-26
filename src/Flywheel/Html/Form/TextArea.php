<?php
/**
 * Created by PhpStorm.
 * User: nobita
 * Date: 12/26/14
 * Time: 12:17 PM
 */

namespace Flywheel\Html\Form;


class TextArea extends Input {
    protected $_row = 0;
    protected $_cols = 0;
    /**
     * Display text area
     */
    public function display() {
        $this->_htmlOptions['name'] = $this->_name;
        $this->_htmlOptions['value'] = $this->_value;
        $this->_htmlOptions['type'] = $this->_type;
        $this->_htmlOptions['placeholder'] = $this->_placeHolder;
        foreach($this->_data as $data=>$value) {
            $this->_htmlOptions['data-' .$data] = $value;
        }

        echo '<textarea name="' .$this->_name .'" '.$this->_serializeHtmlOption($this->_htmlOptions) .(($this->_disabled)? ' disabled':'') .'>'.$this->_value .'</textarea>';
    }
}