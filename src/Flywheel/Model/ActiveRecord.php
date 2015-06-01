<?php
namespace Flywheel\Model;
use Flywheel\Db\Exception;
use Flywheel\Db\Type\DateTime;
use Flywheel\Event\Event;
use Flywheel\Model\Validator\Rule;
use Flywheel\Object;
use Flywheel\Validator\BaseValidator;
use Flywheel\Db\Expression;
use Flywheel\Util\Inflection;
use Flywheel\Db\Connection;
use Flywheel\Db\Manager;

abstract class ActiveRecord extends Object {
    protected static $_tableName;
    protected static $_phpName;
    protected static $_pk;
    protected static $_dbConnectName;
    protected static $_validate = array();
    protected static $_cols = array();
    protected static $_schema = array();
    protected static $_alias;
    protected static $_instances = array();

    /**
     * @var Rule[]
     */
    protected static $_validatorRules = array();

    /**
     * @var BaseValidator[]
     */
    protected static $_validator = array();
    protected static $_readMode = Manager::__SLAVE__;
    protected static $_writeMode = Manager::__MASTER__;
    protected static $_init = false;

    /**
     * status of deleted om. If object had been delete from database
     * @var bool
     */
    private $_deleted = false;

    /**
     * status of new object. If object not store in database, this value is true
     * @var $_new boolean
     */
    private $_new = true;

    protected $_data = array();
    protected $_modifiedCols = array();

    /**
     * @var ValidationFailed[]
     */
    protected $_validationFailures = array();
    protected $_valid = false;

    public function __construct($data = null, $isNew = true) {
        $this->setTableDefinition();
        $this->_initDataValue();
        $this->init();

        if (!empty($data)) {
            $this->hydrate($data);
        }

        $this->setNew($isNew);
        if (!static::$_init) {
            $this->validationRules();
            static::$_init = true;
        }
    }

    public function setTableDefinition() {}

    /**
     * customize model validation rules
     * @return void
     */
    public function validationRules() {}

    protected function _initDataValue() {
        foreach (static::$_schema as $c => $config) {
            if (!isset($this->_data[$c])) {
                $this->_data[$c] = (isset($config['default']))? $config['default'] : null;
            } else {
                $this->_data[$c] = static::fixData($this->_data[$c], static::$_schema[$c]);
            }
        }
    }

    public function init() {}

    public static function setReadMode($mode) {
        if ($mode != Manager::__MASTER__ && $mode != Manager::__SLAVE__)
            throw new Exception("Read mode {$mode} is not allowed.");
        self::$_readMode = $mode;
    }

    public static function setWriteMode($mode) {
        if ($mode != Manager::__MASTER__ && $mode != Manager::__SLAVE__)
            throw new Exception("Write mode {$mode} is not allowed.");
        self::$_writeMode = $mode;
    }

    public static function getReadMode() {
        return static::$_readMode;
    }

    public static function getWriteMode() {
        return static::$_writeMode;
    }

    public static function create() {
        return new static();
    }

    public static function setTableName($tblName) {
        static::$_tableName = $tblName;
    }

    public static function getTableName() {
        return static::$_tableName;
    }

    public static function setPhpName($phpName) {
        static::$_phpName = $phpName;
    }

    public static function getPhpName() {
        return static::$_phpName;
    }

    public static function setTableAlias($alias) {
        static::$_alias = $alias;
    }

    public static function getTableAlias() {
        return static::$_alias;
    }

    public static function setPrimaryKeyField($field) {
        static::$_pk = $field;
    }

    public static function getPrimaryKeyField() {
        return static::$_pk;
    }

    public static function setDbConnectName($dbName) {
        static::$_dbConnectName = $dbName;
    }

    public static function getDbConnectName() {
        self::create();
        return static::$_dbConnectName;
    }

    public static function quote($name) {
        return self::getReadConnection()->getAdapter()->quoteIdentifier($name);
    }

    public static function getColumnsList($alias = null) {
        $db = self::getReadConnection();

        $list = array();
        for($i = 0, $size = sizeof(static::$_cols); $i < $size; ++$i) {
            $list[] = ((null != $alias)? $alias .'.' : '') .$db->getAdapter()->quoteIdentifier(static::$_cols[$i]);
        }
        return implode(',', $list);
    }

    /**
     * get write database connection (slave)
     * @return \Flywheel\Db\Connection
     */
    public static function getWriteConnection() {
        return Manager::getConnection(self::getDbConnectName(), self::getWriteMode());
    }

    /**
     * get read database connection (slave)
     * @return \Flywheel\Db\Connection
     */
    public static function getReadConnection() {
        return Manager::getConnection(self::getDbConnectName(), self::getReadMode());
    }

    /**
     * create read query
     * @return \Flywheel\Db\Query
     */
    public static function read() {
        return self::getReadConnection()->createQuery()->from(static::getTableName());
    }

    /**
     * create write query
     * @return \Flywheel\Db\Query
     */
    public static function write() {
        return self::getWriteConnection()->createQuery()->from(static::getTableName());
    }

    /**
     * Select always return array of object when calling @see \Flywheel\Db\Query::execute()
     * (or empty if have no records)
     * @return \Flywheel\Db\Query
     */
    public static function select() {
        $q = self::read();
        $q->setSelectQueryCallback(array(static::getPhpName(), 'selectQueryCallback'));
        return $q;
    }

    /**
     * @param \PDOStatement $stmt
     * @return array|null
     */
    public static function selectQueryCallback(\PDOStatement $stmt) {
        if ($stmt instanceof \PDOStatement) {
            /** @var ActiveRecord $om */
            $oms = $stmt->fetchAll(\PDO::FETCH_CLASS, static::getPhpName(), array(null, false));
            for ($i = 0, $size = sizeof($oms); $i < $size; ++$i) {
                $oms[$i]->resetModifiedCols();
            }

            return $oms;
        }

        return null;
    }

    /**
     * return the named attribute value.
     * if this is a new record and the attribute is not set before, the default column value will be returned.
     * if this record is the result of a query and the attribute is not loaded, null will be returned.     *
     * @param string $name the attribute name
     * @return mixed
     */
    public function getAttribute($name) {
        if (property_exists($this, $name))
            return $this->$name;
        else if (isset($this->_data[$name]))
            return $this->_data[$name];

        return null;
    }

    /**
     * return all column attribute values.
     *
     * @param mixed $names names of attributes whose value need to be returned.
     * if this null (default), them all attribute values will be returned
     * if this is a array
     * @return array
     */
    public function getAttributes($names = null) {
        if (null == $names)
            return $this->_data;

        if (is_string($names)) {
            $names = explode(',', $names);
        }

        $attr = array();
        if (is_array($names)) {
            for ($i = 0, $size = sizeof($names); $i < $size; ++$i) {
                $names[$i] = trim($names[$i]);
                if (property_exists($this, $names[$i]))
                    $attr[$names[$i]] = $this->$names[$i];
                else
                    $attr[$names[$i]] = isset($this->_data[$names[$i]])? $this->_data[$names[$i]] : null;
            }
        }

        return $attr;
    }

    public function hasField($field) {
        return isset(static::$_schema[$field]);
    }

    /**
     * reload object data from db
     * @return bool
     * @throws Exception
     */
    public function reload() {
        if ($this->isDeleted()) {
            throw new Exception('Cannot reload a deleted object.');
        }

        if ($this->isNew()) {
            throw new Exception('Cannot reload an unsaved object.');
        }

        $data = static::write()->where(static::getPrimaryKeyField() .'= :pk')
            ->setMaxResults(1)
            ->setParameter(':pk', $this->getPkValue())
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);
        if ($data) {
            $this->hydrate($data);
            static::addInstanceToPool($this);
            return true;
        }
        throw new Exception('Reload fail!');
    }

    /**
     * get primary key field
     *
     * @return mixed
     */
    public function getPkValue() {
        return $this->{static::getPrimaryKeyField()};
    }

    /**
     * Set validation failure each column
     *
     * @param $table
     * @param $column
     * @param $mess
     * @param null $validator
     */
    public function setValidationFailure($table, $column, $mess, $validator = null) {
        $this->_validationFailures[] = new ValidationFailed($table, $column, $mess, $validator);
    }

    /**
     * @return ValidationFailed[]
     */
    public function getValidationFailures() {
        return $this->_validationFailures;
    }

    /**
     * @param string $sep
     * @return null|string
     */
    public function getValidationFailuresMessage($sep = "<br>") {
        $r = '';
        if (!empty($this->_validationFailures)) {
            for ($i = 0, $size = sizeof($this->_validationFailures); $i < $size; ++$i) {
                $r .= $this->_validationFailures[$i]->getMessage() .$sep;
            }
        }
        return $r;
    }

    public function beforeSave() {
        return $this->_beforeSave();
    }

    protected function _beforeSave() {
        return $this->getPrivateEventDispatcher()->dispatch('onBeforeSave', new Event($this));
    }

    public function afterSave() {
        return $this->_afterSave();
    }

    protected function _afterSave() {
        return $this->getPrivateEventDispatcher()->dispatch('onAfterSave', new Event($this));
    }

    public function beforeDelete() {
        return $this->_beforeDelete();
    }

    protected function _beforeDelete() {
        return $this->getPrivateEventDispatcher()->dispatch('onBeforeDelete', new Event($this));
    }

    public function afterDelete() {
        return $this->_afterDelete();
    }

    protected function _afterDelete() {
        return $this->getPrivateEventDispatcher()->dispatch('onAfterDelete', new Event($this));
    }

    protected function _beforeValidate() {
        $this->getPrivateEventDispatcher()->dispatch('onBeforeValidate', new Event($this));
    }

    protected function _afterValidate() {
        $this->getPrivateEventDispatcher()->dispatch('onAfterValidate', new Event($this));
    }

    /**
     * is object did not store in database
     *
     * @return boolean
     */
    public function isNew() {
        return $this->_new;
    }

    /**
     * Set New
     * @param boolean $isNew
     */
    public function setNew($isNew) {
        $this->_new = (boolean) $isNew;
    }

    public function getModifiedCols() {
        return array_keys($this->_modifiedCols);
    }

    public function isColumnModified($col) {
        return isset($this->_modifiedCols[$col]);
    }

    public function hasColumnsModified() {
        return (bool) sizeof($this->_modifiedCols);
    }

    public function isNotNull($col) {
        return static::$_schema[$col]['not_null'];
    }

    /**
     * check model's data were validated
     * @return bool
     */
    public function isValid() {
        return $this->_valid;
    }

    /**
     * check model has validationFailures
     * @return bool
     */
    public function hasValidationFailures() {
        return sizeof($this->_validationFailures) > 0;
    }

    /**
     * To Array
     *
     * @param bool $raw return array with all
     *          object's property neither only column field
     *
     * @return array
     */
    public function toArray($raw = false) {
        if (true === $raw) {
            $data = get_object_vars($this);
        } else {
            $data = $this->_data;
        }

        foreach ($data as $k => $v) {
            if ($v instanceof DateTime) {
                $data[$k] = $v->toString();
            }
        }

        return $data;
    }

    /**
     * To Json
     *
     * @param bool $raw
     *
     * @return string in JSON format
     */
    public function toJSon($raw = false) {
        if (true === $raw) {
            return json_encode($this);
        }

        return json_encode($this->_data);
    }

    /**
     * Reset modified cols
     */
    public function resetModifiedCols() {
        $this->_modifiedCols = array();
    }

    /**
     * hydrate data to object
     *
     * @param object | array $data
     */
    public function hydrate($data) {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        foreach ($data as $p=>$value) {
            if (isset(static::$_schema[$p])) {
                if ($value instanceof Expression) {
                    $this->_modifiedCols[$p] = true;
                    $this->_data[$p] = $value;
                } else {
                    $d = $this->fixData($value, static::$_schema[$p]);
                    if ($this->_data[$p] !== $d) {
                        $this->_modifiedCols[$p] = true;
                        $this->_data[$p] = $d;
                    }
                }
            } else {
                $this->$p = $value;
            }
        }
    }

    /**
     * hydrate json to om
     * @param $json
     */
    public function hydrateJSON($json) {
        $data = json_decode($json, true);

        $this->hydrate($data);
    }

    public function isDeleted() {
        return $this->_deleted;
    }

    /**
     * fix data matche collumn data defined
     * @param $data
     * @param $config
     * @return bool|float|int|null|string|Expression
     */
    public function fixData($data, $config) {
        if ($data instanceof Expression) {
            return $data;
        }

        $type = $config['type'];
        if (null !== $data) {
            switch ($type) {
                case 'integer':
                    return (int) $data;
                case 'float':
                case 'number':
                case 'decimal':
                    return (float) $data;
                case 'double' :
                    return (double) $data;
                case 'time':
                case 'timestamp':
                case 'date':
                case 'datetime':
                    if ($data instanceof \DateTime) {
                        $data = new DateTime($data->format('Y-m-d H:i:s'));
                    }
                    if ($data instanceof DateTime) {
                        return $data;
                    }
                    return new DateTime($data);
                case 'blob':
                case 'string':
                    return (string) $data;
                case 'boolean':
                case 'bool':
                    return (bool) $data;
                case 'array':
                    if (is_scalar($data)) {
                        $data = json_decode($data);
                    }
                    return $data;
            }
        } else {
            if (!isset(self::$_validate[$config['name']])
                || !isset(self::$_validate[$config['name']]['require'])) {
                if ('bool' == $type)
                    return 0;

                if ('array' == $type)
                    return array();
            } else {
                switch ($type) {
                    case 'integer':
                    case 'int':
                    case 'float':
                    case 'decimal':
                    case 'double':
                    case 'number':
                        return 0;
                    case 'timestamp':
                        return new DateTime('0000-00-00 00:00:00');
                    case 'time':
                        return new DateTime('0000-00-00 00:00:00');
                    case 'date':
                        return new DateTime('0000-00-00');
                    case 'datetime':
                        return new DateTime('0000-00-00 00:00:00');
                    case 'blob':
                    case 'string':
                        return '';
                    case 'boolean':
                    case 'bool':
                        return 0;
                    case 'array':
                        return array();
                }
            }
        }

        return null;
    }

    /**
     * Removes errors for all attributes or a single attribute.
     * @param string $attribute attribute name. Use null to remove errors for all attribute.
     */
    public function clearErrors($attribute=null) {
        if($attribute===null)
            $this->_validationFailures = array();
        else
            unset($this->_validationFailures[$attribute]);
    }

    /**
     * @param bool $validate
     * @throws \Flywheel\Db\Exception
     * @return bool
     */
    public function saveToDb($validate = true) {
        if ($validate && !$this->validate()) {
            return false;
        }

        $data = $this->getAttributes($this->getModifiedCols());
        foreach($data as $c => &$v) {
            if (is_array($v)) {
                $v = json_encode($v);
            } else if ($v instanceof DateTime) {
                $v = $v->toString();
            } else {
                $v = $this->fixData($v, static::$_schema[$c]);
            }
        }

        $db = self::getWriteConnection();
        $data_bind = $this->_populateStmtValues($data);
        if (!empty($data_bind)) {
            if ($this->isNew()) { //insert new record
                $status = $db->insert(static::getTableName(), $data, $data_bind);
                if (!$status) {
                    throw new Exception('Insert record did not succeed!');
                }
                $this->{static::getPrimaryKeyField()} = $db->lastInsertId();
            } else {
                $db->update(static::getTableName(), $data, array(static::getPrimaryKeyField() => $this->getPkValue()), $data_bind);
            }
        }

        $this->resetModifiedCols();
        $this->setNew(false);
        return true;
    }

    /**
     * Build SqlStatement bind values
     *
     * @param $data
     * @return array
     */
    protected function _populateStmtValues(&$data) {
        $data_bind = array();
        $c = $data;
        foreach ($c as $n => $v) {
            if (!($v instanceof Expression)) {
                /*
                 * @FIXME LuuHieu: I could not remember why need check null here?
                if ((null === $v || '' === $v) && (!isset(static::$_validate[$n]) || !isset(static::$_validate[$n]['require']))) {
                    unset($data[$n]); // no thing
                } else {
                    $databind[] = self::getReadConnection()->getAdapter()->getPDOParam(static::$_schema[$n]['db_type']);
                }*/

                $data_bind[] = self::getReadConnection()->getAdapter()->getPDOParam(static::$_schema[$n]['db_type']);
            }
        }

        return $data_bind;
    }

    /**
     * delete object from database
     *
     * @return int
     * @throws Exception
     */
    public function deleteFromDb() {
        if ($this->isNew()) {
            throw new Exception('Record has been not saved in to database, cannot delete!');
        }

        $pkField = static::getPrimaryKeyField();
        $affectedRows = self::getWriteConnection()->delete(static::getTableName(), array($pkField => $this->getPkValue()));
        if ($affectedRows)
            $this->_deleted = true;
        return $affectedRows;
    }

    /**
     * begin transaction
     * @return bool
     */
    public function beginTransaction() {
        return self::getWriteConnection()->beginTransaction();
    }

    /**
     * commit transaction
     * @return bool
     */
    public function commit() {
        return self::getWriteConnection()->commit();
    }

    /**
     * rollBack
     * @return bool
     */
    public function rollBack() {
        return self::getWriteConnection()->rollBack();
    }

    abstract public function save($validate = true);
    abstract public function delete();

    public function validate() {
        $this->clearErrors();
        $this->_beforeValidate();

        $unique = array();

        foreach (static::$_validate as $name => $rules) {
            foreach($rules as $rule) {
                if ($rule['name'] == 'Unique') {
                    $unique[$name] = $rule;
                    continue;
                }

                if (!isset(static::$_validatorRules[$name. '.' .$rule['name']])) {
                    if (!isset($rule['value'])) {
                        $rule['value'] = '';
                    }

                    $validationRule = new Rule(static::getTableName() .$name);
                    $validationRule->setClass($rule['name']);
                    $validationRule->setMessage($rule['message']);
                    $validationRule->setValue($rule['value']);
                    static::$_validatorRules[static::getTableName(). '.' .$name. '.' .$rule['name']] = $validationRule;
                } else {
                    $validationRule = static::$_validatorRules[static::getTableName(). '.' .$name. '.' .$rule['name']];
                }

                $validator = static::createValidator($validationRule->getClass());
                if ($validator && ($this->$name != null || $this->isNotNull($name)) && !$validator->isValid($validationRule->getValue(), $this->$name)) {
                    $this->setValidationFailure(static::getTableName(), $name, $validationRule->getMessage(), $validator);
                }
            }
        }

        if (!empty($unique) && !$this->hasValidationFailures()) {
            $validator = static::createValidator('\Flywheel\Model\Validator\UniqueValidator');
            $validator->isValid($this, $unique);
        }

        $this->_afterValidate();
        $this->_valid = !$this->hasValidationFailures();
        return $this->_valid;
    }

    /**
     * @param $validatorName
     * @return null|BaseValidator
     * @throws \Flywheel\Db\Exception
     */
    public static function createValidator($validatorName) {
        try {
            if (!isset(static::$_validator[$validatorName])) {
                static::$_validator[$validatorName] = new $validatorName();
            }

            return static::$_validator[$validatorName];
        } catch (\Exception $e) {
            throw new Exception("ActiveRecord::createValidator(): failed trying to instantiate {$validatorName}:{$e->getMessage()} in {$e->getFile()} at {$e->getLine()}}");
        }
    }

    /**
     * add instance to pool
     * @static
     * @param $obj
     * @param null $key
     * @return bool
     */
    public static function addInstanceToPool($obj, $key = null) {
        $lbClass = get_called_class();
        if (!$obj instanceof $lbClass) {
            return false;
        }

        /* @var ActiveRecord $obj */
        if (null == $key) {
            $key = $obj->getPkValue();
        }

        static::$_instances[$key] = $obj;
        return true;
    }

    /**
     * get instance from pool by key
     * @static
     * @param $key
     * @return static|null
     */
    public static function getInstanceFromPool($key) {
        return isset(static::$_instances[$key])? static::$_instances[$key] : null;
    }

    /**
     * get all instances from pool by key
     * @static
     * @return static[] | null
     */
    public static function getInstancesFromPool() {
        return static::$_instances;
    }

    /**
     * remove instance in pool by $key
     * @static
     * @param $key
     */
    public static function removeInstanceFromPool($key) {
        unset(static::$_instances[$key]);
    }

    /**
     * clear pool
     * @static
     */
    public static function clearPool() {
        static::$_instances = array();
    }

    /**
     * Resolves the passed find by field name inflecting the parameter.
     *
     * @param string $fieldName
     * @return string $fieldName
     */
    protected static function _resolveFindByFieldName($fieldName) {
//        $fieldName = Inflection::camelCaseToHungary($name);
        $fieldName = strtolower($fieldName);
        if (isset(static::$_schema[$fieldName])) {
            return (static::getTableAlias()? static::getTableAlias() .'.' :'')
            .self::getReadConnection()->getAdapter()->quoteIdentifier($fieldName);
        }

        return false;
    }

    public static function buildFindByWhere($fieldName) {
        if ('' == $fieldName || 1 == $fieldName || '*' == $fieldName || 'all' == strtolower($fieldName))
            return '1';

        $fieldName = preg_replace('~(?<=\\w)([A-Z])~', '_$1', $fieldName);

        $ands = array();
        $e = explode('_And_', $fieldName);
        foreach ($e as $k => $v) {
            $and = '';
            $e2 = explode('_Or_', $v);
            $ors = array();
            foreach ($e2 as $k2 => $v2) {
                if ($v2 = static::_resolveFindByFieldName(trim($v2, '_'))) {
                    $ors[] = $v2 . ' = ?';
                } else {
                    throw new Exception('Invalid field name to find by: ' . $v2);
                }
            }
            $and .= implode(' OR ', $ors);
            $and = count($ors) > 1 ? '(' . $and . ')':$and;
            $ands[] = $and;
        }
        $where = implode(' AND ', $ands);
        return $where;
    }

    /**
     * findAll records in table
     * careful with this method;
     *
     * @param null $assoc
     * @return array
     */
    public static function findAll($assoc = null) {
        static::create();
        $stmt =  self::getReadConnection()->createQuery()
            ->select(static::getTableAlias() . '.*')
            ->from(self::quote(static::getTableName()), static::getTableAlias())
            ->execute();

        $result = [];
        while($om = $stmt->fetchObject(self::getPhpName(), [null, false])) {
            /** @var ActiveRecord $om */
            $om->resetModifiedCols();
            self::addInstanceToPool($om);
            if ($assoc && $om->hasField($assoc)) {
                $result[$om->getAttribute($assoc)] = $om;
            } else {
                $result[] = $om;
            }
        }

        return $result;
    }

    /**
     * @param array $conditions
     * @param string $order
     * @param string $limit
     * @param null $assoc
     * @return array
     */
    public static function findAllByConditions($conditions = array(), $order = 'id asc', $limit = '', $assoc = null) {
        static::create();
        $query = self::getReadConnection()->createQuery();
        $query
            ->select(static::getTableAlias() . '.*')
            ->from(self::quote(static::getTableName()), static::getTableAlias());

        if($conditions && !empty($conditions)){
            foreach ($conditions as $condition){
                if(is_string($condition)) {
                    $query->andWhere($condition);
                }
            }
        }

        if($order && $order !='' && is_string($order)){
            $orderArray = explode(' ',$order);
            if(!empty($orderArray) && isset($orderArray[0]) && isset($orderArray[1])){
                $query->orderBy($orderArray[0],$orderArray[1]);
            }
        }

        if($limit && $limit !='' && is_string($limit) && strpos($limit,',')!==false){
            $limitArray = explode(',',$order);
            if(!empty($limitArray) && isset($limitArray[0]) && isset($limitArray[1])){
                $query->setFirstResult($limitArray[0]);
                $query->setMaxResults($limitArray[1]);
            }
        }
        $stmt = $query
            ->execute();

        $result = [];
        while($om = $stmt->fetchObject(self::getPhpName(), [null, false])) {
            /** @var ActiveRecord $om */
            $om->resetModifiedCols();
            self::addInstanceToPool($om);
            if ($assoc && $om->hasField($assoc)) {
                $result[$om->getAttribute($assoc)] = $om;
            } else {
                $result[] = $om;
            }
        }

        return $result;
    }

    /**
     * find By key with params
     *
     * @param $by
     * @param null $param
     * @param bool $first
     * @return array|ActiveRecord|null
     * @throws Exception
     */
    public static function findBy($by, $param = null, $first = false) {
        static::create();
        $q = self::getReadConnection()->createQuery()
            ->select(static::getTableAlias() .'.*')
            ->from(self::quote(static::getTableName()), static::getTableAlias())
            ->where(static::buildFindByWhere($by));
        if ($first)
            $q->setMaxResults(1);

        foreach ($param as &$p) {//toString datetime object
            if ($p instanceof DateTime) {
                $p = $p->format('Y-m-d H:i:s');
            }
        }

        if (null != $param)
            $q->setParameters($param);

        $stmt = $q->execute();

        $result = array();
        while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            /** @var ActiveRecord $om */
            $om = new static($row, false);
            self::addInstanceToPool($om);
            $om->resetModifiedCols(); //reset after get from database
            if ($first) {
                return $om;
            }

            $result[] = $om;
        }

        return (!empty($result))? $result : null;
    }

    /**
     * @param $by
     * @param $param
     * @return ActiveRecord|null
     */
    public static function retrieveBy($by, $param) {
        static::create();
        $field = Inflection::camelCaseToHungary($by);
        $id = static::getPrimaryKeyField();
        if ($field == static::getPrimaryKeyField()) {
            if (null != ($obj = static::getInstanceFromPool($param[0]))) {
                return $obj;
            }
        } else {
            $objs = static::getInstancesFromPool();
            foreach($objs as $obj) {
                if ($obj->{$field} == $param[0]) {
                    return $obj;
                }
            }
        }

        $obj = self::findBy($by, $param, true);
        if ($obj) {
            static::addInstanceToPool($obj);
            return $obj;
        }

        return false;
    }

    public function __call($method, $params) {
        foreach ($this->_behaviors as $behavior) {
            if($behavior->getEnable() && method_exists($behavior, $method)) {
                return call_user_func_array(array($behavior, $method), $params);
            }
        }

        if (strrpos($method, 'set') === 0
            && isset($params[0]) && null !== $params[0]) {
            $name = Inflection::camelCaseToHungary(substr($method, 3, strlen($method)));

            if (isset(static::$_cols[$name])) {
                $this->_data[$name] = $this->fixData($params[0], static::$_schema[$name]);
                $this->_modifiedCols[$name] = true;
            } else {
                $this->$name = $params[0];
            }

            return true;
        }

        if (strpos($method, 'get') === 0) {
            $name = Inflection::camelCaseToHungary(substr($method, 3, strlen($method)));
            if (in_array($name, static::$_cols)) {
                return isset($this->_data[$name])? $this->_data[$name]: null ;
            }

            return $this->$name;
        }

        $lcMethod = strtolower($method);
        if (substr($lcMethod, 0, 6) == 'findby') {
            $by = substr($method, 6, strlen($method));
            $method = 'findBy';
            $one = false;
        } else if(substr($lcMethod, 0, 9) == 'findoneby') {
            $by = substr($method, 9, strlen($method));
            $method = 'findOneBy';
            $one = true;
        }

        if ($method == 'findBy' || $method == 'findOneBy') {
            if (isset($by)) {
                if (!isset($params[0])) {
                    throw new Exception('You must specify the value to ' . $method);
                }

                /*if ($one) {
                    $fieldName = static::_resolveFindByFieldsName($by);
                    if(false == $fieldName) {
                        throw new Exception('Column ' .$fieldName .' not found!');
                    }
                }*/

                return static::findBy($by, $params, $one);
            }
        }

        if(substr($lcMethod, 0, 10) == 'retrieveby') {
            $by = substr($method, 10, strlen($method));
            $method = 'retrieveBy';

            if (isset($by)) {
                if (!isset($params[0])) {
                    return false;
                    //@FIXED not need throw exception
                    //throw new Exception('You must specify the value to ' . $method);
                }

                return static::retrieveBy($by, $params);
            }
        }

//        return parent::$method($params);
    }

    public function __set($name, $value) {
        if (isset(static::$_schema[$name])) {
            $v = $this->fixData($value, static::$_schema[$name]);
            $ov = (isset($this->_data[$name])? $this->_data[$name]: null);
            $this->_data[$name] = $v;
            if ($ov !== $v)
                $this->_modifiedCols[$name] = true;
        } else {
            $this->$name = $value;
        }
    }

    public function __get($name) {
        if (isset(static::$_schema[$name])) {
            return $this->_data[$name];
        }

        return $this->$name;
    }

    public function __isset($name) {
        return (isset($this->_data[$name]))? true : isset($this->$name);
    }

    public function __unset($name) {
        if (isset(static::$_schema[$name])) {
            unset($this->_data[$name]);
        } else {
            unset($this->$name);
        }
    }

    public static function __callStatic($method, $params) {
        $lcMethod = strtolower($method);
        if (substr($lcMethod, 0, 6) == 'findby') {
            $by = substr($method, 6, strlen($method));
            $method = 'findBy';
            if (isset($by)) {
                if (!isset($params[0])) {
                    throw new Exception('You must specify the value to ' . $method);
                }

                return static::findBy($by, $params);
            }
        }

        $lcMethod = strtolower($method);
        if (substr($lcMethod, 0, 9) == 'findoneby') {
            $by = substr($method, 9, strlen($method));
            $method = 'findOneBy';

            if (isset($by)) {
                if (!isset($params[0])) {
                    throw new Exception('You must specify the value to ' . $method);
                }

                /*$fieldName = static::_resolveFindByFieldName($by);
                if(false == $fieldName) {
                    throw new Exception('Column ' .$fieldName .' not found!');
                }*/

                return static::findBy($by, $params, true);
            }
        }

        if(substr($lcMethod, 0, 10) == 'retrieveby') {
            $by = substr($method, 10, strlen($method));
            $method = 'retrieveBy';

            if (isset($by)) {
                if (!isset($params[0])) {
                    return false;
                    //@FIXED not need throw exception
                    //throw new Exception('You must specify the value to ' . $method);
                }

                return static::retrieveBy($by, $params);
            }
        }
    }
}