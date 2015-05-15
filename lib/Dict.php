<?php

/**
 * Create a dict.
 * @param  array  $a
 * @return Dict
 */
function dict($a=array())
{
    return Dict::make($a);
}

/**
 * True if $obj is a Dict.
 * @param  mixed  $obj
 * @return boolean
 */
function is_dict($obj)
{
    return is_object($obj) && $obj instanceof Dict;
}

/**
 * A light-weight Collection.
 *
 * CakePHP's Collection blows this out fo the water for complex stuff, but
 * sometimes it is handy to have a simple alternative without dependencies.
 * Where possible, to avoid wheel reinvention, this tries to be a thin OO
 * layer over PHP's built-in array functions.
 *
 * print_r(
 *     dict([ 1, 2, 3 ])
 *     ->filter(
 *         function ($key, $val) {
 *             return $val > 1;
 *         })
 *     ->export()
 * );
 */
class Dict implements ArrayAccess, Iterator, Serializable
{
    private $_data = array();
    private $_type = array();

    // Iterator
    private $_ipos = 0;
    private $_ikeys = null;

    /**
     * [__construct description]
     * @param array $row
     */
    public function __construct($row=array())
    {
        $this->import($row);
    }

    /**
     * Static constructor
     * @param  array  $row
     * @return Dict
     */
    public static function make($row=array())
    {
        return new static($row);
    }

    /**
     * Magic method __get
     * @param  mixed $key scalar
     * @return mixed
     */
    public function __get($key)
    {
        return isset($this->_data[$key]) ? $this->_data[$key]: null;
    }

    /**
     * Magic method __set
     * @param mixed $key scalar
     * @param mixed $val
     * @return void
     */
    public function __set($key, $val)
    {
        if (!isset($this->_type[$key]))
        {
            $this->_type[$key] = gettype($val);
        }
        else
        if (!is_null($val))
        {
            switch ($this->_type[$key])
            {
                case 'boolean':
                    $val = $val ? true: false;
                    break;
                case 'integer':
                    $val = intval($val);
                    break;
                case 'double':
                    $val = floatval($val);
                    break;
                case 'array':
                    $val = is_array($val) ? $val: (array) $val;
                    break;
                case 'object':
                    $val = is_object($val) ? $val: (object) $val;
                    break;
                case 'string':
                    $val = strval($val);
                    break;
            }
        }
        $this->_data[$key] = $val;
    }

    /**
     * Magic method __isset
     * @param  mixed   $key scalar
     * @return boolean
     */
    public function __isset($key)
    {
        return isset($this->_data[$key]);
    }

    /**
     * Magic method __unset
     * @param mixed $key scalar
     */
    public function __unset($key)
    {
        unset($this->_data[$key]);
        unset($this->_type[$key]);
    }

    /**
     * Magic method __toString. Auto-serialize.
     * @return string
     */
    public function __toString()
    {
        return $this->serialize();
    }

    /**
     * Iterator
     * @return void
     */
    public function rewind()
    {
        $this->_ikeys = array_keys($this->_data);
        $this->_ipos = 0;
    }

    /**
     * Iterator
     * @return mixed
     */
    public function current()
    {
        return $this->get($this->_ikeys[$this->_ipos]);
    }

    /**
     * Iterator
     * @return mixed scalar
     */
    public function key()
    {
        return $this->_ikeys[$this->_ipos];
    }

    /**
     * Iterator
     * @return void
     */
    public function next()
    {
        $this->_ipos++;
    }

    /**
     * Iterator
     * @return boolean
     */
    public function valid()
    {
        return isset($this->_ikeys[$this->_ipos]);
    }

    /**
     * ArrayAccess
     * @param  mixed $off scalar
     * @param  mixed $val
     * @return void
     */
    public function offsetSet($off, $val)
    {
        $this->__set($off, $val);
    }

    /**
     * ArrayAccess
     * @param  mixed $off scalar
     * @return void
     */
    public function offsetUnset($off)
    {
        unset($this->_data[$off]);
        unset($this->_type[$off]);
    }

    /**
     * ArrayAccess
     * @param  mixed $off scalar
     * @return void
     */
    public function offsetExists($off)
    {
        return isset($this->_data[$off]);
    }

    /**
     * ArrayAccess
     * @param  mixed $off scalar
     * @return mixed
     */
    public function offsetGet($off)
    {
        return $this->__get($off);
    }

    /**
     * Serializable
     * @return string
     */
    public function serialize()
    {
        return json_encode(array($this->_data, $this->_type));
    }

    /**
     * Serializable
     * @param  string $str
     * @return Dict
     */
    public function unserialize($str)
    {
        list ($this->_data, $this->_type) = json_decode($str, true);
        return $this;
    }

    /**
     * Retrieve value by key.
     * @param  mixed $key scalar
     * @param  mixed $def default value
     * @return mixed
     */
    public function get($key, $def=null)
    {
        return isset($this->_data[$key]) ? $this->$key: $def;
    }

    /**
     * Define a value by key.
     * @param  mixed $key scalar
     * @param  mixed $val
     * @return Dict
     */
    public function set($key, $val=null)
    {
        $this->$key = $val;
        return $this;
    }

    /**
     * Bulk define data.
     * @param  array $row
     * @return Dict
     */
    public function import($row)
    {
        $this->_data = array();
        $this->_type = array();
        foreach ($row as $key => $val)
            $this->$key = $val;
        return $this;
    }

    /**
     * Bulk retrieve data.
     * @return array
     */
    public function export()
    {
        return $this->_data;
    }

    /**
     * Return first item.
     * @return mixed
     */
    public function first()
    {
        return empty($this->_data) ? null: reset($this->_data);
    }

    /**
     * Return first item.
     * @return mixed
     */
    public function last()
    {
        return empty($this->_data) ? null: end($this->_data);
    }

    /**
     * array_column
     * @param  mixed $key
     * @return Dict
     */
    public function column($key)
    {
        return dict(array_column($this->_data, $key));
    }

    /**
     * @return boolean
     */
    public function is_empty()
    {
        return empty($this->_data);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->_data);
    }

    /**
     * @return array
     */
    public function keys()
    {
        return dict(array_keys($this->_data));
    }

    /**
     * @return array
     */
    public function values()
    {
        return dict(array_values($this->_data));
    }

    /**
     * Sort pairs.
     * @param  callable $call
     * @return Dict
     */
    public function sort($call=null)
    {
        if (is_null($call))
            asort($this->_data);
        else
            uasort($this->_data, $call);
        return $this;
    }

    /**
     * Sort pairs.
     * @param  callable $call
     * @return Dict
     */
    public function key_sort($call)
    {
        if (is_null($call))
            ksort($this->_data);
        else
            uksort($this->_data, $call);
        return $this;
    }

    /**
     * @return string
     */
    public function join($c=',')
    {
        return join($c, $this->_data);
    }

    /**
     * Alias for join()
     * @return string
     */
    public function implode($c=',')
    {
        return implode($c, $this->_data);
    }

    /**
     * Clone.
     * @return Dict
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * Apply callback to each pair. Destructive! See copy().
     * @param  callable $call
     * @return Dict
     */
    public function map($call)
    {
        foreach ($this->_data as $key => $val)
            $this->$key = $call($key, $val);
        return $this;
    }

    /**
     * Apply callback to each pair. Keep matches. Destructive! See copy().
     * @param  callable $call
     * @return Dict
     */
    public function filter($call)
    {
        foreach ($this->_data as $key => $val)
            if (!$call($key, $val)) $this->__unset($key);
        return $this;
    }

    /**
     * Apply callback to each pair. Remove matches. Destructive! See copy().
     * @param  callable $call
     * @return Dict
     */
    public function reject($call)
    {
        foreach ($this->_data as $key => $val)
            if ($call($key, $val)) $this->__unset($key);
        return $this;
    }

    /**
     * Apply callback to each pair, reducing.
     * @param  calable $call
     * @return mixed
     */
    public function reduce($call)
    {
        if (count($this->_data) == 0)
            return null;

        $keys  = array_keys($this->_data);
        $value = $this->_data[array_shift($keys)];

        foreach ($keys as $key)
            $value = $call($value, $this->_data[$key]);

        return $value;
    }

    /**
     * @return int
     */
    public function sum()
    {
        return array_sum($this->_data);
    }

    /**
     * @return mixed
     */
    public function min()
    {
        return min($this->_data);
    }

    /**
     * @return mixed
     */
    public function max()
    {
        return max($this->_data);
    }

    /**
     * True if any value is true.
     * @return boolean
     */
    public function any()
    {
        foreach ($this->_data as $key => $val)
            if ($val) return true;
        return false;
    }

    /**
     * True if any value is true.
     * @param  calable $call
     * @return boolean
     */
    public function some($call)
    {
        foreach ($this->_data as $key => $val)
            if ($call($val)) return true;
        return false;
    }

    /**
     * True if all values are true.
     * @return boolean
     */
    public function all()
    {
        foreach ($this->_data as $key => $val)
            if (!$val) return false;
        return true;
    }

    /**
     * True if all values are true.
     * @param  calable $call
     * @return boolean
     */
    public function every($call)
    {
        foreach ($this->_data as $key => $val)
            if (!$call($val)) return false;
        return true;
    }

    /**
     * True if any value is null.
     * @return boolean
     */
    public function any_null()
    {
        foreach ($this->_data as $key => $val)
            if (is_null($val)) return true;
        return false;
    }

    /**
     * True if all values are null.
     * @return boolean
     */
    public function all_null()
    {
        foreach ($this->_data as $key => $val)
            if (!is_null($val)) return false;
        return true;
    }
}