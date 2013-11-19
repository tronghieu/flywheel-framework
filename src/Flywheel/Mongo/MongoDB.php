<?php
namespace Flywheel\Mongo;

use Flywheel\Config\ConfigHandler as ConfigHandler;
/**
 * @t90
 */
class MongoDB
{
    /**
     * Config file data
     *
     * @var array
     * @access protected
     */
    public $_configParam = array();

    /**
     * Config file data
     *
     * @var array
     * @access protected
     */
    public $_configData = array();

    /**
     * Connection resource.
     *
     * @var null|\Mongo
     * @access protected
     */
    protected $_connection = null;

    /**
     * Database handle.
     *
     * @var \MongoDB
     * @access protected
     */
    protected $_dbhandle = null;

    /**
     * Database host.
     *
     * @var mixed
     * @access protected
     */
    private $_dsn = '';

    /**
     * Database name.
     *
     * @var string
     * @access protected
     */
    protected $_dbname = '';

    /**
     * Persist connection.
     *
     * @var boolean
     * @access protected
     */
    protected $_persist = true;

    /**
     * Persist key.
     *
     * @var string
     * @access protected
     */
    protected $_persistKey = 'mongoqb';

    /**
     * Use replica set.
     *
     * @var boolean|string
     * @access protected
     */
    protected $_replicaSet = true;

    /**
     * Query safety value.
     *
     * @var string
     * @access protected
     */
    protected $_querySafety = null;

    /**
     * Selects array.
     *
     * @var array
     * @access protected
     */
    protected $_selects = array();

    /**
     * Wheres array.
     *
     * Public to make debugging easier.
     *
     * @var array
     * @access public
     */
    public $wheres = array();

    /**
     * Sorts array.
     *
     * @var array
     * @access protected
     */
    protected $_sorts = array();

    /**
     * Updates array.
     *
     * Public to make debugging easier
     *
     * @var array
     * @access public
     */
    public $updates = array();

    /**
     * Results limit.
     *
     * @var integer
     * @access protected
     */
    protected $_limit = 999999;

    /**
     * Query log.
     *
     * @var integer
     * @access protected
     */
    protected $_queryLog = array();

    /**
     * Result offset.
     *
     * @var integer
     * @access protected
     */
    protected $_offset = 0;
    public static $instance = null;

    /**
     * Constructor
     *
     * Automatically check if the Mongo PECL include has been
     *  installed/enabled.
     *
     * @access public
     * @param array $config
     * @param bool $connect
     * @throws Exception
     * @return \Flywheel\Mongo\MongoDB
     */

    public function __construct($config = array(), $connect = true)
    {
        if (!class_exists('Mongo') && !class_exists('MongoClient'))
            throw new Exception('The Mongo PECL include has not been installed or enabled');
        $this->_loadConfig();
        $this->setConfig($config, $connect);
    }

    public function _loadConfig()
    {
        $this->_configParam = ConfigHandler::load('global.config.mongodb', 'mongodb');
        if($this->_configParam)
        {
            $params = $this->_configParam;
            if($params['host'] == "" || $params['port'] == "")
            {
                throw new Exception('No host or port configured to connect to Mongo');
            }

            if(!empty($params['db'])) {
                $this->db = $params['db'];
            } else {
                throw new Exception('No Mongo database selected');
            }

            $this->_dbname = $params['db'];
            $this->_replicaSet = $params['replica_set'];
            $this->_querySafety = $params['query_safety'];
            $this->_persistKey = $params['persist_key'];
            $this->_persist = $params['persist'];

            $this->_configData = array(
                'hostname'      =>  "mongodb://{$params['host']}:{$params['port']}/{$params['db']}",
                'dsn'           =>  "mongodb://{$params['host']}:{$params['port']}/{$params['db']}",
                'persist'       =>  true,
                'persist_key'   =>  $this->_persistKey,
                'replica_set'   =>  $this->_replicaSet,
                'query_safety'  =>  $this->_querySafety
            );
            $this->_dsn = $this->_configData['dsn'];
        }
    }
    public function __destruct()
    {
        unset($this);
    }
    /** @getInstance() */
    public static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new self();
        return self::$instance;
    }

    /**
     * Set the configuation.
     *
     * @param mixed $config Array of configuration parameters
     *
     * @param bool $connect
     * @throws Exception
     * @access public
     * @return void
     */
    public function setConfig($config = array(), $connect = true)
    {
        if (is_array($config)) {
            $this->_configData = array_merge($config, $this->_configData);
        } else {
            throw new Exception('No config variables passed');
        }

        $this->_connectionString();

        if ($connect) {
            $this->_connect();
        }
    }

    /**
     * Switch database.
     *
     * @param string $dsn
     * @param string $qb
     * @throws Exception
     * @internal param string $database Database name
     *
     * @access public
     * @return boolean
     */
    public function switchDb($dsn = '',$qb = '')
    {
        $r = new \ReflectionClass($qb);
        $_connection = $r->getProperty('_connection');
        if (empty($dsn)) {
            throw new Exception('To switch Mongo databases, a DSN must be specified');
        }

        try {
            // Regenerate the connection string and reconnect
            $this->_configData['dsn'] = $dsn;
            $this->_connectionString();
            $this->_connect();
        } catch (\MongoConnectionException $Exception) {
            throw new Exception('Unable to switch Mongo Databases: ' .
                $Exception->getMessage());
        }
    }

    /**
     * Drop a database.
     *
     * @param string $database Database name
     *
     * @throws Exception
     * @access public
     * @return boolean
     */
    public function dropDb($database = '')
    {
        if (empty($database)) {

            throw new Exception('Failed to drop Mongo database because name is empty');

        } else {
            try {
                $this->_connection->{$database}->drop();

                return true;
            }
            catch (\Exception $Exception) {
                throw new Exception('Unable to drop Mongo database `' .
                    $database . '`: ' . $Exception->getMessage());
            }

        }
    }

    /**
     * Drop a collection.
     *
     * @param string $database   Database name
     * @param string $collection Collection name
     *
     * @throws Exception
     * @access public
     * @return boolean
     */
    public function dropCollection($database = '', $collection = '')
    {
        if (empty($database)) {
            throw new Exception('Failed to drop Mongo collection because database name is empty', 500);
        }

        if (empty($collection)) {
            throw new Exception('Failed to drop Mongo collection because collection name is empty', 500);
        } else {
            try {
                $this->_connection->{$database}->{$collection}->drop();

                return true;
            }
                //@start
            catch (\Exception $Exception) {
                throw new Exception('Unable to drop Mongo collection `' .
                    $collection . '`: ' . $Exception->getMessage(), 500);
                //@end
            }
        }
    }

    /**
     * Set select parameters.
     *
     * Determine which fields to include OR which to exclude during the query
     *  process. Currently, including and excluding at the same time is not
     *  available, so the $includes array will take precedence over the
     *  $excludes array.  If you want to only choose fields to exclude, leave
     *  $includes an empty array().
     *
     * @param array $includes Fields to include in the returned result
     * @param array $excludes Fields to exclude from the returned result
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function select($includes = array(), $excludes = array())
    {
        if ( ! is_array($includes)) {
            $includes = array();
        }

        if ( ! is_array($excludes)) {
            $excludes = array();
        }

        if ( ! empty($includes)) {
            foreach ($includes as $include) {
                $this->_selects[$include] = 1;
            }
        } else {
            foreach ($excludes as $exclude) {
                $this->_selects[$exclude] = 0;
            }
        }
        return $this;
    }

    /**
     * Set where paramaters
     *
     * Get the documents based on these search parameters.  The $wheres array
     *  should be an associative array with the field as the key and the value
     *  as the search criteria.
     *
     * @param array|string $wheres Array of where conditions. If string, $value
     *  must be set
     * @param mixed $value Value of $wheres if $wheres is a string
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function where($wheres = array(), $value = null)
    {
        if (is_array($wheres)) {
            foreach ($wheres as $where => $value) {
                $this->wheres[$where] = $value;
            }
        } else {
            $this->wheres[$wheres] = $value;
        }

        return $this;
    }

    /**
     * or_where.
     *
     * Get the documents where the value of a $field may be something else
     *
     * @param array $wheres Array of where conditions
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function orWhere($wheres = array())
    {
        if (count($wheres) > 0) {
            if ( ! isset($this->wheres['$or']) OR !
            is_array($this->wheres['$or'])) {
                $this->wheres['$or'] = array();
            }

            foreach ($wheres as $where => $value) {
                $this->wheres['$or'][] = array($where => $value);
            }
        }

        return $this;
    }

    /**
     * Where in array.
     *
     * Get the documents where the value of a $field is in a given $in array().
     *
     * @param string $field     Name of the field
     * @param array  $inValues Array of values that $field could be
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereIn($field = '', $inValues = array())
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$in'] = $inValues;

        return $this;
    }

    /**
     * Where all are in array.
     *
     * Get the documents where the value of a $field is in all of a given $in
     *  array().
     *
     * @param string $field     Name of the field
     * @param array  $inValues Array of values that $field must be
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereInAll($field = '', $inValues = array())
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$all'] = $inValues;

        return $this;
    }

    /**
     * Where not in
     *
     * Get the documents where the value of a $field is not in a given $in
     *  array().
     *
     * @param string $field     Name of the field
     * @param array  $inValues Array of values that $field isnt
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereNotIn($field = '', $inValues = array())
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$nin'] = $inValues;

        return $this;
    }

    /**
     * Where greater than
     *
     * Get the documents where the value of a $field is greater than $value.
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is greater than
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereGt($field = '', $value = null)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$gt'] = $value;

        return $this;
    }

    /**
     * Where greater than or equal to
     *
     * Get the documents where the value of a $field is greater than or equal to
     *  $value.
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is greater than or equal to
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereGte($field = '', $value = null)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$gte'] = $value;

        return $this;
    }

    /**
     * Where less than.
     *
     * Get the documents where the value of a $field is less than $x
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is less than
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereLt($field = '', $value = null)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$lt'] = $value;

        return $this;
    }

    /**
     * Where less than or equal to
     *
     * Get the documents where the value of a $field is less than or equal to $x
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is less than or equal to
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereLte($field = '', $value = null)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$lte'] = $value;

        return $this;
    }

    /**
     * Where between two values
     *
     * Get the documents where the value of a $field is between $x and $y
     *
     * @param string $field   Name of the field
     * @param int    $valueX Value that $field is greater than or equal to
     * @param int    $valueY Value that $field is less than or equal to
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereBetween($field = '', $valueX = 0, $valueY = 0)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$gte'] = $valueX;
        $this->wheres[$field]['$lte'] = $valueY;

        return $this;
    }

    /**
     * Where between two values but not equal to
     *
     * Get the documents where the value of a $field is between but not equal to
     *  $x and $y
     *
     * @param string $field   Name of the field
     * @param int    $valueX Value that $field is greater than or equal to
     * @param int    $valueY Value that $field is less than or equal to
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereBetweenNe($field = '', $valueX, $valueY)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$gt'] = $valueX;
        $this->wheres[$field]['$lt'] = $valueY;

        return $this;
    }

    /**
     * Where not equal to
     *
     * Get the documents where the value of a $field is not equal to $x
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is not equal to
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereNe($field = '', $value)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$ne'] = $value;

        return $this;
    }

    /**
     * Where near
     *
     * Get the documents nearest to an array of coordinates (your collection
     *  must have a geospatial index)
     *
     * @param string  $field     Name of the field
     * @param array   $coords    Array of coordinates
     * @param integer $distance  Value of the maximum distance to search
     * @param boolean $spherical Treat the Earth as spherical instead of flat
     *  (useful when searching over large distances)
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereNear($field = '', $coords = array(), $distance = null,
                              $spherical = false)
    {
        $this->_whereInit($field);

        if ($spherical) {
            $this->wheres[$field]['$nearSphere'] = $coords;
        } else {
            $this->wheres[$field]['$near'] = $coords;
        }

        if ($distance !== null) {
            $this->wheres[$field]['$maxDistance'] = $distance;
        }

        return $this;
    }

    /**
     * Where like
     *
     * Get the documents where the (string) value of a $field is like a value.
     *  The defaults
     * allow for a case-insensitive search.
     *
     * @param string $field The field
     * @param string $value The value to match against
     * @param string $flags Allows for the typical regular
     *  expression flags:<br>i = case insensitive<br>m = multiline<br>x = can
     *  contain comments<br>l = locale<br>s = dotall, "." matches everything,
     *  including newlines<br>u = match unicode
     * @param boolean $enableStartWildcard If set to anything other than true,
     *  a starting line character "^" will be prepended to the search value,
     *  representing only searching for a value at the start of a new line.
     * @param boolean $enableEndWildcard If set to anything other than true,
     *  an ending line character "$" will be appended to the search value,
     *  representing only searching for a value at the end of a line.
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function whereLike($field = '', $value = '', $flags = 'i',
                              $enableStartWildcard = true, $enableEndWildcard = true)
    {
        $field = (string) trim($field);
        $this->_whereInit($field);
        $value = (string) trim($value);
        $value = quotemeta($value);

        if ($enableStartWildcard !== true) {
            $value = '^' . $value;
        }

        if ($enableEndWildcard !== true) {
            $value .= '$';
        }

        $regex = '/' . $value . '/' . $flags;
        $this->wheres[$field] = new \MongoRegex($regex);

        return $this;
    }

    /**
     * Order results by
     *
     * Sort the documents based on the parameters passed. To set values to
     *  descending order, you must pass values of either -1, false, 'desc', or
     *  'DESC', else they will be set to 1 (ASC).
     *
     * @param array $fields Array of fields with their sort type (asc or desc)
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function orderBy($fields = array())
    {
        if(!empty($fields) && is_array($fields)){
            foreach( $fields as $field => $order) {
                if ($order === -1 OR $order === false OR strtolower($order) === 'desc') {
                    $this->_sorts[$field] = -1;
                } else {
                    $this->_sorts[$field] = 1;
                }
            }
        }

        return $this;
    }

    /**
     * Limit the number of results
     *
     * Limit the result set to $limit number of documents
     *
     * @param int $limit The maximum number of documents that will be returned
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function limit($limit = 99999)
    {
        if ($limit !== null AND is_numeric($limit) AND $limit >= 1) {
            $this->_limit = (int) $limit;
        }

        return $this;
    }

    /**
     * Offset results
     *
     * Offset the result set to skip $x number of documents
     *
     * @param int $offset The number of documents to offset the search by
     *
     * @access public
     * @return MongoDB
     */
    public function offset($offset = 0)
    {
        if ($offset !== null AND is_numeric($offset) AND $offset >= 1) {
            $this->_offset = (int) $offset;
        }

        return $this;
    }

    /**
     * Get where.
     *
     * Get the documents based upon the passed parameters
     *
     * @param string $collection Name of the collection
     * @param array  $where      Array of where conditions
     *
     * @access public
     * @return array
     */
    public function getWhere($collection = '', $where = array())
    {
        return $this->where($where)->get($collection);
    }

    /**
     * Get results
     *
     * Return the found documents
     *
     * @param string $collection    Name of the collection
     * @param bool   $returnCursor Return the native document cursor
     *
     * @access public
     * @return array
     */
    public function get($collection = '', $returnCursor = false)
    {
        if (empty($collection)) {
            //throw new Exception('In order to retrieve documents from Mongo, a collection name must be passed');
        }

        $cursor = $this->_dbhandle
            ->{$collection}
            ->find($this->wheres, $this->_selects)
            ->limit($this->_limit)
            ->skip($this->_offset)
            ->sort($this->_sorts);
        // Clear
        $this->_clear($collection, 'get');

        // Return the raw cursor if wanted
        if ($returnCursor === true) {
            return $cursor;
        }

        $documents = array();

        while ($cursor->hasNext()) {
            try {
                $documents[] = $cursor->getNext();
            }
            catch (\MongoCursorException $Exception) {
                throw new Exception($Exception->getMessage());
            }
        }

        return $documents;
    }

    /**
     * Count.
     *
     * Count the number of found documents
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return int
     */
    public function count($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('In order to retrieve a count of documents from Mongo, a collection name must be passed');
        }

        $count = $this->_dbhandle
            ->{$collection}
            ->find($this->wheres)
            ->limit($this->_limit)
            ->skip($this->_offset)
            ->count();
        $this->_clear($collection, 'count');
        return $count;
    }

    /**
     * Insert.
     *
     * Insert a new document
     *
     * @param string $collection Name of the collection
     * @param array  $insert     The document to be inserted
     * @param array  $options    Array of options
     *
     * @access public
     * @return boolean
     */
    public function insert($collection = '', $insert = array(), $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to insert into');
        }

        if (count($insert) === 0 OR ! is_array($insert)) {
            throw new Exception('Nothing to insert into Mongo collection or insert is not an array');
        }

        $options = array_merge(
            array(
                $this->_querySafety => true
            ),
            $options
        );

        try {
            $uk = $this->_dbhandle
                ->{$collection}
                ->find()
                ->limit(1)
                ->sort(array('_id' => -1));
            if($uk->hasNext())
            {
                $data = $uk->getNext();
                $insert['uk'] = intval($data['uk'])+1;
            }
            else
            {
                $insert['uk'] = 1;
            }
            $this->_dbhandle
                ->{$collection}
                ->insert($insert, $options);

            if (isset($insert['_id'])) {
                return $insert['_id'];
            } else {
                //@start
                return false;
                //@end
            }
        }
            //@start
        catch (\MongoCursorException $Exception) {
            throw new Exception('Insert of data into Mongo failed: ' . $Exception->getMessage());
            //@end
        }
    }

    /**
     * Insert.
     *
     * Insert a new document
     *
     * @param string $collection Name of the collection
     * @param array  $insert     The document to be inserted
     * @param array  $options    Array of options
     *
     * @access public
     * @return boolean
     */
    public function batchInsert($collection = '', $insert = array(), $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to insert into');
        }

        if (count($insert) === 0 || ! is_array($insert)) {
            throw new Exception('Nothing to insert into Mongo collection or insert is not an array');
        }

        $options = array_merge(
            array(
                $this->_querySafety => true
            ),
            $options
        );

        try {
            $uk = $this->_dbhandle
                ->{$collection}
                ->find()
                ->limit(1)
                ->sort(array('_id' => -1));
            if($uk->hasNext())
            {
                $data = $uk->getNext();
                $insert['uk'] = intval($data['uk'])+1;
            }
            else
            {
                $insert['uk'] = 1;
            }
            return $this->_dbhandle
                ->{$collection}
                ->batchInsert($insert, $options);
        }
        catch (\MongoCursorException $Exception) {
            throw new Exception('Insert of data into Mongo failed: ' . $Exception->getMessage());
        }
    }

    /**
     * Update a document
     *
     * @param string $collection Name of the collection
     * @param array  $options    Array of update options
     *
     * @access public
     * @return boolean
     */
    public function update($collection = '', $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to
             update');
        }

        if(!$options || count($options) == 0 || !is_array($options)) {
            throw new Exception('Nothing to update in Mongo collection or update is not an array');
        }

        try {
            /*$options = array_merge(array($this->_querySafety => true, 'multiple' => false), $options);*/
            $result = $this->_dbhandle->{$collection}->update($this->wheres, array('$set' => $options));
            $this->_clear($collection, 'update');

            if ($result['updatedExisting'] > 0) {
                return $result['updatedExisting'];
            }
            return $result;
        }
        catch (\MongoCursorException $Exception) {
            throw new Exception('Update of data into Mongo failed: ' . $Exception->getMessage());
        }
    }

    /**
     * Update all documents.
     *
     * Updates a document
     *
     * @param string $collection Name of the collection
     * @param array  $options    Array of update options
     *
     * @access public
     * @return boolean
     */
    public function updateAll($collection = '', $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to update');
        }

        if(!$options || count($options) == 0 || !is_array($options)) {
            throw new Exception('Nothing to update in Mongo collection or update is not an array');
        }

        try {
            $options = array_merge(array($this->_querySafety => true, 'multiple' => true), $options);
            $result = $this->_dbhandle->{$collection}->update($this->wheres, $options);
            $this->_clear($collection, 'update_all');

            if ($result['updatedExisting'] > 0) {
                return $result['updatedExisting'];
            }
            return $result;
        }
        catch (\MongoCursorException $Exception) {
            throw new Exception('Update of data into Mongo failed: ' . $Exception->getMessage());
        }
    }

    /**
     * Inc.
     *
     * Increments the value of a field
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name) to be incremented
     * @param int $value Value that the field(s) should be incremented
     *  by
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function inc($fields = array(), $value = 0)
    {
        $this->_updateInit('$inc');

        if (is_string($fields)) {
            $this->updates['$inc'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->updates['$inc'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Dec.
     *
     * Decrements the value of a field
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name) to be decremented
     * @param int $value Value that the field(s) should be decremented
     *  by
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function dec($fields = array(), $value = 0)
    {
        $this->_updateInit('$inc');

        if (is_string($fields)) {
            $value = 0 - $value;
            $this->updates['$inc'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $value = 0 - $value;
                $this->updates['$inc'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Set.
     *
     * Sets a field to a value
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name)
     * @param mixed $value Value that the field(s) should be set to
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function set($fields, $value = null)
    {
        $this->_updateInit('$set');

        if (is_string($fields)) {
            $this->updates['$set'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->updates['$set'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Unset.
     *
     * Unsets a field (or fields)
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name) to be unset
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function unsetField($fields)
    {
        $this->_updateInit('$unset');

        if (is_string($fields)) {
            $this->updates['$unset'][$fields] = 1;
        } elseif (is_array($fields)) {
            foreach ($fields as $field) {
                $this->updates['$unset'][$field] = 1;
            }
        }

        return $this;
    }

    /**
     * Add to set.
     *
     * Adds value to the array only if its not in the array already
     *
     * @param string       $field  Name of the field
     * @param string|array $values Value of the field(s)
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function addToSet($field, $values)
    {
        $this->_updateInit('$addToSet');

        if (is_string($values)) {
            $this->updates['$addToSet'][$field] = $values;
        } elseif (is_array($values)) {
            $this->updates['$addToSet'][$field] = array('$each' => $values);
        }

        return $this;
    }

    /**
     * Push.
     *
     * Pushes values into a field (field must be an array)
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name)
     * @param mixed $value Value of the field(s) to be pushed into an
     *  array or object
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function push($fields, $value = array())
    {
        $this->_updateInit('$push');

        if (is_string($fields)) {
            $this->updates['$push'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->updates['$push'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Pop.
     *
     * Pops the last value from a field (field must be an array)
     *
     * @param string $field Name of the field to be popped
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function pop($field)
    {
        $this->_updateInit('$pop');

        if (is_string($field)) {
            $this->updates['$pop'][$field] = -1;
        } elseif (is_array($field)) {
            foreach ($field as $pop_field) {
                $this->updates['$pop'][$pop_field] = -1;
            }
        }

        return $this;
    }

    /**
     * Pull.
     *
     * Removes by an array by the value of a field
     *
     * @param string $field Name of the field
     * @param array  $value Array of identifiers to remove $field
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function pull($field = '', $value = array())
    {
        $this->_updateInit('$pull');

        $this->updates['$pull'] = array($field => $value);

        return $this;
    }

    /**
     * Rename field.
     *
     * Renames a field
     *
     * @param string $old_name Name of the field to be renamed
     * @param string $new_name New name for $old_name
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function renameField($oldName, $newName)
    {
        $this->_updateInit('$rename');
        $this->updates['$rename'][$oldName] = $newName;

        return $this;
    }

    /**
     * Delete.
     *
     * delete document from the passed collection based upon certain criteria
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function delete($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to delete from');
        }

        try {
            $this->_dbhandle->{$collection}->remove($this->wheres,
                array($this->_querySafety => true, 'justOne' => true));
            $this->_clear($collection, 'delete');

            return true;
        }
            //@start
        catch (\MongoCursorException $Exception) {
            throw new Exception('Delete of data into Mongo failed: ' . $Exception->getMessage());
            //@end
        }
    }

    /**
     * Delete all.
     *
     * Delete all documents from the passed collection based upon certain
     *  criteria
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function deleteAll($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to delete from');
        }

        try {
            $this->_dbhandle->{$collection}->remove($this->wheres, array($this->_querySafety => true, 'justOne' => false));
            $this->_clear($collection, 'delete_all');

            return true;
        }
            //@start
        catch (\MongoCursorException $Exception) {
            throw new Exception('Delete of data into Mongo failed: ' . $Exception->getMessage());
            //@end
        }
    }

    /**
     * Command.
     *
     * Runs a Mongo command (such as GeoNear). See the Mongo documentation
     *  for more usage scenarios - http://dochub.mongodb.org/core/commands
     *
     * @param array $query The command query
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function command($query = array())
    {
        try {
            $execute = $this->_dbhandle->command($query);

            return $execute;
        }
            //@start
        catch (\MongoCursorException $Exception) {
            throw new Exception('Mongo command failed to execute: ' . $Exception->getMessage());
            //@end
        }
    }

    /**
     * Add indexes.
     *
     * Ensure an index of the keys in a collection with optional parameters.
     *  To set values to descending order, you must pass values of either -1,
     *  false, 'desc', or 'DESC', else they will be set to 1 (ASC).
     *
     * @param string $collection Name of the collection
     * @param array  $fields     Array of fields to be indexed. Key should be
     *  the field name, value should be index type
     * @param array $options Array of options
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function addIndex($collection = '', $fields = array(),
                             $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection specified to add index to');
        }

        if (empty($fields) OR ! is_array($fields)) {
            throw new Exception('Index could not be added to Mongo collection because no keys were specified');
        }

        foreach ($fields as $field => $value) {
            if($value === -1 OR $value === false OR
                strtolower($value) === 'desc') {
                $keys[$field] = -1;
            } elseif($value === 1 OR $value === true OR
                strtolower($value) === 'asc') {
                $keys[$field] = 1;
            } else {
                $keys[$field] = $value;
            }
        }

        try {
            $this->_dbhandle->{$collection}->ensureIndex($keys, $options);
            $this->_clear($collection, 'add_index');
            return $this;
        }
            //@start
        catch (\Exception $e) {
            throw new Exception('An error occurred when trying to add an index to Mongo Collection: ' . $e->getMessage());
            //@end
        }
    }

    /**
     * Remove indexes.
     *
     * Remove an index of the keys in a collection.
     *
     * @param string $collection Name of the collection
     * @param array  $keys       Array of index keys to be removed. Array key
     *  should be the field name, the value should be -1
     *
     * @access public
     * @return \Flywheel\Mongo\MongoDB
     */
    public function removeIndex($collection = '', $keys = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection specified to remove index from');
        }

        if (empty($keys) OR ! is_array($keys)) {
            throw new Exception('Index could not be removed from Mongo Collection because no keys were specified');
        }

        if ($this->_dbhandle->{$collection}->deleteIndex($keys)) {
            $this->_clear($collection, 'remove_index');

            return $this;
        } else {
            //@start
            throw new Exception('An error occurred when trying to remove an index from Mongo Collection');
            //@end
        }

        return $this->_dbhandle->{$collection}->deleteIndex($keys);
    }

    /**
     * Remove all indexes
     *
     * Remove all indexes from a collection.
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return array|object
     */
    public function removeAllIndexes($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection specified to remove all indexes from');
        }
        $this->_dbhandle->{$collection}->deleteIndexes();
        $this->_clear($collection, 'remove_all_indexes');

        return $this;
    }

    /**
     * List indexes.
     *
     * Lists all indexes in a collection.
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return array|object
     */
    public function listIndexes($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection specified to remove all indexes from');
        }

        return $this->_dbhandle->{$collection}->getIndexInfo();
    }

    /**
     * Mongo Date.
     *
     * Create new MongoDate object from current time or pass timestamp to create
     *  mongodate.
     *
     * @param int|null $timestamp A unix timestamp (or null to return a
     *  MongoDate relative to time()
     *
     * @access public
     * @return array|object
     */
    public static function date($timestamp = null)
    {
        if ($timestamp === null) {
            return new \MongoDate();
        }

        return new \MongoDate($timestamp);
    }

    /**
     * last_query.
     *
     * Return the last query
     *
     * @access public
     * @return array
     */
    public function lastQuery()
    {
        return $this->_queryLog;
    }

    /**
     * Connect to Mongo
     *
     * Establish a connection to Mongo using the connection string generated
     *  in the connection_string() method.
     *
     * @return MongoDB
     * @access private
     */
    private function _connect()
    {
        $options = array();

        if ($this->_persist === true) {
            $options['persist'] = $this->_persistKey;
        }

        if ($this->_replicaSet !== false) {
            //@start
            $options['replicaSet'] = $this->_replicaSet;

        } //@end

        try {
            //@start
            if (phpversion('MongoClient') >= 1.3)
            {
                unset($options['persist']);
                $this->_connection = new \MongoClient($this->_dsn, $options);
                $this->_dbhandle = $this->_connection->{$this->_dbname};
            }

            else
            {
                $this->_connection = new \Mongo($this->_dsn, $options);

                $this->_dbhandle = $this->_connection->{$this->_dbname};
            }
            return $this;
        }
        catch (MongoConnectionException $Exception) {
            throw new Exception('Unable to connect to Mongo: ' . $Exception->getMessage());
        }
    }

    /**
     * Build connectiong string.
     *
     * @access private
     * @return void
     */
    private function _connectionString()
    {
        $this->_dsn = $this->_configData['dsn'];

        if (!$this->_dsn) {
            throw new Exception('The DSN is empty');
        }

        $this->_persist = $this->_configData['persist'];
        $this->_persistKey = trim($this->_configData['persist_key']);
        $this->_replicaSet = $this->_configData['replica_set'];
        $this->_querySafety = trim($this->_configData['query_safety']);

        $parts = parse_url($this->_dsn);

        if ( ! isset($parts['path']) OR str_replace('/', '', $parts['path']) === '') {
            throw new Exception('The database name must be set in the DSN string');
        }

        $this->_dbname = str_replace('/', '', $parts['path']);
        return;
    }

    /**
     * Reset the class variables to default settings.
     *
     * @access private
     * @return void
     */
    private function _clear($collection, $action)
    {
        $this->_queryLog = array(
            'collection'    => $collection,
            'action'        => $action,
            'wheres'        => $this->wheres,
            'updates'       => $this->updates,
            'selects'       => $this->_selects,
            'limit'         => $this->_limit,
            'offset'        => $this->_offset,
            'sorts'         => $this->_sorts
        );

        $this->_selects = array();
        $this->updates  = array();
        $this->wheres   = array();
        $this->_limit   = 999999;
        $this->_offset  = 0;
        $this->_sorts   = array();
    }

    /**
     * Where initializer.
     *
     * Prepares parameters for insertion in $wheres array().
     *
     * @param string $field Field name
     *
     * @access private
     * @return void
     */
    private function _whereInit($field)
    {
        if ( ! isset($this->wheres[$field])) {
            $this->wheres[$field] = array();
        }
    }

    /**
     * Update initializer.
     *
     * Prepares parameters for insertion in $updates array().
     *
     * @param string $field Field name
     *
     * @access private
     * @return void
     */
    private function _updateInit($field = '')
    {
        if ( ! isset($this->updates[$field])) {
            $this->updates[$field] = array();
        }
    }
}