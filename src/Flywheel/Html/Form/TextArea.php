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
     * @param int $row
     * @return $this
     */
    public function setRow($row)
    {
        $this->_row = $row;
        return $this;
    }

    /**
     * @param int $cols
     * @return $this
     */
    public function setCols($cols)
    {
        $this->_cols = $cols;
        return $this;
    }

    /**
     * Display text area
     */
    public function display() {
        $this->_htmlOptions['name'] = $this->_name;
        $this->_htmlOptions['type'] = $this->_type;
        $this->_htmlOptions['placeholder'] = $this->_placeHolder;
        $this->_htmlOptions['row'] = $this->_row;
        $this->_htmlOptions['col'] = $this->_cols;
        foreach($this->_data as $data=>$value) {
            $this->_htmlOptions['data-' .$data] = $value;
        }

        echo '<textarea name="' .$this->_name .'" '.$this->_serializeHtmlOption($this->_htmlOptions) .(($this->_disabled)? ' disabled':'') .'>'.$this->_value .'</textarea>';
    }
}