<style type="text/css">

#processlist {
    width: 100%;
}

#processlist .query td {
    font-size: smaller;
    color: #666;
    padding-bottom: 1em;
}

#activity-hide label {
    padding: 0 0.5em;
}

#processlist .time.long {
    color: red;
}

</style>

<form method="GET" class="search">
    <strong>host</strong>
    <input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
    <strong>user</strong>
    <input type="text" name="user" value="<?= escape(pkg()->request('user')) ?>" placeholder="regex" />
    <strong>schema</strong>
    <input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />
    <strong>com</strong>
    <input type="text" style="width: 6em;" name="command" value="<?= escape(pkg()->request('command')) ?>" placeholder="regex" />
    <strong>time</strong>
    <input type="text" style="width: 3em;" name="time" value="<?= escape(pkg()->request('time')) ?>" placeholder="#s" />
    <strong>query</strong>
    <select name="qmode">
        <option value="eq" <?= pkg()->request('qmode') == 'eq' ? ' selected': '' ?>>=~</option>
        <option value="ne" <?= pkg()->request('qmode') == 'ne' ? ' selected': '' ?>>!~</option>
    </select>
    <input type="text" name="query" value="<?= escape(pkg()->request('query')) ?>" placeholder="regex" />

    <input type="submit" value="Search" />
</form>

<table id="processlist">

    <tr>
        <th>Server</th>
        <th>Connection</th>
        <th>User</th>
        <th>Client</th>
        <th>Port</th>
        <th>Database</th>
        <th>Command</th>
        <th>Time</th>
    </tr>

<?php

foreach ($rows as $row)
{
    $host = new Server($row['server_id']);

    list ($ip, $port) = explode(':', $row['host']);

    $client = expect($dns, $ip, 'string', $ip);
    list($client_short) = preg_match('/^[a-z]/', $client) ? explode('.', $client): $client;

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
            'class' => 'id',
            'html' => escape($row['id']),
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
        tag('td', array(
            'class' => 'command',
            'html' => escape($row['command']),
        )),
        tag('td', array(
            'class' => sprintf('time %s', $row['time'] > 86400 ? 'long': ''),
            'html' => escape(duration_short($row['time'])),
        )),

    );

    print tag('tr', array(
        'class' => 'metadata',
        'html' => str($cells)
    ));

    $sample = preg_replace('/,([^ ])/', ', \1', $row['info']);

    if (($ips = find_ipv4($sample)) && ($name = dns_reverse($ips[0])) && $name != $ips[0])
    {
        $sample = sprintf('%s /* %s */', $sample, $name);
    }

    $cells = array(
        tag('td', array(
            'class' => 'info',
            'colspan' => 8,
            'html' => trim($sample) ? escape($sample): 'n/a',
        )),
    );

    print tag('tr', array(
        'class' => 'query',
        'html' => str($cells)
    ));

}

?>

</table>

<ul>
    <li>
        <a href="?user=%28wik|res%29&command=Sleep&time=3600">Sleepers &gt; 3600s</a>
    </li>
</ul>