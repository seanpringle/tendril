<?php

// An unashamedly MySQL-centric class.

function sql($table=null)
{
    return SQL::query($table);
}

class SQL implements Iterator
{
    protected static $db = null;

    protected $table  = null;
    protected $alias  = null;
    protected $fields = array();
    protected $where  = array();
    protected $having = array();
    protected $limit  = null;
    protected $order  = array();
    protected $group  = array();
    protected $join   = array();

    protected $for_update = false;
    protected $share_mode = false;

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

    // calc found
    protected $sql_calc_found = false;
    protected $found_rows = 0;

    // Iterator
    protected $it_pos;
    protected $it_keys;
    protected $it_rows;

    protected $debug = false;

    public function __construct($table=null, $db=null)
    {
        if (!is_null($table))
            $this->from($table);

        if (!is_null($db))
            self::$db = $db;

        if (is_null(self::$db))
            static::connect();

        $this->debug = @$_ENV['debug'];
    }

    public function rewind()
    {
        if (!$this->rs)
        {
            $data = $this->fetch_all();
            $this->it_keys = array_keys($data);
            $this->it_rows = array_values($data);
        }
        $this->it_pos = 0;
    }

    public function current()
    {
        return dict($this->it_rows[$this->it_pos]);
    }

    public function key()
    {
        return $this->it_keys[$this->it_pos];
    }

    public function next()
    {
        $this->it_pos++;
    }

    public function valid()
    {
        return isset($this->it_keys[$this->it_pos]);
    }

    public static function connect($con=null)
    {
        $env = dict(is_array($con) ? $con: $_ENV);

        if ($env->debug)
            error_log(sprintf('database connect: %s@%s:%d', $env->db_user, $env->db_host, $env->db_port));

        self::$db = mysqli_connect(
            $env->get('db_host', 'localhost'),
            $env->get('db_user', $env->USER),
            $env->get('db_pass', ''),
            $env->get('db_name', 'test'),
            $env->get('db_port', 3306),
            $env->get('db_sock', null)
        );

        if (self::$db === false)
            throw new Exception(mysqli_connect_error(), mysqli_connect_errno());
    }

    private static function quote_number($num)
    {
        return "$num";
    }

    private static function quote_string($str)
    {
        if (preg_match('/^[a-zA-Z0-9@!#$%()+=*&^_\-. ]*$/', $str))
            return "'$str'";

        $res = "cast(X'";
        if (strlen($str))
            foreach (str_split($str) as $c)
                $res .= sprintf('%02x', ord($c));
        return $res."' as char)";
    }

    // quote and escape a value
    public static function quote($val)
    {
        if (is_null($val))
            return 'null';

        if (is_int($val) || is_double($val))
            return self::quote_number($val);

        if (is_scalar($val))
            return self::quote_string($val);

        if (is_array($val))
        {
            $out = array(); foreach ($val as $v) $out[] = self::quote($v);
            return sprintf('(%s)', join(',', $out));
        }

        if (is_object($val))
            return strval($val);

        if (is_callable($val))
            return $val();

        return 'null';
    }

    public function table() { return $this->table; }

    public function alias() { return $this->alias; }

    // quote a field or table name or sub-query if safe to do so
    public static function quote_name($key)
    {
        if (is_object($key))
            return '('.strval($key).')';

        if (preg_match('/^[a-zA-Z]+[a-zA-Z0-9_]*$/', $key))
            return sprintf('`%s`', trim($key));

        return trim($key);
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
        $obj = text($out);
        return $obj;
    }

    // allow client side caching of result
    public function cache($f=SQL::CACHE, $expire=0)
    {
        $this->cache = $f;
        $this->expire = $expire;
        return $this;
    }

    // disable all caching, including query cache
    public function no_cache()
    {
        $this->cache(SQL::NOCACHE, 0);
        $this->sql_no_cache = true;
        return $this;
    }

    // add a field to be retrieved
    public function field($field)
    {
        $this->fields[] = self::quote_name($field);
        return $this;
    }

    public function no_fields()
    {
        $this->fields = array();
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

    // sub-query field
    public function field_select($name, $search)
    {
        $this->fields[] = sprintf('(%s) as %s',
            $search->limit(1)->get_select(),
            self::quote_name($name)
        );
        return $this;
    }

    public function count()
    {
        return $this->fields('count(*)');
    }

    // set primary table name
    public function from($table, $alias=null)
    {
        if (is_string($table) && strpos($table, ' ') && is_null($alias))
            list ($table, $alias) = preg_split('/\s+/', trim($table));

        if (is_object($table) && is_null($alias))
            $alias = 't'.uniqid();

        $this->table = $table;
        $this->alias = $alias;

        return $this;
    }

    // create an SQL clause for WHERE or JOIN conditions
    public static function clause($name)
    {
        $name = trim($name);
        $argc = func_num_args();
        $value = $argc > 1 ? func_get_arg(1): null;
        // ? mark expression with variable number of args
        if (is_scalar($name) && strpos($name, '?') !== false)
            return call_user_func_array('self::parse', func_get_args());
        else
        // single field name without operator, so default to =
        if (is_scalar($name) && strpos($name, ' ') === false && $argc > 1)
            return sprintf('%s = %s', self::quote_name($name), self::quote($value));
        else
        // single field with trailing operator and separate value
        if (is_scalar($name) && strpos($name, ' ') !== false && $argc > 1)
        {
            list ($field, $op) = preg_split('/\s+/', $name);
            return sprintf('%s %s %s', self::quote_name($field), $op, self::quote($value));
        }
        else
        // only one arg and may be raw SQL, don't touch it
            return strval($name);
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

    public function where_like($names, $val, $mode=true)
    {
        if (is_scalar($names))
            $names = array($names);

        foreach ($names as $name)
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

    public function where_match($fields, $against)
    {
        if (is_scalar($fields))
            $fields = array($fields);

        foreach ($fields as &$field)
            $field = self::quote_name($field);

        $this->where[] = sprintf('match(%s) against (%s in boolean mode)',
            join(',', $fields), self::quote($against)
        );
    }

    public function where_fk($name, $field)
    {
        $this->where[] = self::quote_name($name).' = '.self::quote_name($field);
        return $this;
    }

    public function having($sql)
    {
        $this->having[] = call_user_func_array('self::clause', func_get_args());
        return $this;
    }
    protected static function ensure_array($vals)
    {
        if (is_null($vals)) $vals = array(null);
        if (is_scalar($vals)) $vals = preg_split('/\s*,\s*/', $vals);
        foreach ($vals as &$val) if (is_numeric($val) && preg_match('/^[0-9]+$/', $val)) $val = intval($val);
        return $vals;
    }

    // add a field IN(...values...)
    public function where_in($name, $vals)
    {
        if (is_object($vals))
        {
            $this->where[] = sprintf('%s in (%s)', self::quote_name($name), $vals->get_select());
            return $this;
        }
        $vals = static::ensure_array($vals);
        $this->where[] = !empty($vals)
            ? sprintf('%s in (%s)', self::quote_name($name), join(',', self::prepare($vals)))
            : sprintf('1 = 0 /* empty: %s IN() */', $name);
        return $this;
    }

    // add a field IN(...values...)
    public function where_not_in($name, $vals)
    {
        if (is_object($vals))
        {
            $this->where[] = sprintf('%s not in (%s)', self::quote_name($name), $vals->get_select());
            return $this;
        }
        $vals = static::ensure_array($vals);
        $this->where[] = !empty($vals)
            ? sprintf('%s not in (%s)', self::quote_name($name), join(',', self::prepare($vals)))
            : sprintf('1 = 0 /* empty: %s NOT IN() */', $name);
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

    // wrapper for limit
    public function paginate($page_num=1, $page_size=10)
    {
        $start_index = ($page_num - 1) * $page_size;
        $this->limit($start_index, $page_size);
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

    public function no_order()
    {
        $this->order = array();
        return $this;
    }

    // group by one or more fields
    public function group($vals)
    {
        $vals = static::ensure_array($vals);
        foreach ($vals as $val) $this->group[] = self::quote_name($val);
        $this->group = array_unique($this->group);
        return $this;
    }

    public function no_group()
    {
        $this->group = array();
        return $this;
    }

    // join a table
    public function join($table, $on=null)
    {
        $alias = null;

        if (is_string($table) && strpos($table, ' ') && !preg_match('/^\s*select/i', $table))
        {
            list ($table, $alias) = preg_split('/\s+/', trim($table));
            $table = self::quote_name($table);
        }

        if ($on)
        {
            $args = func_get_args(); array_shift($args);
            $clause = call_user_func_array('self::clause', $args);
            $this->join[] = sprintf('join '. $table .($alias ? ' '.$alias:'') . ' on ' . $clause);
        }
        else
        {
            $this->join[] = sprintf('join '. $table .($alias ? ' '.$alias:''));
        }

        return $this;
    }

    // left outer join a table
    public function left_join($table, $on=null)
    {
        $alias = null;

        if (is_string($table) && strpos($table, ' ') && !preg_match('/^\s*\(/i', $table))
        {
            list ($table, $alias) = preg_split('/\s+/', trim($table));
            $table = self::quote_name($table);
        }

        $args = func_get_args(); array_shift($args);
        $clause = call_user_func_array('self::clause', $args);

        $this->join[] = sprintf('left join '. $table .($alias ? ' '.$alias:'') . ' on ' . $clause);

        return $this;
    }

    // right outer join a table
    public function right_join($table, $on=null)
    {
        $alias = null;

        if (is_string($table) && strpos($table, ' ') && !preg_match('/^\s*\(/i', $table))
        {
            list ($table, $alias) = preg_split('/\s+/', trim($table));
            $table = self::quote_name($table);
        }

        $args = func_get_args(); array_shift($args);
        $clause = call_user_func_array('self::clause', $args);

        $this->join[] = sprintf('right join '. $table .($alias ? ' '.$alias:'') . ' on ' . $clause);

        return $this;
    }

    // set key/val pair to be written in undate or single row insert
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

    // set SQL_CALC_FOUND_ROWS
    public function calc()
    {
        $this->sql_calc_found = true;
        return $this;
    }

    // unset SQL_CALC_FOUND_ROWS
    public function no_calc()
    {
        $this->sql_calc_found = false;
        return $this;
    }

    // methods for building SQL fragments
    private function get_from()   { return 'from '.self::quote_name($this->table) .($this->alias ? ' '.$this->alias:''); }
    private function get_join()   { return $this->join ? join(' ', $this->join): ''; }
    private function get_where()  { return $this->where ? 'where '.join(' and ', $this->where) : ''; }
    private function get_having() { return $this->having ? 'having '.join(' and ', $this->having) : ''; }
    private function get_limit()  { return $this->limit ? 'limit '.$this->limit: ''; }
    private function get_order()  { return $this->order ? 'order by '.join(', ', $this->order): ''; }
    private function get_group()  { return $this->group ? 'group by '.join(', ', $this->group): ''; }

    // FOR UPDATE
    public function for_update()
    {
        $this->for_update = true;
        return $this;
    }

    // LOCK IN SHARE MDOE
    public function share_mode()
    {
        $this->share_mode = true;
        return $this;
    }

    // generate a SELECT query
    public function get_select()
    {
        $flags  = $this->sql_no_cache   ? 'sql_no_cache'        : '';
        $flags .= $this->sql_calc_found ? 'sql_calc_found_rows' : '';

        $fields_table = $this->alias ? $this->alias: self::quote_name($this->table);

        $fields = $this->fields ? join(', ', $this->fields): $fields_table.'.*';
        $from   = $this->get_from();
        $join   = $this->get_join();
        $where  = $this->get_where();
        $having = $this->get_having();
        $order  = $this->get_order();
        $group  = $this->get_group();
        $limit  = $this->get_limit();
        $mode   = $this->for_update ? 'for update': '';
        $mode   = $this->share_mode ? 'lock in share mode': '';
        // MySQL runs a needless filesort when grouping without an order clause. disable it.
        if ($group && !$order) $order = 'order by null';
        return "select $flags $fields $from $join $where $group $having $order $limit $mode";
    }

    // Often useful...
    public function __toString()
    {
        return $this->get_select();
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
        if ($this->debug) error_log('execute: '.$sql);

        $this->error = null;
        $this->error_msg = null;

        $host = preg_replace('/^([^\s]+).*$/', '\1', mysqli_get_host_info(self::$db));

        $this->rs = mysqli_query(self::$db, $sql, MYSQLI_STORE_RESULT);
        $this->rs_sql = $sql;
        $this->rs_fields = array();

        if ($this->rs === false)
        {
            $this->error = mysqli_errno(self::$db);
            $this->error_msg = mysqli_error(self::$db);
        }
        else
        if (is_object($this->rs))
        {
            while (($field = $this->rs->fetch_field()) && $field)
                $this->rs_fields[] = $field;
        }

        if ($this->sql_calc_found)
        {
            $this->found_rows = self::rawquery('select found_rows()')->fetch_value();
        }
        return $this;
    }

    public function ok($err=0)
    {
        if (!is_null($this->error))
            throw new Exception($this->error_msg, $err ? $err: $this->error);
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

        if ($this->cache === SQL::MEMCACHE)
            Cache::set($md5, $rows, $this->expire);

        if ($this->cache === SQL::CACHE)
            self::$_result_cache[$md5] = gzcompress(serialize($rows));
    }

    public function found()
    {
        return $this->found_rows;
    }

    // retrieve all available rows
    public function fetch_all($index=null)
    {
        $host = preg_replace('/^([^\s]+).*$/', '\1', mysqli_get_host_info(self::$db));

        $sql = $this->rs_sql ? $this->rs_sql: $this->get_select();
        $md5 = md5($sql);

        if (!$this->rs)
        {
            $rows = null;
            if ($this->cache && isset(self::$_result_cache[$md5]))
            {
                $rows = unserialize(gzuncompress(self::$_result_cache[$md5]));
            }

            if (($data_obj = Cache::get($md5)) && is_array($data_obj))
            {
                $rows = $data_obj;
            }

            if ($rows)
            {
                if ($this->debug)
                    error_log('cached ('.count($rows).'): '.$sql);
                foreach ($rows as $i => $row)
                    $rows[$i] = dict($row);
                return $rows;
            }

            $this->execute($sql);
        }

        $rows = array();
        while ($this->rs && ($row = mysqli_fetch_array($this->rs, MYSQLI_NUM)) && $row)
        {
            $j = count($rows);
            $res = array();
            $pri = array();
            foreach ($row as $i => $value)
            {
                $field = $this->rs_fields[$i];
                $res[$field->name] = $value;

                // NOT_NULL_FLAG = 1
                // PRI_KEY_FLAG = 2
                // UNIQUE_KEY_FLAG = 4
                // BLOB_FLAG = 16
                // UNSIGNED_FLAG = 32
                // ZEROFILL_FLAG = 64
                // BINARY_FLAG = 128
                // ENUM_FLAG = 256
                // AUTO_INCREMENT_FLAG = 512
                // TIMESTAMP_FLAG = 1024
                // SET_FLAG = 2048
                // NUM_FLAG = 32768
                // PART_KEY_FLAG = 16384
                // GROUP_FLAG = 32768
                // UNIQUE_FLAG = 65536

                if (!is_null($value))
                {
                    if ($field->flags & 32768)
                        $res[$field->name] = intval($value);
                }

                if ($field->flags & 2)
                    $pri[] = $value;
            }
            if ($pri) $j = join(':', $pri);
            $rows[$index && array_key_exists($index, $res) ? $res[$index]: $j] = $res;
        }

        $this->recache($rows);

        foreach ($rows as $i => $row)
            $rows[$i] = dict($row);

        return $rows;
    }

    // retrieve all rows as numerically indexed array
    public function fetch_all_numeric()
    {
        $rows = $this->fetch_all();
        foreach ($rows as $i => $row)
            $rows[$i] = dict($row->values());
        return $rows;
    }

    // retrieve a single field from all available rows
    public function fetch_field($name=null)
    {
        $out = array();
        foreach ($this->fetch_all() as $row)
            $out[] = $row[is_null($name) ? $row->keys()->get(0): $name];
        return $out;
    }

    // retrieve two fields from all available rows, as a key/val array (key must be UNIQUE or PRIMARY)
    public function fetch_pair($key, $val)
    {
        $out = array();
        foreach ($this->fetch_all() as $row)
            $out[$row->$key] = $row->$val;
        return $out;
    }

    // retrieve a single row
    public function fetch_one()
    {
        if (!$this->limit) $this->limit(1);
        $rows = $this->fetch_all();
        return count($rows) ? array_shift($rows): null;
    }

    // retrieve a single row numerically indexed
    public function fetch_one_numeric()
    {
        if (!$this->limit) $this->limit(1);
        $rows = $this->fetch_all_numeric();
        return count($rows) ? array_shift($rows): null;
    }

    // retrieve a single value
    public function fetch_value($name=null)
    {
        $row = $this->fetch_one();

        if (!$row->is_empty())
        {
            if (!is_null($name))
                return $row->$name;

            return $row->values()->get(0);
        }

        return null;
    }

    // wrappers to execute the current state as different queries
    public function select($fields=null) { if ($fields) $this->fields($fields); return $this->execute($this->get_select()); }

    public function delete() { return $this->execute($this->get_delete()); }

    public function insert($set=null, $val=null) { if ($set) $this->set($set, $val); return $this->execute($this->get_insert()); }

    public function update($set=null, $val=null) { if ($set) $this->set($set, $val); return $this->execute($this->get_update()); }

    public function replace($set=null, $val=null) { if ($set) $this->set($set, $val); return $this->execute($this->get_replace()); }

    public function insert_id($set=null, $val=null) { $this->insert($set, $val); return $this->rs ? mysqli_insert_id(self::$db): null; }

    public function truncate() { return $this->execute('truncate table '.self::quote_name($this->table)); }

    // initializer
    public static function query($table=null, $cache=SQL::NOCACHE, $db=null)
    {
        $s = new static($table, $db);
        if ($cache) $s->cache($cache);
        return $s;
    }

    // initializer
    public static function rawquery($sql=null, $cache=SQL::NOCACHE, $db=null)
    {
        $s = new static(null, $db);
        if ($cache) $s->cache($cache);
        $s->execute($sql);
        return $s;
    }

    // initializer
    public static function command($command, $db=null)
    {
        $s = new static(null, $db);
        $s->execute($command);
        return $s;
    }
}