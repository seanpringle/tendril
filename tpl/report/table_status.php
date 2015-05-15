<form method="GET" class="search">
	<strong>host</strong>
	<input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
	<strong>schema</strong>
	<input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />
	<strong>table</strong>
	<input type="text" name="table" value="<?= escape(pkg()->request('table')) ?>" placeholder="regex" />
	<strong>engine</strong>
	<input type="text" name="engine" value="<?= escape(pkg()->request('engine')) ?>" placeholder="regex" />
	<strong>data</strong>
	<input type="text" style="width: 2em;" name="data" value="<?= escape(pkg()->request('data')) ?>" placeholder="GB" />
	<strong>index</strong>
	<input type="text" style="width: 2em;" name="index" value="<?= escape(pkg()->request('index')) ?>" placeholder="GB" />
	<input type="submit" value="Search" />
</form>

<style>
#table-list .suffix {
	color: #999;
}
#table-list th.rows,
#table-list td.rows {
	text-align: right;
}
#table-list th.avg,
#table-list td.avg {
	text-align: right;
}
#table-list th.data,
#table-list td.data {
	text-align: right;
}
#table-list th.indexes,
#table-list td.indexes {
	text-align: right;
}
#table-list td.indexes.large {
	color: red;
}
#table-list th.ai,
#table-list td.ai {
	text-align: right;
}
</style>

<table id="table-list">

<tr>
	<th class="host">
		Host
	</th>
	<th class="schema">
		Schema
	</th>
	<th class="table">
		Table
	</th>
	<th class="rows" title="InnoDB is vague!">
		Rows
	</th>
	<th class="avg" title="average row length">
		Avg.
	</th>
	<th class="data">
		Data
	</th>
	<th class="indexes" title="Indexes">
		Idx.
	</th>
	<th class="engine">
		Engine
	</th>
	<th class="ai">
		auto-inc
	</th>
</tr>

<?php

$hosts = array();

foreach ($rows as $row)
{
	$hosts[$row['server_id']] = $server = expect($hosts, $row['server_id'], 'object') ?: new Server($row['server_id']);

	$cells = array(
		tag('td', array(
			'html' => tag('a', array(
				'href' => sprintf('/host/view/%s/%s', $server->name(), $server->port()),
				'html' => escape($server->describe()),
			))
		)),
		tag('td', array(
			'class' => 'schema',
			'html' => escape(pkg()->format_csv($row['table_schema'])),
		)),
		tag('td', array(
			'class' => 'table',
			'html' => escape($row['table_name']),
		)),
		tag('td', array(
			'class' => 'rows',
			'title' => escape($row['table_rows']),
			'html' => number_format($row['table_rows']/1000000, 1) . suffix('M'),
		)),
		tag('td', array(
			'class' => 'avg',
			'html' => number_format($row['avg_row_length'], 0) . suffix('B'),
		)),
		tag('td', array(
			'class' => 'data',
			'title' => escape($row['data_length']),
			'html' => number_format($row['data_length_gb'], 1) . suffix('G'),
		)),
		tag('td', array(
			'class' => sprintf('indexes %s', $row['data_length_gb'] < $row['index_length_gb']
				&& $row['index_length_gb'] > 1 ? 'large': 'small'),
			'title' => escape($row['index_length']),
			'html' => number_format($row['index_length_gb'], 1) . suffix('G'),
		)),
		tag('td', array(
			'class' => 'engine',
			'html' => escape($row['engine']),
		)),
		tag('td', array(
			'class' => 'ai',
			'html' => escape($row['auto_increment']),
		)),
	);

	print tag('tr', array(
		'html' => $cells,
	));
}

?>

</table>