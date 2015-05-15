
<style>
#schema-list .right {
	text-align: right;
}
#schema-list .suffix {
	color: #999;
}
#schema-list .right.large {
	color: red;
}
</style>

<form method="GET" class="search">
	<strong>schema</strong>
	<input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />
	<input type="submit" value="Search" />
</form>

<table id="schema-list">

<tr>
	<th class="">
		Host
	</th>
	<th class="">
		Schema
	</th>
	<th class="right">
		Data
	</th>
	<th class="right">
		Indexes
	</th>
	<th class="right">
		Total
	</th>
</tr>

<?php

$servers = array();

foreach ($rows as $row)
{
	$server_id = $row['server_id'];
	$servers[$server_id] = $host = expect($servers, $server_id, 'object') ?: new Server(intval($server_id));

	$cells = array(
		tag('td', array(
			'html' => tag('a', array(
				'href' => sprintf('/host/%s/%d', $host->name(), $host->port()),
				'html' => $host->describe(),
			))
		)),
		tag('td', array(
			'title' => escape($row['schema_name']),
			'html' => escape(truncate($row['schema_name'], 15)),
		)),
		tag('td', array(
			'class' => 'right',
			'title' => escape($row['data']),
			'html' => number_format($row['data_gb'], 1) . suffix('G'),
		)),
		tag('td', array(
			'class' => sprintf('right %s', $row['data_gb'] < $row['indexes_gb']
				&& $row['indexes_gb'] > 1 ? 'large': 'small'),
			'title' => escape($row['indexes']),
			'html' => number_format($row['indexes_gb'], 1) . suffix('G'),
		)),
		tag('td', array(
			'class' => 'right',
			'title' => escape($row['total']),
			'html' => number_format($row['total_gb'], 1) . suffix('G'),
		)),
	);

	print tag('tr', array(
		'html' => $cells,
	));
}

?>

</table>