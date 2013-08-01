<?php
use Flywheel\Db\Connection;
use Flywheel\Db\Manager;
use Flywheel\Util\Folder;
use Flywheel\Util\Inflection;

function get_help() {
    return <<<EOD

DESCRIPTION
  Generate object models (OM) from database.
  - Base OM define table structure: primary field, schema, columns list,
    validate etc .... Base om will be re-generated when command run.
  - OM extend from Base OM, is object which project working with. This command only
    generate OM if not exist.

USAGE
  command gen:models

PARAMETER
  --cnf:        database config key in "/global/config/db.cfg.php"
                exp: --cnf=core
  --dir:        path/to/dir OM will be generated
                <optional> default is "/global/model/"
EOD;
}

function models_execute() {
    $params = func_get_arg(0);

    if (isset($params['help'])) {
        echo get_help();
        exit;
    }

    if (isset($params['config'])) { // using key
        $conn = Manager::getConnection($params['config']);
    } else {
        throw new \Flywheel\Exception('missing "config" config key in user parameter');
    }

    $builder = new BuildModels($conn);
    $builder->mod = (isset($params['m']))? $params['m'] : 'preserve';
    $builder->tbPrefix = (isset($params['prefix']))? $params['prefix'] : '';
    $builder->package = (isset($params['pack']))? $params['pack']: '';
    $builder->destinationDir = (isset($params['dir']))? $params['dir'] .DIRECTORY_SEPARATOR: GLOBAL_PATH;
    if (isset($params['igtbl'])) {
        $builder->setIgnoreTables($params['igtbl']);
    }

    if (isset($params['igprefix'])) {
        $builder->setIgnoreTablePrefixes($params['igprefix']);
    }

    if (isset($params['allow_tbl'])) {
        $builder->setAllowTables($params['allow_tbl']);
    }

    if (isset($params['allow_prefix'])) {
        $builder->setAllowTablePrefixes($params['allow_prefix']);
    }

    $builder->run();
}

class BuildModels {
    public $mod;
    public $tbPrefix;
    public $package;
    public $destinationDir;

    /**
     * Connection Object
     * @var Connection
     */
    private $_conn;

    private $pkField;

    /**
     * ignore table
     * @var array
     */
    private $_igrTbls = array();

    /**
     * ignore by table prefix
     * @var array
     */
    private $_igrTblPrefix = array();

    private $_allowTbls = array();

    private $_allowTblPrefix = array();

    /**
     * Construct BuildModels object
     * @param Connection $conn
     */
    public function __construct(Connection $conn) {
        $this->_conn = $conn;
    }

    /**
     * set ignore tables list
     * @param array $list
     */
    public function setIgnoreTables($list) {
        if (null == $list) {
            return;
        }
        $this->_igrTbls = explode(',', $list);
    }

    /**
     * set ignore tables by prefixes
     * @param string $prefixes
     */
    public function setIgnoreTablePrefixes($prefixes) {
        $prefixes = trim($prefixes);

        if (null == $prefixes) {
            return;
        }

        $this->_igrTblPrefix = explode(',', $prefixes);
    }

    public function setAllowTables($list) {
        if (null == $list) {
            return;
        }
        $this->_allowTbls = explode(',', $list);
    }

    function setAllowTablePrefixes($prefixes) {
        $prefixes = trim($prefixes);

        if (null == $prefixes) {
            return;
        }

        $this->_allowTblPrefix = explode(',', $prefixes);
    }

    /**
     * Get Tables List
     */
    private function _getTablesList() {
        $stmt = $this->_conn->query('SHOW TABLES');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get List Table Column
     *
     * @param string $table
     */
    private function _getListTableColumn($table) {
        $column = array();
        $stmt = $this->_conn->query('DESCRIBE ' . $this->_conn->getAdapter()->quoteIdentifierTable($table));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        for ($i = 0, $size = sizeof($result); $i < $size; ++$i) {
            $val = array_change_key_case($result[$i], CASE_LOWER);
            $decl = $this->getPortableDeclaration($val);
            $values = isset($decl['values']) ? $decl['values'] : array();

            //$val['default'] = $val['default'] == 'CURRENT_TIMESTAMP' ? null : $val['default'];

            $description = array(
                'name'          => $val['field'],
                'type'          => $decl['php_type'],
                'db_type'        => $decl['type'][0],
                'alltypes'      => $decl['type'],
                'ntype'         => $val['type'],
                'length'        => $decl['length'],
                'fixed'         => (bool) $decl['fixed'],
                'unsigned'      => (bool) $decl['unsigned'],
                'values'        => $values,
                'primary'       => (strtolower($val['key']) == 'pri'),
                'unique'        => (strtolower($val['key']) == 'uni'),
                'default'       => $val['default'],
                'notnull'       => (bool) ($val['null'] != 'YES'),
                'auto_increment' => (bool) (strpos($val['extra'], 'auto_increment') !== false),
                'extra'         => strtolower($val['extra'])
            );

            if ($description['default'] == 'CURRENT_TIMESTAMP') {
                $description['default'] = null;
                $description['notnull'] = false;
                $description['current_timestamp'] = true;
            }

            $column[$val['field']] = $description;
        }

        return $column;
    }

    /**
     * Maps a native array description of a field to a MDB2 datatype and length
     *
     * @param array  $field native field description
     * @return array containing the various possible types, length, sign, fixed
     */
    public function getPortableDeclaration(array $field)
    {
        $dbType = strtolower($field['type']);
        $dbType = strtok($dbType, '(), ');
        if ($dbType == 'national') {
            $dbType = strtok('(), ');
        }
        if (isset($field['length'])) {
            $length = $field['length'];
            $decimal = '';
        } else {
            $length = strtok('(), ');
            $decimal = strtok('(), ');
            if ( ! $decimal ) {
                $decimal = null;
            }
        }
        $type = array();
        $unsigned = $fixed = null;

        if ( ! isset($field['name'])) {
            $field['name'] = '';
        }

        $values = null;
        $scale = null;

        switch ($dbType) {
            case 'tinyint':
                $type[] = 'integer';
                $type[] = 'boolean';
                if (preg_match('/^(is|has)/', $field['name'])) {
                    $type = array_reverse($type);
                }
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 1;
                $php_type = $type[0];
                break;
            case 'smallint':
                $type[] = 'integer';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 2;
                $php_type = $type[0];
                break;
            case 'mediumint':
                $type[] = 'integer';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 3;
                $php_type = $type[0];
                break;
            case 'int':
            case 'integer':
                $type[] = 'integer';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 4;
                $php_type = $type[0];
                break;
            case 'bigint':
                $type[] = 'integer';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 8;
                $php_type = $type[0];
                break;
            case 'tinytext':
            case 'mediumtext':
            case 'longtext':
            case 'text':
            case 'text':
            case 'varchar':
                $fixed = false;
            case 'string':
            case 'char':
                $type[] = 'string';
                if ($length == '1') {
                    $type[] = 'boolean';
                    if (preg_match('/^(is|has)/', $field['name'])) {
                        $type = array_reverse($type);
                    }
                } elseif (strstr($dbType, 'text')) {
                    $type[] = 'clob';
                    if ($decimal == 'binary') {
                        $type[] = 'blob';
                    }
                }
                if ($fixed !== false) {
                    $fixed = true;
                }
                $php_type = $type[0];
                break;
            case 'enum':
                $type[] = 'enum';
                preg_match_all('/\'((?:\'\'|[^\'])*)\'/', $field['type'], $matches);
                $length = 0;
                $fixed = false;
                if (is_array($matches)) {
                    foreach ($matches[1] as &$value) {
                        $value = str_replace('\'\'', '\'', $value);
                        $length = max($length, strlen($value));
                    }
                    if ($length == '1' && count($matches[1]) == 2) {
                        $type[] = 'boolean';
                        if (preg_match('/^(is|has)/', $field['name'])) {
                            $type = array_reverse($type);
                        }
                    }

                    $values = $matches[1];
                }
                $php_type = ('boolean' == $type[0])? $type[0] : 'string';
                $type[] = 'integer';
                break;
            case 'set':
                $fixed = false;
                $type[] = 'text';
                $type[] = 'integer';
                $php_type = 'string';
                break;
            case 'date':
                $type[] = 'date';
                $length = null;
                $php_type = 'date';
                break;
            case 'datetime':
            case 'timestamp':
                $type[] = 'timestamp';
                $php_type = 'datetime';
                $length = null;
                break;
            case 'time':
                $type[] = 'time';
                $php_type = 'time';
                $length = null;
                break;
            case 'float':
            case 'double':
            case 'real':
                $type[] = 'float';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $php_type = 'number';
                break;
            case 'unknown':
            case 'decimal':
                if ($decimal !== null) {
                    $scale = $decimal;
                }
            case 'numeric':
                $type[] = 'decimal';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $php_type = 'number';
                break;
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'blob':
            case 'binary':
            case 'varbinary':
                $type[] = 'blob';
                $length = null;
                $php_type = 'string';
                break;
            case 'year':
                $type[] = 'integer';
                $type[] = 'date';
                $length = null;
                $php_type = 'string';
                break;
            case 'bit':
                $type[] = 'bit';
                break;
            case 'geometry':
            case 'geometrycollection':
            case 'point':
            case 'multipoint':
            case 'linestring':
            case 'multilinestring':
            case 'polygon':
            case 'multipolygon':
                $type[] = 'blob';
                $php_type = 'string';
                $length = null;
                break;
            default:
                $type[] = $field['type'];
                $length = isset($field['length']) ? $field['length']:null;
        }

        $length = ((int) $length == 0) ? null : (int) $length;
        $def =  array('type' => $type, 'length' => $length, 'unsigned' => $unsigned, 'fixed' => $fixed);
        $def['php_type'] = $php_type;
        if ($values !== null) {
            $def['values'] = $values;
        }
        if ($scale !== null) {
            $def['scale'] = $scale;
        }
        return $def;
    }

    /**
     * Help
     * print help message
     */
    public function help() {
    }

    public function modelTemplate() {
        $temp =  '/**'.PHP_EOL
            . '%s' . PHP_EOL
            . ' */' . PHP_EOL .PHP_EOL
            . 'require_once dirname(__FILE__) .\'/Base/%sBase.php\';' .PHP_EOL
            . 'class %s extends \\%sBase {' .PHP_EOL
            . '}';
        return $temp;
    }

    public function baseModelTemplate() {
        return
            'use Flywheel\\Db\\Manager;'.PHP_EOL
            .'use Flywheel\\Model\\ActiveRecord;'.PHP_EOL
            .'/**.' .PHP_EOL
            .'%s' .PHP_EOL //class comment
            .'%s' .PHP_EOL //class magic property
            .'%s' .PHP_EOL //class magic method
            .' */' .PHP_EOL
            .'abstract class %s extends ActiveRecord {' .PHP_EOL
            .'    %s' .PHP_EOL
            .'    public function setTableDefinition() {' .PHP_EOL //table defination
            .     '%s'
            .'    }' .PHP_EOL .PHP_EOL
            .'    /**' .PHP_EOL
            .'     * save object model' .PHP_EOL
            .'     * @return boolean' .PHP_EOL
            .'     * @throws \Exception' .PHP_EOL
            .'     */'.PHP_EOL
            .'    public function save($validate = true) {' .PHP_EOL
            .'        $conn = Manager::getConnection(self::getDbConnectName());'.PHP_EOL
            .'        $conn->beginTransaction();'.PHP_EOL
            .'        try {'.PHP_EOL
            .'            $this->_beforeSave();' .PHP_EOL
            .'            $status = $this->saveToDb($validate);' .PHP_EOL
            .'            $this->_afterSave();' .PHP_EOL
            .'            $conn->commit();' .PHP_EOL
            .'            self::addInstanceToPool($this, $this->getPkValue());'.PHP_EOL
            .'            return $status;'.PHP_EOL
            .'        }'.PHP_EOL
            .'        catch (\Exception $e) {'.PHP_EOL
            .'            $conn->rollBack();'.PHP_EOL
            .'            throw $e;'.PHP_EOL
            .'        }'.PHP_EOL
            .'    }'.PHP_EOL .PHP_EOL
            .'    /**'.PHP_EOL
            .'     * delete object model' .PHP_EOL
            .'     * @return boolean' .PHP_EOL
            .'     * @throws \Exception' .PHP_EOL
            .'     */' .PHP_EOL
            .'    public function delete() {' .PHP_EOL
            .'        $conn = Manager::getConnection(self::getDbConnectName());'.PHP_EOL
            .'        $conn->beginTransaction();' .PHP_EOL
            .'        try {'.PHP_EOL
            .'            $this->_beforeDelete();' .PHP_EOL
            .'            $this->deleteFromDb();' .PHP_EOL
            .'            $this->_afterDelete();' .PHP_EOL
            .'            $conn->commit();'.PHP_EOL
            .'            self::removeInstanceFromPool($this->getPkValue());' .PHP_EOL
            .'            return true;' .PHP_EOL
            .'        }'.PHP_EOL
            .'        catch (\Exception $e) {'.PHP_EOL
            .'            $conn->rollBack();'.PHP_EOL
            .'            throw $e;' .PHP_EOL
            .'        }'.PHP_EOL
            .'    }'.PHP_EOL
            .'}';
    }

    private function _writeClassComment($className) {
        return ' * ' .$className .PHP_EOL
            //.' *  This class has been auto-generated at ' .date('d/m/Y H:i:s', time()) .PHP_EOL
            .' * @version		$Id$' .PHP_EOL
            .' * @package		Model' .PHP_EOL;
    }

    private function _writeClassMagicMethod($class, $column, &$infos) {
        $s = '';
        $name = Inflection::camelize($column);
        if ($infos['type'] == 'date' || $infos['type'] == 'datetime'
            || $infos['type'] == 'time' || $infos['type'] == 'timestamp') {
            $s .= ' * @method void set' .$name .'(\Flywheel\Db\Type\DateTime $' .$column .') set' .$name .'(string $' .$column .') set ' .$column .' value'.PHP_EOL
                .' * @method \Flywheel\Db\Type\DateTime get' .$name.'() get '. $column .' value' .PHP_EOL
                .' * @method static \\'.$class .'[] findBy'.$name.'(\Flywheel\Db\Type\DateTime $' .$column .') findBy'.$name.'(string $' .$column .') find objects in database by ' .$column .PHP_EOL
                .' * @method static \\'.$class .' findOneBy'.$name.'(\Flywheel\Db\Type\DateTime $' .$column .') findOneBy'.$name.'(string $' .$column .') find object in database by ' .$column .PHP_EOL
                .' * @method static \\'.$class .' retrieveBy'.$name.'(\Flywheel\Db\Type\DateTime $' .$column .') retrieveBy'.$name.'(string $' .$column .') retrieve object from poll by ' .$column .', get it from db if not exist in poll' .PHP_EOL .PHP_EOL;
        } else {
            $s .= ' * @method void set' .$name .'(' .$infos['type'] .' $' .$column .') set ' .$column .' value'.PHP_EOL
                .' * @method ' .$infos['type'].' get' .$name.'() get '. $column .' value' .PHP_EOL
                .' * @method static \\'.$class .'[] findBy'.$name.'(' .$infos['type'] .' $' .$column .') find objects in database by ' .$column .PHP_EOL
                .' * @method static \\'.$class .' findOneBy'.$name.'(' .$infos['type'] .' $' .$column .') find object in database by ' .$column .PHP_EOL
                .' * @method static \\'.$class .' retrieveBy'.$name.'(' .$infos['type'] .' $' .$column .') retrieve object from poll by ' .$column .', get it from db if not exist in poll' .PHP_EOL .PHP_EOL;
        }

        return $s;

    }

    private function _writeClassMagicProperties($column, &$infos) {
        $properties = array();
        $properties[] = $column;
        if ($infos['primary'] == true) {
            $properties[] = 'primary';
            $this->pkField = $column;
        }
        if ($infos['auto_increment'] == true) {
            $properties[] = 'auto_increment';
        }
        $properties[] = 'type : ' .$infos['ntype'];

        if ($infos['type'] == 'string') {
            $properties[] = 'max_length : ' .$infos['length'];
        }
        $properties = ' * @property ' .$infos['type'] .' $' .$column .' ' .implode(' ', $properties) .PHP_EOL;
        return $properties;
    }

    private function _writeTableDefinition($name, &$columns) {
        return '';
        $s = "        self::setDbConnectName('{$name}');" .PHP_EOL
            .'        if (self::$_initFlag) {' .PHP_EOL
            .'            return ;' .PHP_EOL
            .'        }' .PHP_EOL
            ."        self::setTableName('{$name}');" .PHP_EOL
            ."        self::setPrimaryKeyField('{$this->pkField}');" .PHP_EOL;

        foreach ($columns as $name => $property) {
            $default = 'null';
            if (isset($property['default']) && $property['default'] != null) {
                if ($property['type'] == 'float' || $property['type'] == 'integer' || $property['type'] == 'decimal') {
                    $default = $property['default'];
                }
                else {
                    $default = '\'' .$property['default'] .'\'';
                }
            } else if ($property['notnull'] == true) {
                if ($property['type'] == 'float' || $property['type'] == 'integer' || $property['type'] == 'decimal') {
                    $default = '0';
                }
                else {
                    $default = '\'\'';
                }
            }

            $option = array();
            if ($property['notnull'] != true) {
                $option[] = '\'allow_null\' => true';
            }

            if (sizeof($option) > 0) {
                $option = ', array('
                    .'                '.implode(PHP_EOL, $option) .PHP_EOL
                    .'            )';
            } else {
                $option = '';
            }

            $s .='        $this->hasColumn(\''.$name.'\', \'' .$property['type'] .'\', ' .$default .$option .');' .PHP_EOL;
        }

        return $s;
    }

    private function _writeClassInfo($name, &$columns) {
        $schema = '';
        $alias = strtolower($name[0]);
        $validate = '';
        foreach ($columns as $n => $property) {
            $schema .= $this->_writeColumnInfo($n, $property);
            $validate .= $this->_writeColumnValidatingRule($n, $property);
        }


        $s = 'protected static $_tableName = \'' .$name .'\';'.PHP_EOL
            .'    protected static $_phpName = \'' .Inflection::hungaryNotationToCamel($name) .'\';' .PHP_EOL
            .'    protected static $_pk = \'' .$this->pkField .'\';'.PHP_EOL
            .'    protected static $_alias = \'' .$alias .'\';' .PHP_EOL
            .'    protected static $_dbConnectName = \'' .$name .'\';' .PHP_EOL
            .'    protected static $_instances = array();' .PHP_EOL
            .'    protected static $_schema = array(' .PHP_EOL .$schema .'     );' .PHP_EOL
            .'    protected static $_validate = array(' .PHP_EOL .$validate .'    );' .PHP_EOL
            .'    protected static $_init = false;' .PHP_EOL
            .'    protected static $_cols = array(\'' .implode("','", array_keys($columns)) .'\');' .PHP_EOL;

        return $s;
    }

    private function _writeColumnInfo($name, $property) {
        $default = 'null';
        $option = array();
        $option[] = "'name' => '{$property['name']}'";
        if (isset($property['default']) && $property['default'] != null) {
            if ($property['type'] == 'float' || $property['type'] == 'integer'
                || $property['type'] == 'double' || $property['type'] == 'number') {
                $default = $property['default'];
            }
            else {
                $default = '\'' .$property['default'] .'\'';
            }
            $option[] = "'default' => {$default}";
        }

        $option[] = "'not_null' => " .var_export($property['notnull'], true);

        $option[] ="'type' => '{$property['type']}'";
        if ($property['primary']) {
            $option[] = "'primary' => " .var_export($property['primary'], true);
        }

        if ($property['type'] == 'number' || $property['type'] == 'integer') {
            $option[] = "'auto_increment' => " .var_export($property['auto_increment'], true);
        }

        $option[] = "'db_type' => " .var_export($property['ntype'], true);
        if ($property['length']) {
            $option[] = "'length' => " .strtolower(var_export($property['length'], true));
        }

        return "        '{$name}' => array("
            .implode(',' .PHP_EOL.'                ', $option)
            ."),".PHP_EOL;
    }



    private function _writeColumnValidatingRule($name, $property)
    {
        if ($property['auto_increment']) {
            return null;
        }

        $real_name = str_replace('_', ' ', $name);

        $option = array();
        if ($property['notnull']) {
            //do nothing
        }

        if ('enum' == $property['db_type']) {
            $option[] = "            array('name' => 'ValidValues'," .PHP_EOL
                ."                'value' => '" .implode('|', $property['values'])."'," .PHP_EOL
                ."                'message'=> '{$real_name}\\'s values is not allowed'" .PHP_EOL
                ."            )," .PHP_EOL;
        }

        if ($property['type'] == 'string' && $property['length'] > 0) {
            /*$option[] = "            array('name' => 'MaxLength'," .PHP_EOL
                ."                'value' => {$property['length']}, " .PHP_EOL
                ."                'message' => '{$real_name} is too long, can not be longer than {$property['length']} characters'," .PHP_EOL
                ."            )," .PHP_EOL;*/
        }

        if ($property['unique']) {
            $option[] = "            array('name' => 'Unique'," .PHP_EOL
                ."                'message'=> '{$real_name}\\'s was used'" .PHP_EOL
                ."            )," .PHP_EOL;
        }

        if (!empty($option)) {
            return "        '{$name}' => array(".PHP_EOL
                .implode('', $option)
                ."        ),".PHP_EOL;
        }

        return null;
    }

    private function _isInIgnoreList($column) {
        if(count($this->_igrTbls) > 0) {
            if (in_array($column, $this->_igrTbls)) {
                return true;
            }
        }

        if (count($this->_igrTblPrefix) > 0) {
            foreach ($this->_igrTblPrefix as $igPrefix) {
                if(strpos($column, $igPrefix) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function _isInAllowList($column) {
        $return = true;
        if(count($this->_allowTbls) > 0) {
            $return = false;
            if (in_array($column, $this->_allowTbls)) {
                return true;
            }
        } else {
            $return = true;
        }

        if (count($this->_allowTblPrefix) > 0) {
            $return = false;
            foreach ($this->_allowTblPrefix as $allowPrefix) {
                if(strpos($column, $allowPrefix) === 0) {
                    return true;
                }
            }
        } else {
            $return = true;
        }

        return $return;
    }

    private function _generateBaseModel($table, $class) {
        $dir = $this->getDestinationDir() .'/model/Base/';
        //generate base model
        $file = $dir .$class .'Base.php';
        Folder::create($dir);
        $columns = $this->_getListTableColumn($table);
        $classComment = $this->_writeClassComment($class);
        $magicProperties = '';
        $magicMethod = '';
        foreach ($columns as $name => $property) {
            $magicProperties .= $this->_writeClassMagicProperties($name, $property);
            $magicMethod .= $this->_writeClassMagicMethod($class, $name, $property);
        }

        $buffer = sprintf($this->baseModelTemplate(),
            $classComment,
            $magicProperties,
            $magicMethod,
            $class .'Base',
            $this->_writeClassInfo($table, $columns),
            $this->_writeTableDefinition($table, $columns));

        $fp = @fopen($file, 'w');
        if ($fp === false) {
            throw new \Flywheel\Exception("Couldn't write model data. Failed to open $file");
        }

        fwrite($fp, "<?php ".PHP_EOL .$buffer);
        fclose($fp);

        unset($buffer);
        unset($columns);
        unset($classComment);
        unset($name);
        unset($property);
        unset($classProperties);
        echo 'generate om:' .'Base/' .$class.'Base.php' ." succeed!\n";
    }

    private function getDestinationDir()
    {
        if (null == $this->destinationDir)
            $this->destinationDir = GLOBAL_PATH;
        return $this->destinationDir;
    }

    private function _generateModel($table, $class) {
        $dir = $this->getDestinationDir() .'/model/';
        $file = $dir .$class .'.php';
        if (file_exists($file)) {
            return;
        }

        $classComment = $this->_writeClassComment($class);

        $buffer = sprintf($this->modelTemplate(),
            $classComment,
            $class,
            $class,
            $class);

        $fp = @fopen($file, 'w');
        if ($fp === false) {
            throw new \Flywheel\Exception("Couldn't write model data. Failed to open $file");
        }

        fwrite($fp, "<?php ".PHP_EOL .$buffer);
        fclose($fp);
        echo 'generate om:' .$class .'.php' ." success!\n";
    }

    public function run() {
        $tables = $this->_getTablesList();
//        $db = $this->conn->getDatabase();
        $this->package = Inflection::hungaryNotationToCamel($this->package);
        for($i = 0, $size = sizeof($tables); $i < $size; ++$i) {
            //check is in ignore list
            $ignore = $this->_isInIgnoreList($tables[$i]);

            //check allow list
            $allow = $this->_isInAllowList($tables[$i]);

            $build = (!$ignore) && $allow;

            if (!$build) { //except table
                continue;
            }

            $modelClass = str_replace(' ', '', ucwords(str_replace('_', ' ', trim(str_replace($this->tbPrefix, '', $tables[$i]), '_'))));
            $this->_generateBaseModel($tables[$i], $modelClass);
            $this->_generateModel($tables[$i], $modelClass);
        }
    }
}