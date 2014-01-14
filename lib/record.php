<?php

class Record
{
	public $ok = true;

	protected static $_schema_cache = array();

	protected $_autoinc  = false;
	protected $_table    = 'unknown';
	protected $_schema   = array();
	protected $_snapshot = array();
	protected $_missing  = false;
	protected $_dberror  = null;
	protected $_pk       = 'id';

    const NOCACHE = 0;
    const CACHE = 1;
    const MEMCACHE = 2;

	protected $_cache = 0;
	protected $_expire = 0;

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
			{
				$this->_pk = $name;
				$this->_autoinc = (strstr($extra, 'auto_increment')) ?1:0;
			}

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
			$id = $this->_pk;
			$this->$id = $pk;
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
	 * Memcache key
	 */
	protected function mc_key()
	{
		$pk = $this->_pk;
		return sprintf('%s.%s.%s', $this->_table, $pk, $this->$pk);
	}
	/**
	 * Load record from database, or external cache.
	 * @return success
	 */
	protected function load()
	{
		$pk = $this->_pk;
		
		$row = null;
		$sql = sql::query($this->_table)->where($pk, $this->$pk);
		$cache = true;

		if ($this->_cache == self::MEMCACHE && mc())
		{
			error_log(sprintf('(memcached) read %s', $this->mc_key()));
			$row = @mc()->get($this->mc_key());
			$cache = false;
		}

		if (!$row)
		{
			$row = $sql->fetch_one();
			$this->check($sql);
		}

		if (!$row || !$this->ok)
		{
			$this->_missing = true;
			$this->ok = false;
			return false;
		}

		$this->inject($row);

		if ($this->ok && $cache && $this->_cache == self::MEMCACHE && mc())
		{
			error_log(sprintf('(memcached) save %s', $this->mc_key()));
			mc()->set($this->mc_key(), $this->export(), $this->_expire);
		}

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
			if (preg_match('/(char|text)/', $type) || is_array($default))
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
		$pk = $this->_pk;
		$this->ok = ($this->_missing || !$this->$pk) ? $this->create(): $this->update();
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
			$pk = $this->_pk;

			$this->check(
				sql::query($this->_table)->set($data)->where($pk, $this->$pk)->update()
			);

			if ($this->ok && $this->_cache == self::MEMCACHE && mc())
			{
				error_log(sprintf('(memcached) save %s', $this->mc_key()));
				mc()->set($this->mc_key(), $this->export(), $this->_expire);
			}
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
			unset($data[$this->_pk]);

		$pk = $this->_pk;
		$sql = sql::query($this->_table)->set($data);
		$id = $this->_autoinc ? $sql->insert_id(): $sql->insert();

		if (!$this->check($sql)) return false;
		if ($this->_autoinc) $this->$pk = $id;

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

		if ($this->ok && $this->_cache == self::MEMCACHE && mc())
		{
			error_log(sprintf('(memcached) save %s', $this->mc_key()));
			mc()->set($this->mc_key(), $this->export(), $this->_expire);
		}
		return true;
	}
	/**
	 * Delete record from database
	 */
	public function delete()
	{
		$pk = $this->_pk;
		return $this->check(
			sql::query($this->_table)->where($pk, $this->$pk)->delete()
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
