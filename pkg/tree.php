<?php

class Package_Tree extends Package
{
    private $qps      = null;
    private $versions = null;
    private $uptimes  = null;
    private $replag   = null;

    public function page()
    {
        list ($clusters) = $this->data_index();
        include ROOT .'tpl/tree/index.php';
    }

    private function node_html($host)
    {
        $html = tag('a', array(
            'href' => sprintf('/host/view/%s/%d', $host->name(), $host->port()),
            'html' => escape($host->name_short()),
            'title' => sprintf('%s:%d', $host->name(), $host->port()),
            'class' => sprintf('%s %s',
                $host->enabled ? 'enabled': 'disabled',
                $this->replag[$host->id] > 60 ? 'lagging': 'replicating'
            ),
        ));

        return $html;
    }

    private function tree_recurse($hosts, $repl, $master_name, &$cluster)
    {
        foreach ($repl as $slave_id => $family)
        {
            $slave   = new Host($hosts[$slave_id]);
            $cluster[] = array(
                array(
                    'v' => $slave->describe(),
                    'f' => $this->node_html($slave),
                ),
                $master_name,
            );
            $this->tree_recurse($hosts, $family, $slave->describe(), $cluster);
        }
    }

    public function data_index()
    {
        $hosts = sql::query('tendril.servers')
            ->fetch_all();

        $this->qps = sql::query('tendril.global_status_log gsl')
            ->fields(array(
                'srv.id',
                'floor((max(value)-min(value))/(unix_timestamp(max(stamp))-unix_timestamp(min(stamp)))) as qps',
            ))
            ->join('tendril.strings str', 'gsl.name_id = str.id')
            ->join('tendril.servers srv', 'gsl.server_id = srv.id')
            ->where_eq('str.string', 'questions')
            ->where('gsl.stamp > now() - interval 10 minute')
            ->group('server_id')
            ->fetch_pair('id', 'qps');

        $this->versions = sql::query('tendril.global_variables')
            ->fields('server_id, variable_value')
            ->where_eq('variable_name', 'version')
            ->fetch_pair('server_id', 'variable_value');

        $this->uptimes = sql::query('tendril.global_status')
            ->fields('server_id, variable_value')
            ->where_eq('variable_name', 'uptime')
            ->fetch_pair('server_id', 'variable_value');

        $this->replag = sql::query('tendril.slave_status a')
            ->join('tendril.slave_status b', 'a.server_id = b.server_id')
            ->fields('a.server_id, a.variable_value')
            ->where_eq('a.variable_name', 'seconds_behind_master')
            ->where_eq('b.variable_name', 'slave_sql_running')
            ->where_eq('b.variable_value', 'Yes')
            ->fetch_pair('server_id', 'variable_value');

        $repl = sql::query('tendril.servers m')
            ->join('tendril.replication r', 'm.id = r.master_id')
            ->join('tendril.servers s', 'r.server_id = s.id')
            ->fields(array(
                'm.id as master_id',
                'count(*) as size',
                'group_concat(s.id order by s.host) as slave_ids'
            ))
            ->group('m.id')
            ->order('size', 'desc')
            ->order('m.host', 'asc')
            ->fetch_pair('master_id', 'slave_ids');

        foreach ($repl as $master_id => $slave_ids)
        {
            $slave_ids = array_flip($slave_ids ? explode(',', $slave_ids): array());
            foreach ($slave_ids as $slave_id => $n) $slave_ids[$slave_id] = array();
            $repl[$master_id] = $slave_ids;
        }

        foreach ($repl as $master_id => $slave_ids)
        {
            foreach ($slave_ids as $slave_id => $a)
            {
                if (isset($repl[$slave_id]))
                {
                    $repl[$master_id][$slave_id] = $repl[$slave_id];
                    unset($repl[$slave_id]);
                }
            }
        }

        $clusters = array();

        foreach ($repl as $master_id => $family)
        {
            $master = new Host($hosts[$master_id]);

            $cluster = array(
                array(
                    array(
                        'v' => $master->describe(),
                        'f' => $this->node_html($master)
                            .tag('div', array(
                                'html' => $master->cluster(),
                            )),
                    ),
                    '',
                )
            );

            $this->tree_recurse($hosts, $family, $master->describe(), $cluster);

            $clusters[] = $cluster;
        }

        return array( $clusters );
    }
}