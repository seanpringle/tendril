
<style>
#slow-queries-checksum .right {
	text-align: right;
}
#slow-queries-checksum tr.sample {
	font-size: smaller;
	color: #999;
}
</style>

<form method="GET" class="search">
	<strong>checksum</strong>
	<input type="text" style="width: 15em;" name="checksum" value="<?= escape(pkg()->request('checksum')) ?>" placeholder="md5" />
	<strong>host</strong>
	<input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
	<strong>user</strong>
	<input type="text" name="user" value="<?= escape(pkg()->request('user')) ?>" placeholder="regex" />
	<strong>schema</strong>
	<input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />
	<strong>hours</strong>
	<input style="width: 2em" type="text" name="hours" value="<?= escape(pkg()->request('hours')) ?>" placeholder="#" />
	<input type="submit" value="Search" />
</form>

<table id="slow-queries-checksum">

<tr>
	<th class="">
		Host
	</th>
	<th class="">
		User
	</th>
	<th class="">
		Schema
	</th>
	<th class="">
		Client
	</th>
	<th class="">
		Source
	</th>
	<th class="right">
		Runtime
	</th>
	<th class="right">
		Stamp
	</th>
</tr>

<?php

$servers = sql::query('tendril.servers')->fetch_all();

foreach ($rows as $row)
{
	$host = new Host($row['server_id']);
	$sample = str_replace(',', ', ', $row['info']);

    list ($ip, $port) = explode(':', $row['host']);

    $client = expect($dns, $ip, 'string', $ip);
    list($client_short) = preg_match('/^[a-z]/', $client) ? explode('.', $client): $client;

	$fqdn = (($ips = find_ipv4($sample)) && ($name = dns_reverse($ips[0])) && $name != $ips[0]) ? $name: '-';

	$cells = array(
		tag('td', array(
			'title' => 'Host',
			'html' =>
				tag('a', array(
					'href' => sprintf('/host/view/%s/%d', $host->name(), $host->port()),
					'html' => escape($host->describe()),
				)),
		)),
		tag('td', array(
			'title' => 'User',
			'html' => escape($row['user']),
		)),
		tag('td', array(
			'html' => escape($row['db']),
		)),
		tag('td', array(
			'title' => escape($client),
			'html' => escape($client_short),
		)),
		tag('td', array(
			'html' => escape($fqdn),
		)),
		tag('td', array(
			'title' => 'Time',
			'class' => 'right',
			'html' => number_format($row['time'], 0).'s',
		)),
		tag('td', array(
			'class' => 'right',
			'title' => datetime_casual($row['stamp']),
			'html' => date('Y-m-d h:i:s', strtotime($row['stamp'])),
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
