<?php

class Search extends sql
{
	public function __construct($table, $cache=sql::NOCACHE, $expire=0)
	{
		parent::__construct($table);
		$this->cache($cache, $expire);
	}
	public function by_id($ids)
	{
		return $this->where_in($this->alias().'.id', $ids);
	}
    public function fields_from($table)
    {
        $alias = null; if (strpos($table, ' '))
            list ($table, $alias) = preg_split('/\s+/', trim($table));

        foreach (sql::table_fields($table) as $row)
        {
            if ($row['COLUMN_NAME'] != 'id')
                $this->field(($alias ? $alias.'.': '').$row['COLUMN_NAME']);
        }

        return $this;
    }
}