<form method="GET" class="search">
    <table cellspacing="0" cellpadding="0">
    <tr>
        <th>Host</th>
        <th>Schema</th>
        <th>Table</th>
        <th>Missing Column</th>
        <th></th>
    </tr>
    <tr>
        <td>
            <input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="text" name="table" value="<?= escape(pkg()->request('table')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="text" name="column" value="<?= escape(pkg()->request('column')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="submit" value="Search" />
        </td>
    </tr>
    </table>
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