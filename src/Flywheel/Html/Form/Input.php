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

    public function __construct($name, $value, $htmlOptions = []) {
        $this->_name = $name;
        $this->_value = $value;
        $this->_htmlOptions = $htmlOptions;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->_value = $value;
    }

    /**
     * @param mixed $placeHolder
     */
    public function setPlaceHolder($placeHolder)
    {
        $this->_placeHolder = $placeHolder;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * Set data-* attribute
     *
     * @param $data
     * @param $value
     */
    public function setData($data, $value){
        $this->_data[$data] = $value;
    }

    /**
     * remove data-* attribute
     * @param $data
     */
    public function removeData($data){
        unset($this->_data[$data]);
    }

    /**
     * Disable input
     */
    public function disabled() {
        $this->_disabled = true;
    }

    /**
     * Active input
     */
    public function enable() {
        $this->_disabled = false;
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

        $html = '<input ' .$this->_serializeHtmlOption($this->_htmlOptions) .(($this->_disabled)? ' disabled':'');

        if ($this->_type == 'checkbox' && isset($this->_htmlOptions['checked']) && $this->_htmlOptions['checked']) {
            $html .= ' checked';
        }

        $html .= $this->_singleHtmlCloseTag();

        echo $html;
    }
}