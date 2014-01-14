
<p class="note">
	Overview pulled from SHOW FULL PROCESSLIST snapshots (10s interval) in <a href="/activity">activity</a>.
	For more detail see <a href="https://ishmael.wikimedia.org">ishmael</a>.
</p>

<style>
#slow-queries .right {
	text-align: right;
}
#slow-queries tr.sample {
	font-size: smaller;
	color: #999;
}
</style>

<form method="GET" class="search">
	<strong>host</strong>
	<input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
	<strong>user</strong>
	<input type="text" name="user" value="<?= escape(pkg()->request('user')) ?>" placeholder="regex" />
	<strong>schema</strong>
	<input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />
	<strong>query</strong>
	<select name="qmode">
		<option value="eq" <?= pkg()->request('qmode') == 'eq' ? ' selected': '' ?>>=~</option>
		<option value="ne" <?= pkg()->request('qmode') == 'ne' ? ' selected': '' ?>>!~</option>
	</select>
	<input type="text" name="query" value="<?= escape(pkg()->request('query')) ?>" placeholder="regex" />
	<strong>hours</strong>
	<input style="width: 2em" type="text" name="hours" value="<?= escape(pkg()->request('hours')) ?>" placeholder="#" />
	<input type="submit" value="Search" />
</form>

<table id="slow-queries">

<tr>
	<th class="right">
		Hits
	</th>
	<th class="right">
		Tmax
	</th>
	<th class="right">
		Tavg
	</th>
	<th class="right">
		Tsum
	</th>
	<th class="">
		Hosts
	</th>
	<th class="">
		Users
	</th>
	<th class="">
		Schemas
	</th>
</tr>

<?php

$servers = sql::query('tendril.servers')->fetch_all();

foreach ($rows as $row)
{
	$hosts = array();
	foreach (explode(',', $row['servers']) as $server_id)
	{
		$host = new Host(expect($servers, $server_id, 'array', $server_id));
		$hosts[] = tag('a', array(
			'href' => sprintf('/host/%s/%d', $host->name(), $host->port()),
			'html' => escape($host->describe()),
		));
	}

	$hosts  = join(', ', $hosts);
	$users  = str_replace(',', ', ', $row['users']);
	$dbs    = str_replace(',', ', ', $row['dbs']);
	$sample = str_replace(',', ', ', $row['sample']);

	$shost = new Host(expect($servers, $row['sample_server_id'], 'array', $row['sample_server_id']));
	$sample = sprintf('%s /* %s %s %s */', $sample, $row['checksum'], $shost->describe(), $row['sample_db']);

	if (($ips = find_ipv4($sample)) && ($name = gethostbyaddr($ips[0])) && $name != $ips[0])
	{
		$sample = sprintf('%s /* %s */', $sample, $name);
	}

	$cells = array(
		tag('td', array(
			'title' => 'Hits',
			'class' => 'right',
			'html' => $row['hits'],
		)),
		tag('td', array(
			'title' => 'Tmax',
			'class' => 'right',
			'html' => $row['max_time'],
		)),
		tag('td', array(
			'title' => 'Tavg',
			'class' => 'right',
			'html' => number_format($row['avg_time'], 0),
		)),
		tag('td', array(
			'title' => 'Tsum',
			'class' => 'right',
			'html' => number_format($row['sum_time'], 0),
		)),
		tag('td', array(
			'html' => $hosts,
		)),
		tag('td', array(
			'html' => escape($users),
		)),
		tag('td', array(
			'html' => escape($dbs),
		)),
	);

	print tag('tr', array(
		'html' => $cells,
	));

	$cells = array(
		tag('td', array(
			'colspan' => 7,
			'html' => escape($sample),
		))
	);

	print tag('tr', array(
		'class' => 'sample',
		'html' => $cells,
	));

}

?>

</table>

