<?php
use Flywheel\Db\Connection;
use Flywheel\Db\Manager;
use Flywheel\Util\Folder;
use Flywheel\Util\Inflection;
use Symfony\Component\Yaml;
use Symfony\Component\Finder;
use Symfony\Component\Filesystem;
use Flywheel\Config\ConfigHandler;


function schemas_execute() {

    $params = func_get_arg(0);

    ConfigHandler::import('root.config');

    if (isset($params['config'])) {
        $conn = Manager::getConnection($params['config']);
    } else {
        throw new \Flywheel\Exception('missing "config" config key in user parameter');
    }

    $builder = new BuildSchemas($conn);
    $builder->_configKey = $params['config'];

    if (isset($params['help'])) die($builder->get_help());


    $builder->mod = (isset($params['m']))? $params['m'] : 'preserve';
    $builder->tbPrefix = (isset($params['prefix']))? $params['prefix'] : '';
    $builder->package = (isset($params['pack']))? $params['pack']: '';

    if(isset($params['table'])){
        $builder->setSpecialTbl($params['table']);
    }

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

class BuildSchemas {
    public $mod;
    public $tbPrefix;
    public $package;
    public $destinationDir;

    /**
     * Connection Object
     * @var Connection
     */
    private $_conn = null;

    public $_configKey = '';
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

    private $_specialTbl = '';
    /**
     * Construct BuildModels object
     * @param Connection $conn
     */
    public function __construct($conn = null) {
        if($conn instanceof Connection){
            $this->_conn = $conn;
        }
        $this->destinationDir = ROOT_PATH.'/model/Structures/';
    }

    public function setSpecialTbl($table){
        $this->_specialTbl = $table;
    }

    public function getSpecialTbl(){
        if(trim($this->_specialTbl) == ''){
            return false;
        }
        return $this->_specialTbl;
    }
    /*
     * Get Help
     * */

    public function get_help() {
        return <<<EOD
DESCRIPTION
  Generate structure from database.

USAGE
  command gen:schemas

PARAMETER
  --cnf:        database config key in "/global/config/db.cfg.php"
                exp: --cnf=core
  --dir:        path/to/dir OM will be generated
                <optional> default is "model/"
EOD;
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
        $tables = array();
        $strTable = $this->getSpecialTbl();

        if($strTable != '' && is_string($strTable)){
            if(strpos($strTable,',') !== false ){

                $arrayTables = explode(',',$strTable);

                foreach ($arrayTables as $table){
                    $tables[] = $table;
                }
            }else{
                $tables[] = $strTable;
            }
        }
        if(!empty($tables)) return $tables;

        $stmt = $this->_conn->query('SHOW TABLES');
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if(empty($tables)){
            return false;
        }
        return $tables;
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
    public function help() {}

    private function _writeComment($className) {
        return array(
            "#$className",
            "#Schema for $className OMs"
        );

    }


    private function _isInIgnoreList($column) {
        if(count($this->_igrTbls) > 0) {
            if (in_array($column, $this->_igrTbls)) return true;
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

    private function _generateSchemas($table, $class) {

        $dir = $this->getDestinationDir();

        $file = $dir .$class .'.yml';
        Folder::create($dir);

        $structArray = array(
            array('comment'=>$this->_writeComment($class)),
            array('colums'=>$this->_getListTableColumn($table)),
            array('infos'=>$this->_generaInfo($table))
        );


        $yaml = $this->dumpYaml($structArray);

        $fileSystem = new Symfony\Component\Filesystem\Filesystem();
        $fileSystem->dumpFile($file, $yaml);
        return $class.".yml";

    }

    private function dumpYaml($datas){
        $dumper = new Yaml\Dumper();
        ob_start();
        if(!empty($datas)){
            foreach ($datas as $data){
                echo $dumper->dump($data,2);
            }
        }
        $yaml = ob_get_contents();
        ob_end_clean();

        return $yaml;
    }

    public function getDestinationDir(){
        return $this->destinationDir;
    }

    private function _generaInfo($name) {
        return array(
            '_dbConnectName'=>   $name,
        );
    }

    public function run() {

        if(null == $this->_conn ) die('Connection fail');

        $tables = $this->_getTablesList();

        $this->package = Inflection::hungaryNotationToCamel($this->package);
        echo "\n\n";

        $tableDestination = array();
        for($i = 0, $size = sizeof($tables); $i < $size; ++$i) {

            /*$ignore = $this->_isInIgnoreList($tables[$i]);

            $allow = $this->_isInAllowList($tables[$i]);

            $build = (!$ignore) && $allow;

            if (!$build) continue;*/

            $schemaName = str_replace(' ', '', ucwords(str_replace('_', ' ', trim(str_replace($this->tbPrefix, '', $tables[$i]), '_'))));

            array_push($tableDestination,$tables[$i]);

            $schema = $this->_generateSchemas($tables[$i], $schemaName);
            echo " -- Generate schema ".$schema." success \n";
        }

        $this->getSpecialTbl() != false?$note = '[SOME]':$note = '[ALL]';

        echo "\nAre you sure you want to generate ".$note." OMs ?\nType 'yes' to continue or 'no' to abort': ";


        $handle = fopen ("php://stdin","r");

        if(trim(fgets($handle)) != 'yes') {
            echo 'Gen OMs aborted!'; exit;
        }
        $nextRunning = "php command gen:models --config=".$this->_configKey;

        if($this->getSpecialTbl() != false){
            $tableToModels = @implode(',',$tableDestination);
            $nextRunning.=" --table=".$tableToModels;
        }
        echo shell_exec($nextRunning);exit;

    }
}