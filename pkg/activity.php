<?php

class Package_Activity extends Package
{
    public function head()
    {
        return $this->head_refresh();
    }

    public function page()
    {
        switch ($this->action())
        {
            default:
                list ($processlist, $dns) = $this->data_list();
                include ROOT .'tpl/activity/list.php';
        }
    }

    private function data_list()
    {
        $search = sql('tendril.processlist p')
            ->join('tendril.servers s', 'p.server_id = s.id')
            ->fields('p.*, s.host as server')
            ->where_gt('p.time', 30)
            ->where_eq('p.command', 'Query')
            ->order('p.time', 'desc');

        foreach (array('wikiadmin', 'wikiuser') as $user)
        {
            if ($this->request($user, 'uint', 1) === 0)
            {
                $search->where_ne('user', $user);
            }
        }

        if ($this->request('research', 'uint', 1) === 0)
        {
            $search->where('user not like "research%"');
            $search->where('user <> "halfak"');
        }

        if ($this->request('labsusers', 'uint', 1) === 0)
        {
            $search->where('s.host not like "labs%"');
            $search->where('user not like "u%"');
        }

        $processlist = $search->fetch_all();

        $dns = sql('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $processlist, $dns );
    }
}