<?php
/**
 * Created by PhpStorm.
 * User: nobita
 * Date: 12/26/14
 * Time: 6:00 AM
 */

namespace Flywheel\Html\Form;


use Flywheel\Html\Html;

class Input extends Html {
    public $supportTypes = ['text', 'password', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'number', 'range', 'search', 'tel', 'time', 'url', 'week'];
    /**
     * @var input name
     */
    protected $_name;

    /**
     * @var input value
     */
    protected $_value;

    /**
     * @var
     */
    protected $_placeHolder;

    /**
     * @var input type @link http://www.w3schools.com/html/html_form_input_types.asp
     */
    protected $_type = 'text';

    /**
     * Html data- attribute
     * @var array
     */
    protected $_data = [];

    protected $_disabled = false;

    /**
     * readonly attr
     * @var bool
     */
    protected $_readonly = false;

    public function __construct($name, $value, $htmlOptions = []) {
        $this->_name = $name;
        $this->_value = $value;
        $this->_htmlOptions = $htmlOptions;
    }

    /**
     * @param mixed $name
     * @return $this
     */
    public function setName($name)
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->_value = $value;
        return $this;
    }

    /**
     * @param mixed $placeHolder
     * @return $this
     */
    public function setPlaceHolder($placeHolder)
    {
        $this->_placeHolder = $placeHolder;
        return $this;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * Set data-* attribute
     *
     * @param $data
     * @param $value
     * @return $this
     */
    public function setData($data, $value){
        $this->_data[$data] = $value;
        return $this;
    }

    /**
     * remove data-* attribute
     * @param $data
     * @return $this
     */
    public function removeData($data){
        unset($this->_data[$data]);
        return $this;
    }

    /**
     * Set readonly
     *
     * @param $b
     * @return $this
     */
    public function setReadonly($b) {
        $this->_readonly = (bool) $b;
        return $this;
    }

    /**
     * Set disable
     *
     * @param $b
     * @return $this
     */
    public function setDisable($b) {
        $this->_disabled = (bool) $b;
        return $this;
    }

    /**
     * Disable input
     * @return $this
     */
    public function disabled() {
        $this->_disabled = true;
        return $this;
    }

    /**
     * Active input
     * @return $this
     */
    public function enable() {
        $this->_disabled = false;
        return $this;
    }

    /**
     * Display input
     */
    public function display() {
        $this->_htmlOptions['name'] = $this->_name;
        $this->_htmlOptions['value'] = $this->_value;
        $this->_htmlOptions['type'] = $this->_type;
        $this->_htmlOptions['placeholder'] = $this->_placeHolder;
        foreach($this->_data as $data=>$value) {
            $this->_htmlOptions['data-' .$data] = $value;
        }

        $html = '<input ' .$this->_serializeHtmlOption($this->_htmlOptions)
            .(($this->_disabled)? ' disabled':''
            .(($this->_readonly)? ' readonly' : ''));

        if ($this->_type == 'checkbox' && isset($this->_htmlOptions['checked']) && $this->_htmlOptions['checked']) {
            $html .= ' checked';
        }

        $html .= $this->_singleHtmlCloseTag();

        echo $html;
    }
}