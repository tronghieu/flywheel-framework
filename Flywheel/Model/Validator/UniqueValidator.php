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
     * @param string $str
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
            $where[] = $map::getTableName().".{$name} = ?";
            $getter = 'get' .Inflection::camelize($name);
            $params[] = $map->$getter();
        }

        if (!$map->isNew()) {
            $where[] = $map::getTableName().'.' .$map::getPrimaryKeyField() .' != ?';
            $params[] = $map->getPkValue();
        }
        $where = implode(' AND ', $where);

        $fields = array_keys($str);

        foreach ($fields as &$field) {
            $field = $map->quote($field);
        }

        $data = $map::read()->select(implode(',', $fields))
            ->where($where)
            ->setMaxResults(1)
            ->setParameters($params)
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);

        if ($data) {
            foreach ($data as $field => $value) {
                if($map->$field == $value) {
                    $map->setValidationFailure($map::getTableName() .$field, $str[$field]['message'], $this);
                }
            }
        }

        return !$map->hasValidationFailures();
    }
}