
<style>
#cluster-list .right {
	text-align: right;
}
#cluster-list .suffix {
	color: #999;
}
#cluster-list .right.large {
	color: red;
}
</style>

<table id="cluster-list">

<tr>
	<th class="">
		Master
	</th>
	<th class="">
		&nbsp;
	</th>
	<th class="">
		Schemas
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
			'html' => $host->cluster(),
		)),
		tag('td', array(
			'title' => escape($row['schema_names']),
			'html' => escape(pkg()->format_csv($row['schema_names'])),
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