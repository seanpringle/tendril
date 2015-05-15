<style type="text/css">

#processlist {
    width: 100%;
}

#processlist .query td {
    font-size: smaller;
    color: #666;
    padding-bottom: 1em;
}

#processlist .time.long {
    color: red;
}

</style>

<form method="GET" class="search">
    <table cellspacing="0" cellpadding="0">
    <tr>
        <th>Host</th>
        <th>User</th>
        <th>Schema</th>
    </tr>
    <tr>
        <td>
            <input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="text" name="user" value="<?= escape(pkg()->request('user')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="submit" value="Search" />
        </td>
    </tr>
    </table>
</form>

<table id="processlist">

    <tr>
        <th>Server</th>
        <th>Transaction</th>
        <th>Time</th>
        <th>Connection</th>
        <th>User</th>
        <th>Client</th>
        <th>Port</th>
        <th>Database</th>
    </tr>

<?php

foreach ($rows as $row)
{
    $host = new Server($row['server_id']);

    $ip     = '';
    $port   = '';
    $client = '';
    $client_short = '';

    if ($row['host'])
    {
        list ($ip, $port) = explode(':', $row['host']);

        $client = expect($dns, $ip, 'string', $ip);
        list($client_short) = preg_match('/^[a-z]/', $client) ? explode('.', $client): $client;
    }

    $cells = array(
        tag('td', array(
            'class' => 'server',
            'html' =>
                tag('a', array(
                    'href' => sprintf('/host/view/%s/%d', $host->name(), $host->port),
                    'html' => escape($host->name_short()),
                ))
        )),

        tag('td', array(
            'class' => 'user',
            'html' => escape($row['trx_id']),
        )),
        tag('td', array(
            'class' => sprintf('time %s', $row['trx_time'] > 60 ? 'long': ''),
            'html' => escape(duration_short($row['trx_time'])),
        )),

        tag('td', array(
            'class' => 'id',
            'html' => escape($row['trx_mysql_thread_id']),
        )),
        tag('td', array(
            'class' => 'user',
            'html' => escape($row['user']),
        )),
        tag('td', array(
            'class' => 'host',
            'title' => escape($client.':'.$port),
            'html' => escape($client_short),
        )),
        tag('td', array(
            'class' => 'port',
            'html' => escape($port),
        )),
        tag('td', array(
            'class' => 'db',
            'html' => escape($row['db']),
        )),


    );

    print tag('tr', array(
        'class' => 'metadata',
        'html' => str($cells)
    ));

    $query  = $row['info'] ? $row['info']: $row['trx_query'];
    $sample = preg_replace('/,([^ ])/', ', \1', $query);

    if (($ips = find_ipv4($sample)) && ($name = dns_reverse($ips[0])) && $name != $ips[0])
    {
        $sample = sprintf('%s /* %s */', $sample, $name);
    }

    $cells = array(
        tag('td', array(
            'class' => 'info',
            'colspan' => 8,
            'html' => trim($sample) ? escape($sample): '(no query running)',
        )),
    );

    print tag('tr', array(
        'class' => 'query',
        'html' => str($cells)
    ));

    foreach ($row['queries'] as $i => $qrow)
    {
        $sample = preg_replace('/,([^ ])/', ', \1', $qrow['info']);

        if (($ips = find_ipv4($sample)) && ($name = dns_reverse($ips[0])) && $name != $ips[0])
        {
            $sample = sprintf('%s /* %s */', $sample, $name);
        }

        $cells = array(
            tag('td', array(
                'class' => 'info',
                'colspan' => 8,
                'html' => trim($sample),
            )),
        );

        print tag('tr', array(
            'class' => 'query',
            'html' => str($cells)
        ));
    }
}

?>

</table>

