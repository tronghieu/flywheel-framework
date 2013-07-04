<?php
namespace Flywheel\Model\Validator;

use Flywheel\Exception;

class Rule {

    protected static $_classesMap = array(
        'Email' => '\Flywheel\Validator\EmailValidator',
        'Match' => '\Flywheel\Validator\MatchValidator',
        'MaxLength' => '\Flywheel\Validator\MaxLengthValidator',
        'MaxValue' => '\Flywheel\Validator\MaxValueValidator',
        'MinLength' => '\Flywheel\Validator\MinLengthValidator',
        'MinValue' => '\Flywheel\Validator\MinValueValidator',
        'NotMatch' => '\Flywheel\Validator\NotMatchValidator',
        'Require' => '\Flywheel\Validator\RequireValidator',
        'Type' => '\Flywheel\Validator\TypeValidator',
        'ValidValues' => '\Flywheel\Validator\ValidValuesValidator',
    );

    private $_validator;

    /** rule name of this validator */
    private $_name;
    /** the dot-path to class to use for validator */
    private $_className;
    /** value to check against */
    private $_value;
    /** exception message thrown on invalid input */
    private $_message;
    /** related column */
    private $_column;

    public function __construct($containingColumn) {
        $this->_column = $containingColumn;
    }

    public static function checkClassesList($rule) {
        return isset(self::$_classesMap[$rule]);
    }

    public function getColumn() {
        return $this->_column;
    }

    public function setName($name) {
        $this->_name = $name;
    }

    public function setClass($rule) {
        if (self::checkClassesList($rule)) {
            $this->_className = self::$_classesMap[$rule];
        } else {
            $this->_className = $rule;
        }
    }

    public function setValue($value) {
        $this->_value = $value;
    }

    public function setMessage($message) {
        $this->_message = $message;
    }

    public function getName() {
        return $this->_name;
    }

    public function getClass() {
        return $this->_className;
    }

    public function getValue() {
        return $this->_value;
    }

    public function getMessage() {
        return $this->_message;
    }
}