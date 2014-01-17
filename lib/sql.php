<?php

class sql
{
    protected $db     = null;
    protected $table  = null;
    protected $alias  = null;
    protected $fields = array();
    protected $where  = array();
    protected $having = array();
    protected $limit  = null;
    protected $order  = array();
    protected $group  = array();
    protected $join   = array();

    protected $cache  = 0;
    protected $expire = 0;
    // shared result set cache
    protected static $_result_cache = array();

    protected $set = null;
    protected $multiset = array();

    const NOCACHE = 0;
    const CACHE = 1;
    const MEMCACHE = 2;

    // mysql result resource after each query
    protected $rs = null;
    protected $rs_fields = array();
    protected $rs_sql = null;

    protected $error = null;
    protected $error_msg = null;

    // flags
    protected $sql_no_cache = false;

    // for logging
    protected $db_host = null;
    protected $db_vers = null;
    protected $db_user = null;

    public function __construct($table=null, $db=null)
    {
        if (!is_null($table)) $this->from($table);
        $this->db(is_null($db) && function_exists('db') ? db(): $db);
    }

    // quote and escape a value
    public static function quote($val)
    {
        if (is_numeric($val) && preg_match('/^[-]{0,1}[0-9]+$/', $val)) return $val;
        if (is_scalar($val)) return sprintf("'%s'", mysql_real_escape_string($val));
        if (is_array($val))
        {
            $out = array(); foreach ($val as $v) $out[] = self::quote($v);
            return sprintf('(%s)', join(',', $out));
        }
        // expr() returns a stdObject with ->content to idetify something already escaped
        if (is_object($val) && get_class($val) == 'stdClass' && isset($val->content)) return $val->content;
        if (is_callable($val)) return $val();
        return 'null';
    }

    public function table() { return $this->table; }
    public function alias() { return $this->alias; }

    // quote a field or table name if safe to do so
    public static function quote_name($key)
    {
        return preg_match('/^[a-zA-Z]+[a-zA-Z0-9_]*$/', $key) ? sprintf('`%s`', trim($key)): trim($key);
    }

    // quote and escape key/value pairs
    public static function prepare($pairs)
    {
        $out = array();
        if (is_array($pairs)) foreach ($pairs as $key => $val)
        {
            $key = self::quote_name($key);
            $out[$key] = self::quote($val);
        }
        return $out;
    }

    // return a prepared set as an SQL key = val string
    public static function prepare_set($pairs)
    {
        $out = array();
        foreach (self::prepare($pairs) as $key => $val)
            $out[] = sprintf('%s = %s', $key, $val);
        return join(', ', $out);
    }

    // determine the name of a table's primary key
    public static function primary_key($table) {
        $schema = 'database()';
        if (preg_match_all('/^(.+)\.(.+)$/', $table, $matches))
        {
            $schema = "'".$matches[1][0]."'";
            $table  = $matches[2][0];
        }
        return self::query('information_schema.COLUMNS', sql::MEMCACHE)
            ->fields('COLUMN_NAME')
            ->where('TABLE_NAME', $table)->where('COLUMN_KEY', 'PRI')
            ->where("TABLE_SCHEMA = $schema")->fetch_value();
    }

    // grab a table's field types and defaults
    public static function table_fields($table) {
        $schema = 'database()';
        if (preg_match_all('/^(.+)\.(.+)$/', $table, $matches))
        {
            $schema = "'".$matches[1][0]."'";
            $table  = $matches[2][0];
        }
        return self::query('information_schema.COLUMNS', sql::MEMCACHE)
            ->fields('COLUMN_NAME,COLUMN_DEFAULT,IS_NULLABLE,DATA_TYPE,COLUMN_KEY,EXTRA,CHARACTER_MAXIMUM_LENGTH')
            ->where('TABLE_NAME', $table)->where("TABLE_SCHEMA = $schema")->fetch_all();
    }

    // generate an arbitrary sql fragment with ? fields
    public static function parse($pattern)
    {
        $parts = explode('?', $pattern);
        $out = array_shift($parts);
        $i = 1; while (count($parts))
        {
            $arg = func_get_arg($i);
            if ($arg === false) $arg = null;
            $out .= self::quote($arg);
            $out .= array_shift($parts);
            $i++;
        }
        return $out;
    }

    // generate an arbitrary sql fragment with ? fields, as an object for quote()
    public static function expr()
    {
        $out = call_user_func_array('self::parse', func_get_args());
        $obj = new stdClass(); $obj->content = $out;
        return $obj;
    }

    // allow client side caching of result
    public function cache($f=sql::CACHE, $expire=0)
    {
        $this->cache = $f;
        $this->expire = $expire;
        return $this;
    }

    // disable all caching, including query cache
    public function nocache()
    {
        $this->cache(sql::NOCACHE, 0);
        $this->sql_no_cache = true;
        return $this;
    }

    public function db($db)
    {
        $this->db = $db;
    }

    // add a field to be retrieved
    public function field($field)
    {
        $this->fields[] = self::quote_name($field);
        return $this;
    }
    // set an array of field to be retrieved
    public function fields($fields)
    {
        if (!is_array($fields))
            $fields = preg_split('/\s*,\s*/', $fields);

        $this->fields = array();
        foreach ($fields as $f) $this->field($f);

        return $this;
    }
    // set primary table name
    public function from($table, $alias=null)
    {
        if (strpos($table, ' ') && is_null($alias))
            list ($table, $alias) = preg_split('/\s+/', trim($table));

        $this->table = $table;
        $this->alias = $alias;

        if (empty($this->fields))
            $this->field(($alias ? $alias: $table).'.*');

        return $this;
    }
    // create an SQL clause for WHERE or JOIN conditions
    public static function clause($name)
    {
        $name = trim($name);
        $argc = func_num_args();
        $value = $argc > 1 ? func_get_arg(1): null;
        // ? mark expression with variable number of args
        if (strpos($name, '?') !== false)
            return call_user_func_array('self::parse', func_get_args());
        else
        // single field name without operator, so default to =
        if (strpos($name, ' ') === false && $argc > 1)
            return sprintf('%s = %s', self::quote_name($name), self::quote($value));
        else
        // single field with trailing operator and separate value
        if (strpos($name, ' ') !== false && $argc > 1)
        {
            list ($field, $op) = preg_split('/\s+/', $name);
            return sprintf('%s %s %s', self::quote_name($field), $op, self::quote($value));
        }
        else
        // only one arg and may be raw SQL, don't touch it
            return $name;
    }
    // add a where clause, defaulting to = when >1 argument
    public function where($sql)
    {
        $this->where[] = call_user_func_array('self::clause', func_get_args());
        return $this;
    }
    public function where_eq($name, $val)
    {
        return $this->where($name.' =', $val);
    }
    public function where_ne($name, $val)
    {
        return $this->where($name.' <>', $val);
    }
    public function where_lt($name, $val)
    {
        return $this->where($name.' <', $val);
    }
    public function where_let($name, $val)
    {
        return $this->where($name.' <=', $val);
    }
    public function where_gt($name, $val)
    {
        return $this->where($name.' >', $val);
    }
    public function where_gte($name, $val)
    {
        return $this->where($name.' >=', $val);
    }
    public function where_like($name, $val, $mode=true)
    {
        $this->where[] = sprintf('%s %s %s', $name, $mode ? 'like': 'not like', self::quote($val));
        return $this;
    }
    public function where_not_like($name, $val)
    {
        return $this->where_like($name, $val, false);
    }
    public function where_regexp($name, $val, $mode=true)
    {
        $this->where[] = sprintf('%s %s %s', $name, $mode ? 'regexp': 'not regexp', self::quote($val));
        return $this;
    }
    public function where_not_regexp($name, $val)
    {
        return $this->where_regexp($name, $val, false);
    }
    public function having($sql)
    {
        $this->having[] = call_user_func_array('self::clause', func_get_args());
        return $this;
    }
    // add a field IN(...values...)
    public function where_in($name, $vals)
    {
        if (is_scalar($vals)) $vals = preg_split('/\s*,\s*/', $vals);
        $this->where[] = sprintf('%s in (%s)', self::quote_name($name), join(',', self::prepare($vals)));
        return $this;
    }
    // add a field IN(...values...)
    public function where_not_in($name, $vals)
    {
        if (is_scalar($vals)) $vals = preg_split('/\s*,\s*/', $vals);
        $this->where[] = sprintf('%s not in (%s)', self::quote_name($name), join(',', self::prepare($vals)));
        return $this;
    }
    public function where_in_if($name, $vals)
    {
        if (!empty($vals)) $this->where_in($name, $vals);
        return $this;
    }
    public function where_not_in_if($name, $vals)
    {
        if (!empty($vals)) $this->where_not_in($name, $vals);
        return $this;
    }
    // add a field IS NULL
    public function where_null($name, $state=true)
    {
        $this->where[] = sprintf('%s %s null', self::quote_name($name), $state ? 'is': 'is not');
        return $this;
    }
    // add a field IS NULL
    public function where_not_null($name)
    {
        return $this->where_null($name, false);
    }
    // add a field between x AND y
    public function where_between($name, $lower, $upper)
    {
        $this->where(self::quote_name($name) .' between ? and ?', $lower, $upper);
        return $this;
    }
    // bulk add where clauses to be ANDed togetehr
    public function where_and($pairs)
    {
        foreach ($pairs as $key => $val)
        {
            if (is_array($val)) $this->where_in($key, $val);
            else $this->where($key, $val);
        }
        return $this;
    }
    // bulk add where clauses to be ORed together
    public function where_or($pairs)
    {
        $tmp = $this->where;
        $this->where = array();
        foreach ($pairs as $key => $val)
        {
            if (is_array($val)) $this->where_in($key, $val);
            else $this->where($key, $val);
        }
        $tmp[] = sprintf('(%s)', join(' or ', $this->where));
        $this->where = $tmp;
        return $this;
    }
    // limit the returned rows
    public function limit($offset, $limit=null)
    {
        if (is_null($limit)) $this->limit = $offset;
        else $this->limit = $offset.', '.$limit;
        return $this;
    }
    // order by one or more fields
    public function order($name, $dir='asc')
    {
        $name = trim($name);
        // single CSV string of fields (and possibly directions)
        if (strpos($name, ','))
            foreach (preg_split('/\s*,\s*/', $name) as $field) $this->order($field);
        else
        // single field string with name and direction
        if (preg_match('/\s+(asc|desc)$/i', $name))
        {
            list ($name, $dir) = preg_split('/\s+/', $name);
            $this->order[] = self::quote_name($name).' '.$dir;
        }
        else
        // proper field and separate direction
            $this->order[] = self::quote_name($name).' '.$dir;
        return $this;
    }
    // group by one or more fields
    public function group($vals)
    {
        if (is_scalar($vals)) $vals = preg_split('/\s*,\s*/', $vals);
        foreach ($vals as $val) $this->group[] = self::quote_name($val);
        $this->group = array_unique($this->group);
        return $this;
    }
    // join a table
    public function join($table, $on=null)
    {
        $alias = null; if (strpos($table, ' '))
            list ($table, $alias) = preg_split('/\s+/', trim($table));

        $args = func_get_args(); array_shift($args);
        $clause = call_user_func_array('self::clause', $args);
        $this->join[] = sprintf('join '.self::quote_name($table) .($alias ? ' '.$alias:'') . ' on ' . $clause);
        return $this;
    }
    // left outer join a table
    public function left_join($table, $on=null)
    {
        $alias = null; if (strpos($table, ' '))
            list ($table, $alias) = preg_split('/\s+/', trim($table));

        $args = func_get_args(); array_shift($args);
        $clause = call_user_func_array('self::clause', $args);
        $this->join[] = sprintf('left join '.self::quote_name($table) .($alias ? ' '.$alias:'') . ' on ' . $clause);
        return $this;
    }
    // right outer join a table
    public function right_join($table, $on=null)
    {
        $alias = null; if (strpos($table, ' '))
            list ($table, $alias) = preg_split('/\s+/', trim($table));

        $args = func_get_args(); array_shift($args);
        $clause = call_user_func_array('self::clause', $args);
        $this->join[] = sprintf('right join '.self::quote_name($table) .($alias ? ' '.$alias:'') . ' on ' . $clause);
        return $this;
    }    // set key/val pair to be written in undate or single row insert
    public function set($pairs, $val=null)
    {
        if (is_scalar($pairs))
        {
            if (!is_array($this->set))
                $this->set = array();
            $this->set[$pairs] = $val;
        }
        else
        foreach ($pairs as $key => $val)
            $this->set($key, $val);
        return $this;
    }
    // retrieve from set
    public function get($key=null, $def=null)
    {
        if (is_null($key)) return $this->set;
        return isset($this->set[$key]) ? $this->set[$key]: $def;
    }
    // set multiple rows to be written in a bulk insert
    public function rows($rows)
    {
        $this->multiset = $rows;
        return $this;
    }
    // add another set to be written in a bulk insert
    public function add_row($row)
    {
        $this->multiset[] = $row;
        return $this;
    }
    // methods for building SQL fragments
    private function get_from() { return 'from '.self::quote_name($this->table) .($this->alias ? ' '.$this->alias:''); }
    private function get_join() { return $this->join ? join(' ', $this->join): ''; }
    private function get_where() { return $this->where ? 'where '.join(' and ', $this->where) : ''; }
    private function get_having() { return $this->having ? 'having '.join(' and ', $this->having) : ''; }
    private function get_limit() { return $this->limit ? 'limit '.$this->limit: ''; }
    private function get_order() { return $this->order ? 'order by '.join(', ', $this->order): ''; }
    private function get_group() { return $this->group ? 'group by '.join(', ', $this->group): ''; }

    // generate a SELECT query
    public function get_select()
    {
        $flags  = $this->sql_no_cache ? 'SQL_NO_CACHE': '';
        $fields = $this->fields ? join(', ', $this->fields): '*';
        $from   = $this->get_from();
        $join   = $this->get_join();
        $where  = $this->get_where();
        $having = $this->get_having();
        $order  = $this->get_order();
        $group  = $this->get_group();
        $limit  = $this->get_limit();
        // MySQL runs a needless filesort when grouping without an order clause. disable it.
        if ($group && !$order) $order = 'order by null';
        return "select $flags $fields $from $join $where $group $having $order $limit";
    }

    // generate a DELETE query
    public function get_delete()
    {
        $del = $this->alias;
        $from  = $this->get_from();
        $join  = $this->get_join();
        $where = $this->get_where();
        $order = $this->get_order();
        $group = $this->get_group();
        $limit = $this->get_limit();
        return "delete $del $from $join $where $group $order $limit";
    }

    // generate an INSERT query
    public function get_insert()
    {
        $pairs = !empty($this->multiset) ? $this->multiset: array($this->set);

        $vals = array();
        $keys = null;
        foreach ($pairs as $row)
        {
            $keys = array_keys($row);
            $vals[] = join(', ', self::prepare($row));
        }
        $keys = join(', ', $keys);
        $vals = join('), (', $vals);
        $table = self::quote_name($this->table);
        return "insert into $table ($keys) values ($vals)";
    }

    // generate a REPLACE query
    public function get_replace()
    {
        return preg_replace('/^insert/', 'replace', $this->get_insert());
    }

    // generate an UPDATE query
    public function get_update()
    {
        $table = self::quote_name($this->table);
        $alias = $this->alias ? $this->alias: '';
        $where = $this->get_where();
        $limit = $this->get_limit();
        $set = self::prepare_set($this->set);
        return "update $table $alias set $set $where $limit";
    }

    // execute the current state as a SELECT
    public function execute($sql=null)
    {
        if (is_null($sql)) $sql = $this->get_select();

        $this->error = null;
        $this->error_msg = null;

        $host = preg_replace('/^([^\s]+).*$/', '\1', mysql_get_host_info($this->db));
        error_log('sql '.$host.': '.rtrim($sql)."\n");

        $this->rs = mysql_query($sql, $this->db);
        $this->rs_sql = $sql;

        if (!$this->rs)
        {
            $this->error = mysql_errno();
            $this->error_msg = mysql_error();
            error_log('sql error: '.$this->error.' '.$this->error_msg."\n");
        }

        $this->rs_fields = array();

        if (is_resource($this->rs))
        {
            $i = 0;
            $l = mysql_num_fields($this->rs);
            while ($i < $l)
            {
                $this->rs_fields[$i] = mysql_fetch_field($this->rs, $i);
                $i++;
            }
        }

        return $this;
    }

    public function error()
    {
        return ($this->error) ? array($this->error, $this->error_msg) : null;
    }

    public function recache($rows)
    {
        $sql = $this->rs_sql ? $this->rs_sql: $this->get_select();
        $md5 = md5($sql);

        if ($this->cache === sql::MEMCACHE)
            cache::set($md5, $rows, $this->expire);

        if ($this->cache)
            self::$_result_cache[$md5] = gzcompress(serialize($rows));
    }

    // retrieve all available rows
    public function fetch_all($index=null)
    {
        $host = preg_replace('/^([^\s]+).*$/', '\1', mysql_get_host_info($this->db));

        $sql = $this->rs_sql ? $this->rs_sql: $this->get_select();
        $md5 = md5($sql);

        if (!$this->rs)
        {
            if ($this->cache && isset(self::$_result_cache[$md5]))
            {
                error_log("sql $host (result cache): ".rtrim($sql)."\n");
                return unserialize(gzuncompress(self::$_result_cache[$md5]));
            }

            if (($data_obj = cache::get($md5, 'array')) && is_array($data_obj))
            {
                error_log("sql $host (memcached): ".rtrim($sql)."\n");
                return $data_obj;
            }

            $this->execute($sql);
        }

        $rows = array();
        while (($row = mysql_fetch_array($this->rs, MYSQL_NUM)) && $row)
        {
            $j = count($rows);
            $res = array();
            $pri = array();
            foreach ($row as $i => $value)
            {
                $field = $this->rs_fields[$i];
                $res[$field->name] = $value;

                if ($field->type == 'int')
                    $res[$field->name] = intval($value);

                if ($field->primary_key)
                    $pri[] = $value;
            }
            if ($pri) $j = join(':', $pri);
            $rows[$index ? $res[$index]: $j] = $res;
        }

        $this->recache($rows);
        return $rows;
    }

    // retrieve all rows as numerically indexed array
    public function fetch_all_numeric()
    {
        $rows = $this->fetch_all();
        foreach ($rows as &$row) $row = array_values($row);
        return $rows;
    }

    // retrieve a single field from all available rows
    public function fetch_field($name=null)
    {
        $out = array();
        foreach ($this->fetch_all() as $row)
            $out[] = is_null($name) ? array_shift($row): $row[$name];
        return $out;
    }

    // retrieve two fields from all available rows, as a key/val array (key must be UNIQUE or PRIMARY)
    public function fetch_pair($key, $val)
    {
        $out = array();
        foreach ($this->fetch_all() as $row)
            $out[$row[$key]] = $row[$val];
        return $out;
    }

    // retrieve a single row
    public function fetch_one()
    {
        if (!$this->limit) $this->limit(1);
        $rows = $this->fetch_all();
        return $rows ? array_shift($rows): null;
    }

    // retrieve a single row numerically indexed
    public function fetch_one_numeric()
    {
        $row = $this->fetch_one();
        return $row ? array_values($row): $row;
    }

    // retrieve a single value
    public function fetch_value($name=null)
    {
        $row = $this->fetch_one();
        if ($row) {
            if ($name && isset($row[$name])) return $row[$name];
            return array_shift($row);
        }
        return null;
    }

    // wrappers to execute the current state as different queries
    public function select($fields=null) { if ($fields) $this->fields($fields); return $this->execute($this->get_select()); }
    public function delete() { return $this->execute($this->get_delete()); }
    public function insert($set=null, $val=null) { if ($set) $this->set($set, $val); return $this->execute($this->get_insert()); }
    public function update($set=null, $val=null) { if ($set) $this->set($set, $val); return $this->execute($this->get_update()); }
    public function replace($set=null, $val=null) { if ($set) $this->set($set, $val); return $this->execute($this->get_replace()); }
    public function insert_id($set=null, $val=null) { $this->insert($set, $val); return mysql_insert_id(); }

    public function truncate() { return $this->execute('truncate table '.self::quote_name($this->table)); }

    // initializer
    public static function query($table=null, $cache=sql::NOCACHE, $db=null)
    {
        $s = new static($table);
        if ($cache) $s->cache($cache);
        if (!is_null($db)) $s->db($db);
        return $s;
    }

    // initializer
    public static function command($command, $db=null)
    {
        $s = new static();
        if ($db) $s->db($db);
        $s->execute($command);
        return $s;
    }
}

/**
 * Handle a single databse record.
 */
class sqlrecord
{
    public $ok = true;

    protected static $_schema_cache = array();

    protected $_autoinc  = false;
    protected $_table    = 'unknown';
    protected $_schema   = array();
    protected $_snapshot = array();
    protected $_missing  = false;
    protected $_dberror  = null;

    /**
     * @param string table name
     * @param string|uint|array primary key value or entire record
     */
    public function __construct($table='unknown', $pk=0)
    {
        $this->_table = $table;

        // load Table's schema from INFORMATION_SCHEMA, or memcached
        $this->_schema = array();
        $data = expect(static::$_schema_cache, $this->_table, 'array');

        if (!$data)
        {
            $data = sql::table_fields($this->_table);
            static::$_schema_cache[$this->_table] = $data;
        }

        foreach ($data as $row)
        {
            list ($name, $default, $nullable, $type, $key, $extra, $char_len) = array_values($row);

            if ($key == 'PRI')
                $this->_autoinc = (strstr($extra, 'auto_increment')) ?1:0;

            if (is_null($default))
                $value = NULL;
            else
            if (preg_match('/int/', $type) && !preg_match('/bigint/', $type))
                $value = intval($default);
            else
            if (preg_match('/decimal/', $type))
                $value = floatval($default);
            else
            if (preg_match('/char/', $type) && preg_match('/^[{[]/', $default))
                $value = json_decode($default, true);
            else
                $value = strval($default);

            $this->_schema[$name] = array($value, $type, $key, $extra, $char_len);
        }

        $this->inject();

        if (is_array($pk))
        {
            $this->inject($pk);
        }
        else
        if ($pk)
        {
            $this->id = $pk;
            $this->ok = $this->load();
        }

        if ($this->ok)
            $this->_snapshot = $this->export();
    }
    /**
     * Check database error
     */
    protected function check($sql)
    {
        $err = $sql->error();
        if ($err)
        {
            $this->ok = false;
            $this->_dberror = $err[1];
        }
        return $this->ok;
    }
    /**
     * Load record from database, or external cache.
     * @return success
     */
    protected function load()
    {
        $sql = sql::query($this->_table)->where('id', $this->id);
        $row = $sql->fetch_one();

        $this->check($sql);

        if (!$row || !$this->ok)
        {
            $this->_missing = true;
            $this->ok = false;
            return false;
        }
        $this->inject($row);
        return true;
    }
    /**
     * Initialize record from external source, casting types as necessary.
     * @param array field/value pairs
     * @param bool false: reset non-supplied fields to their defaults
     */
    public function inject($data=array(), $overlay=false)
    {
        foreach ($this->_schema as $field => $row)
        {
            list ($default, $type) = $row;

            if ($overlay && !array_key_exists($field, $data)) continue;

            $new_value = array_key_exists($field, $data) ? $data[$field]: $default;
            $value = $default;

            if (is_null($new_value))
                $value = NULL;
            else
            if (preg_match('/int/', $type) && !preg_match('/bigint/', $type))
                $value = intval($new_value);
            else
            if (preg_match('/decimal/', $type))
                $value = floatval($new_value);
            else
            if (preg_match('/char/', $type) && is_array($default))
            {
                if (is_string($new_value))
                    $new_value = json_decode($new_value, true);
                $value = $new_value;
            }
            else
            if (is_scalar($new_value))
                $value = strval($new_value);
            $this->$field = $value;
        }
    }
    /**
     * Dump record field/values as array
     * @param bool if it is for database save right now (affects auto timestamps)
     */
    public function export()
    {
        $data = array();

        foreach ($this->_schema as $field => $row)
        {
            list ($default, $type) = $row;

            if (is_null($this->$field))
                $data[$field] = NULL;
            else
            if (preg_match('/int/', $type) && !preg_match('/bigint/', $type))
                $data[$field] = intval($this->$field);
            else
            if (preg_match('/decimal/', $type))
                $data[$field] = floatval($this->$field);
            else
            if (preg_match('/char/', $type) && is_array($default))
                $data[$field] = $this->$field;
            else
            if (preg_match('/timestamp/', $type) && $this->$field == 'CURRENT_TIMESTAMP')
                continue;
            else
                $data[$field] = strval($this->$field);
        }
        return $data;
    }
    /**
     * Update record field/values from external source, such as Form submission.
     * @param array field/value pairs
     */
    public function detect($data)
    {
        $this->inject($data, true);
    }
    /**
     * Update record field/values from external source, such as Form submission. only import
     * new values. return any clashes as $diff
     * @param array field/value pairs
     * @return array
     */
    public function merge($data)
    {
        $ndata = array(); $diff = array();
        foreach (array_keys($this->_schema) as $field)
        {
            if (array_key_exists($field, $data))
            {
                if (!$this->$field) $ndata[$field] = $data[$field];
                else if ($this->$field != $data[$field]) $diff[$field] = $data[$field];
            }
        }
        $this->inject($ndata, true);
        return $diff;
    }
    /**
     * Save record to database
     */
    public function save()
    {
        $this->ok = ($this->_missing || !$this->id) ? $this->create(): $this->update();
        return $this->ok;
    }
    /**
     * Update record in database.
     */
    public function update()
    {
        if (array_key_exists('updated', $this->_schema) && (!$this->updated || $this->updated == $this->_snapshot['updated']))
            $this->updated = preg_match('/int/', $this->_schema['updated'][1]) ? time(): date('Y-m-d H:i:s');

        $data = array();
        foreach ($this->_schema as $field => $row)
        {
            list ($default, $type) = $row;

            $pool = $this->_snapshot[$field];

            if (is_null($this->$field) && !is_null($pool))
                $data[$field] = NULL;
            else
            if (preg_match('/int/', $type) && !preg_match('/bigint/', $type) && intval($this->$field) != intval($pool))
                $data[$field] = intval($this->$field);
            else
            if (preg_match('/decimal/', $type) && floatval($this->$field) != floatval($pool))
                $data[$field] = floatval($this->$field);
            else
            if (preg_match('/char/', $type) && is_array($default) && serialize($this->$field) != serialize($pool))
                $data[$field] = json_encode($this->$field);
            else
            if (preg_match('/timestamp/', $type) && strtotime($this->$field) != strtotime($pool))
                $data[$field] = date('Y-m-d H:i:s', strtotime($this->$field));
            else
            if ((is_scalar($this->$field) || is_null($this->$field)) && (is_scalar($pool) || is_null($pool)) && strval($this->$field) != strval($pool))
                $data[$field] = strval($this->$field);
        }
        if (count($data))
        {
            $this->check(
                sql::query($this->_table)->set($data)->where('id', $this->id)->update()
            );
        }
        return $this->ok;
    }
    /**
     * Insert new record to database.
     */
    public function create()
    {
        if (array_key_exists('created', $this->_schema) && !$this->created)
            $this->created = preg_match('/int/', $this->_schema['created'][1]) ? time(): date('Y-m-d H:i:s');

        $data = array(); $edata = $this->export();
        // export may have been overidden, so check returned data
        foreach ($this->_schema as $field => $row)
        {
            $val = $edata[$field];
            $data[$field] = is_array($val) ? json_encode($val): $val;
        }
        if ($this->_autoinc)
            unset($data['id']);

        $sql = sql::query($this->_table)->set($data);
        $id = $this->_autoinc ? $sql->insert_id(): $sql->insert();

        if (!$this->check($sql)) return false;
        if ($this->_autoinc) $this->id = $id;

        // reload may be needed for field values generated by the database
        foreach ($this->_schema as $field => $row)
        {
            list ($default, $type) = $row;
            if ($type == 'timestamp' && $default == 'CURRENT_TIMESTAMP')
            {
                $this->load();
                break;
            }
        }
        return true;
    }
    /**
     * Delete record from database
     */
    public function delete()
    {
        return $this->check(
            sql::query($this->_table)->where('id', $this->id)->delete()
        );
    }
    /**
     * Return last db write error. Only valid when ->ok = false;
     */
    public function error()
    {
        return $this->ok ? null: $this->_dberror;
    }
}
