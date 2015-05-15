<?php

$clusters = array(
    gethostbyname('s1-master') => 's1',
    gethostbyname('s2-master') => 's2',
    gethostbyname('s3-master') => 's3',
    gethostbyname('s4-master') => 's4',
    gethostbyname('s5-master') => 's5',
    gethostbyname('s6-master') => 's6',
    gethostbyname('s7-master') => 's7',
    gethostbyname('m1-master') => 'm1',
    gethostbyname('m2-master') => 'm2',
    gethostbyname('m3-master') => 'm3',
    gethostbyname('m4-master') => 'm4',
    gethostbyname('m5-master') => 'm5',
    gethostbyname('x1-master') => 'x1',
);

class Server
{
    public $ok = false;
    private $row = array();

    public function __construct($id=0)
    {
        $row = null;

        if (is_numeric($id) && $id > 0)
        {
            $row = sql('tendril.servers')
                ->cache(SQL::MEMCACHE, 300)
                ->where_eq('id', intval($id))
                ->fetch_one();
        }
        else
        if (!is_numeric($id) && is_string($id))
        {
            $row = sql('tendril.servers')
                ->cache(SQL::MEMCACHE, 300)
                ->where_eq('host', $id)
                ->fetch_one();

            if (is_null($row) && preg_match('/^[a-z]{2}[0-9]+$/', $id))
            {
                $row = sql('tendril.servers')
                    ->cache(SQL::MEMCACHE, 300)
                    ->where_like('host', "$id%")
                    ->fetch_one();
            }
        }
        if (!is_scalar($id))
        {
            $row = $id;
        }

        if (is_dict($row))
        {
            $row = $row->export();
        }

        if (is_array($row))
        {
            $this->row = $row;
            $this->ok = true;
        }
    }

    public function __get($name)
    {
        return isset($this->row[$name]) ? $this->row[$name]: null;
    }

    public static function by_name_port($name, $port=3306)
    {
        $row = sql('tendril.servers')
            ->where_eq('host', $name)
            ->where_eq('port', $port)
            ->fetch_one();
        return new self($row);
    }

    public static function by_m_server_id($id)
    {
        $row = sql('tendril.servers')
            ->where_eq('m_server_id', $id)
            ->fetch_one();
        return new self($row);
    }

    public function ipv4()
    {
        return $this->ipv4 ?: dns_reverse($this->host);
    }

    public function name()
    {
        return $this->host;
    }

    public function port()
    {
        return $this->port;
    }

    public function name_short()
    {
        $parts = explode('.', $this->host);
        return $parts ? $parts[0]: '-';
    }

    public function describe()
    {
        return ($this->port == 3306) ? $this->name_short(): sprintf('%s:%d', $this->name_short(), $this->port);
    }

    public function cluster()
    {
        global $clusters;

        $node = $this;

        while ($node->m_master_id)
        {
            $node = self::by_m_server_id($node->m_master_id);
        }

        return expect($clusters, $node->ipv4(), 'string', '-');
    }

}