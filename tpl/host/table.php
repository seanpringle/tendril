<style>

#host-list th.uptime,
#host-list td.uptime {
    text-align: right;
    white-space: nowrap;
}

#host-list td.uptime.short {
    color: red;
}

#host-list th.contact,
#host-list td.contact {
    text-align: right;
    white-space: nowrap;
}

#host-list td.contact.long {
    color: red;
}

#host-list td.replication {
    font-family: monospace;
    font-size: 120%;
}

#host-list th.qps,
#host-list td.qps {
    text-align: right;
}

#host-list td.qps.many {
    color: red;
}

#host-list th.replag,
#host-list td.replag {
    text-align: right;
}

#host-list td.replag.bad {
    color: red;
}

#host-list th.repsql,
#host-list td.repsql {
    text-align: right;
}

#host-list td.repsql.bad {
    color: red;
}

#host-list span.suffix {
    color: #999;
}

#host-list th.ram,
#host-list td.ram {
    text-align: right;
}

</style>


<table id="host-list">

<tr>
    <th class="name">Host</th>
    <th class="ipv4">IPv4</th>
    <th class="release">Release</th>
    <th class="ram">RAM</th>
    <th class="uptime">Up</th>
    <th class="contact" title="last contact">Act.</th>
    <th class="qps">QPS</th>
    <th class="repsql">Rep</th>
    <th class="replag">Lag</th>
    <th class="rep">Tree</th>
</tr>

<?php

function uptime($secs)
{
    return $secs ? duration_short($secs): '-';
}

function contact($secs)
{
    return is_null($secs) ? '-': duration_short($secs);
}

foreach ($hosts as $row)
{
    $row = $row->export();
    $h = new Server($row);

    $masters = 'n/a';

    if ($row['master_ids'])
    {
        $masters    = array();
        $master_ids = explode(',', $row['master_ids']);

        foreach ($master_ids as $master_id)
        {
            $s = new Server($master_id);
            $masters[] = tag('a', array(
                'href' => sprintf('/host/view/%s/%d', $s->name(), $s->port),
                'html' => escape($s->describe()),
            ));
        }
        $masters = join(', ', $masters);
    }

    $slaves = 'n/a';

    if ($row['slave_ids'])
    {
        $slaves    = array();
        $slave_ids = explode(',', $row['slave_ids']);

        foreach ($slave_ids as $slave_id)
        {
            $s = new Server($slave_id);
            $slaves[] = tag('a', array(
                'href' => sprintf('/host/view/%s/%d', $s->name(), $s->port),
                'html' => escape($s->describe()),
            ));
        }
        $slaves = join(', ', $slaves);
    }

    $branch  = '-';
    $release = '-';
    $uptime  = expect($uptimes,  $h->id, 'uint', 0);
    $version = expect($versions, $h->id, 'str', '-');
    $contact = strtotime($row['contact']);
    $sql     = expect($repsql, $h->id, 'str', '-');
    $lag     = expect($replag, $h->id, 'uint');
    $mem     = expect($ram, $h->id, 'uint', 0);

    if ($version)
    {
        if (preg_match('/mysql/i', $version))
            $branch = 'MySQL';

        if (preg_match('/wm-log/i', $version))
            $branch = 'MySQL-fb';

        if (preg_match('/mariadb/i', $version))
            $branch = 'MariaDB';

        if (preg_match_all('/([0-9]+\.[0-9]+\.[0-9]+)/', $version, $matches))
            $release = $matches[1][0];
    }

    $cells = array(
        tag('td', array(
            'class' => 'name',
            'html' =>
                tag('a', array(
                    'href' => sprintf('/host/view/%s/%d', $h->name(), $h->port),
                    'html' => escape($h->describe()),
                ))
        )),
        tag('td', array(
            'class' => 'ipv4',
            'html' => escape($h->ipv4()),
        )),
        tag('td', array(
            'class' => 'release',
            'title' => escape($branch),
            'html' => escape($release),
        )),
        tag('td', array(
            'class' => 'ram',
            'html' => escape($mem) . suffix('G'),
        )),
        tag('td', array(
            'class' => sprintf('uptime %s', $uptime && $uptime < 86400 ? 'short': 'long'),
            'html' => escape(uptime($uptime)),
        )),
        tag('td', array(
            'class' => sprintf('contact %s', $contact < (time() - 60) ? 'long': 'short'),
            'html' => $contact ? escape(contact(time() - $contact)): '',
        )),
        tag('td', array(
            'class' => sprintf('qps %s', $row['qps'] > 10000 ? 'many': 'few'),
            'html' => escape($row['qps']),
        )),
        tag('td', array(
            'class' => sprintf('repsql %s', preg_match('/No/', $sql) ? 'bad': ''),
            'html' => $sql,
        )),
        tag('td', array(
            'class' => sprintf('replag %s', $lag > 30 ? 'bad': ''),
            'html' => contact($lag),
        )),
        tag('td', array(
            'class' => 'rep',
            'html' => sprintf('masters: %s slaves: %s', $masters, $slaves),
        )),
    );

    print tag('tr', array(
        'html' => str($cells),
    ));
}

?>

</table>

