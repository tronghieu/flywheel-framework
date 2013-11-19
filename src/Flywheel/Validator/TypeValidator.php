<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/20/13
 * Time: 11:37 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Validator;


use Flywheel\Exception;

class TypeValidator extends BaseValidator
{
    /**
     * @see BasicValidator::isValid()
     *
     * @param mixed $map
     * @param mixed $value
     *
     * @throws \Flywheel\Exception
     * @return boolean
     *
     */
    public function isValid($map, $value)
    {
        switch ($map) {
            case 'array':
                return is_array($value);
                break;
            case 'bool':
            case 'boolean':
                return is_bool($value);
                break;
            case 'float':
                return is_float($value);
                break;
            case 'int':
            case 'integer':
                return is_int($value);
                break;
            case 'numeric':
                return is_numeric($value);
                break;
            case 'object':
                return is_object($value);
                break;
            case 'resource':
                return is_resource($value);
                break;
            case 'scalar':
                return is_scalar($value);
                break;
            case 'string':
                return is_string($value);
                break;
            case 'function':
                return function_exists($value);
                break;
            default:
                throw new Exception('Unknown type ' . $map);
                break;
        }
    }
}