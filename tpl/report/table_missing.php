<form method="GET" class="search">
	<strong>host</strong>
	<input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
	<strong>schema</strong>
	<input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />
	<strong>table</strong>
	<input type="text" name="table" value="<?= escape(pkg()->request('table')) ?>" placeholder="name" />
	<input type="submit" value="Search" />
</form>

<table>

<?php

foreach ($rows as $row)
{
	$server = new Server($row);

	$cells = array(
		tag('td', array(
			'html' => tag('a', array(
				'href' => sprintf('/host/view/%s/%s', $server->name(), $server->port()),
				'html' => escape($server->describe()),
			))
		)),
		tag('td', array(
			'html' => escape(pkg()->format_csv($row['schema_names'])),
		)),
	);

	print tag('tr', array(
		'html' => $cells,
	));
}

?>

</table>