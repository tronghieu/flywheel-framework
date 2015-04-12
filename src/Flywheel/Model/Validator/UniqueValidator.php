<?php
namespace Flywheel\Model\Validator;

use Flywheel\Db\Exception;
use Flywheel\Model\ActiveRecord;
use Flywheel\Util\Inflection;

class UniqueValidator extends ModelValidator {
    /**
     * @see BaseValidator::isValid()
     *
     * @param mixed $map
     * @param array $str
     *
     * @throws \Flywheel\Db\Exception
     * @return boolean
     */
    public function isValid($map, $str) {
        if (!($map instanceof ActiveRecord)) {
            throw new Exception('UniqueValidator require "$map" parameter much be ActiveRecord object');
        }

        $where = array();
        $params = array();
        foreach ($str as $name => $rule) {
            $getter = 'get' .Inflection::camelize($name);
            $check_value = $map->$getter();
            if ($check_value) {
                $where[] = $map::getTableName().".{$name} = ?";
                $params[] = $check_value;
            }
        }

        $where = implode(' OR ', $where);

        if (!$map->isNew()) {
            $exclude_self = $map::getTableName().'.' .$map::getPrimaryKeyField() .' != ?';
            $params[] = $map->getPkValue();
            $where = "($where) AND $exclude_self";
        }

        $fields = array_keys($str);

        foreach ($fields as &$field) {
            $field = $map->quote($field);
        }

        if ($where) {
            $data = $map::read()->select(implode(',', $fields))
                ->where($where)
                ->setMaxResults(1)
                ->setParameters($params)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC);

            if ($data) {
                foreach ($data as $field => $value) {
                    if($map->$field == $value) {
                        $map->setValidationFailure($map::getTableName() .'.' .$field, $field, $str[$field]['message'], $this);
                    }
                }
            }

            return !$map->hasValidationFailures();
        }

        return true;
    }
}