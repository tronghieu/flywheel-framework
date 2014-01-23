<?php
use Flywheel\Db\Connection;
use Flywheel\Util\Folder;
use Flywheel\Util\Inflection;
use Symfony\Component\Finder;
use Symfony\Component\Filesystem;
use Symfony\Component\Yaml;


function models_execute() {
    $params = func_get_arg(0);

    $builder = new BuildModels();
    if (isset($params['help'])) {
        die($builder->get_help());
    }

    if(isset($params['table'])){
        if(strpos($params['table'],',')!==false){
            $builder->_specialModels = explode(',',$params['table']);
        }else{
            $builder->_specialModels[] = $params['table'];
        }
    }
    $builder->mod = (isset($params['m']))? $params['m'] : 'preserve';
    $builder->tbPrefix = (isset($params['prefix']))? $params['prefix'] : '';
    $builder->package = (isset($params['pack']))? $params['pack']: '';


    $builder->run();
}

class BuildModels {
    public $mod;
    public $tbPrefix;
    public $package;
    public $destinationDir;

    public $_specialModels = array();
    /**
     * Connection Object
     * @var Connection
     */

    private $pkField;

    /**
     * Construct BuildModels object
     * @param Connection $conn
     */
    public function __construct() {
        $this->destinationDir  = ROOT_PATH.'/model/';
    }

    public function get_help() {
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
  --dir:        path/to/dir OM will be generated
                <optional> default is "/global/model/"
EOD;
    }

    /**
     * Get Tables List
     */
    private function _getTablesList() {
        $dir = ROOT_PATH.'/model/Structures/';

        $finder = new Finder\Finder();
        $finder->files()->in($dir);
        $tables = array();

        foreach ($finder as $file) {
            $fileName = @$file->getRelativePathname();
            $tmp = explode('.',$fileName);

            $table = Inflection::camelCaseToHungary($tmp[0]);

            if(!empty($this->_specialModels)){
                if(true == in_array($table,$this->_specialModels)){
                    $tables[] = $table;
                }
            }else{
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * Get List Table Column
     *
     * @param string $table
     */
    public function _getSchemas($table) {
        $table = str_replace(' ', '', ucwords(str_replace('_', ' ', trim(str_replace($this->tbPrefix, '', $table), '_'))));
        $fileSystem = new Filesystem\Filesystem();
        $dir = ROOT_PATH.'/model/Structures/';
        $file = $dir.$table.'.yml';

        if(false == $fileSystem->exists($file)){
            return false;
        }
        $yaml = new Yaml\Yaml();
        $values = $yaml->parse(file_get_contents($file));
        return $values;
    }
    /**
     * Get List Table Column
     *
     * @param string $table
     */
    public function _getListTableColumn($table) {
        $values = $this->_getSchemas($table);
        return $values['colums'];
    }

    /**
     * Get Config
     *
     * @param string $table
     */

    public function _getListTableConfig($table){
        $values = $this->_getSchemas($table);
        return $values['infos'];
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

    private function _writeClassMagicProperties($column, $infos) {

        $properties = array();
        $properties[] = $column;
        if (isset($infos['primary']) && $infos['primary']  == true) {
            $properties[] = 'primary';
            $this->pkField = $column;
        }
        if (isset($infos['auto_increment']) && $infos['auto_increment'] == true) {
            $properties[] = 'auto_increment';
        }
        $properties[] = 'type : ' .$infos['ntype'];

        if (isset($infos['type']) && $infos['type'] == 'string') {
            $properties[] = 'max_length : ' .$infos['length'];
        }
        $properties = ' * @property ' .$infos['type'] .' $' .$column .' ' .implode(' ', $properties) .PHP_EOL;
        return $properties;
    }

    private function _writeTableDefinition($name, &$columns) {
        return '';
    }

    private function _writeClassInfo($name, &$columns,$otherConfigs = array()) {
        $schema = '';
        $_dbConnectName = $name;
        if(isset($otherConfigs)){
            if($otherConfigs['_dbConnectName']!=''){
                $_dbConnectName = $otherConfigs['_dbConnectName'];
            }
        }


        $alias = strtolower($name[0]);
        $validate = '';
        if(!empty($columns)){
            foreach ($columns as $n => $property) {
                $schema .= $this->_writeColumnInfo($n, $property);
                $validate .= $this->_writeColumnValidatingRule($n, $property);
            }
        }


        $s = 'protected static $_tableName = \'' .$name .'\';'.PHP_EOL
            .'    protected static $_phpName = \'' .Inflection::hungaryNotationToCamel($name) .'\';' .PHP_EOL
            .'    protected static $_pk = \'' .$this->pkField .'\';'.PHP_EOL
            .'    protected static $_alias = \'' .$alias .'\';' .PHP_EOL
            .'    protected static $_dbConnectName = \'' .$_dbConnectName .'\';' .PHP_EOL
            .'    protected static $_instances = array();' .PHP_EOL
            .'    protected static $_schema = array(' .PHP_EOL .$schema .'     );' .PHP_EOL
            .'    protected static $_validate = array(' .PHP_EOL .$validate .'    );' .PHP_EOL
            .'    protected static $_validatorRules = array(' .PHP_EOL .$validate .'    );' .PHP_EOL
            .'    protected static $_init = false;' .PHP_EOL
            .'    protected static $_cols = array(\'' .@implode("','", array_keys($columns)) .'\');' .PHP_EOL;

        return $s;
    }

    private function _writeColumnInfo($name, $property) {
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

    private function _generateBaseModel($table) {
        $dir = $this->getDestinationDir() .'Base/';

        //generate base model
        $class = str_replace(' ', '', ucwords(str_replace('_', ' ', trim(str_replace($this->tbPrefix, '', $table), '_'))));

        $file = $dir .$class .'Base.php';
        Folder::create($dir);
        /* colums get from yaml files */
        $columns = $this->_getListTableColumn($table);
        $configs = $this->_getListTableConfig($table);
        /* comment for new base Class */
        $classComment = $this->_writeClassComment($class);

        $magicProperties = $magicMethod = '';
        if(!empty($columns)){
            foreach ($columns as $name => $property) {
                $magicProperties .= $this->_writeClassMagicProperties($name, $property);
                $magicMethod .= $this->_writeClassMagicMethod($class, $name, $property);
            }
        }
        $buffer = sprintf($this->baseModelTemplate(),
            $classComment,
            $magicProperties,
            $magicMethod,
            $class .'Base',
            $this->_writeClassInfo($table, $columns,$configs),
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
        echo ' -- Generate om:' .'Base/' .$class.'Base.php' ." succeed!\n";
    }

    private function getDestinationDir()
    {
        return $this->destinationDir;
    }

    private function _generateModel($table) {
        $dir = $this->getDestinationDir();

        $class = str_replace(' ', '', ucwords(str_replace('_', ' ', trim(str_replace($this->tbPrefix, '', $table), '_'))));
        $file = $dir .$class .'.php';

        if (file_exists($file)) {
            return false;
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
        echo ' -- Generate om:' .$class .'.php' ." success!\n";
    }

    public function run() {
        $tables = $this->_getTablesList();

        $this->package = Inflection::hungaryNotationToCamel($this->package);
        echo "\n\n";
        for($i = 0, $size = sizeof($tables); $i < $size; ++$i) {
            $table = $tables[$i];
            $this->_generateBaseModel($table);
            $this->_generateModel($table);
        }
    }
}