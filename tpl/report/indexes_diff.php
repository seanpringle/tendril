<form method="GET" class="search">

    <strong>hostA</strong>
    <input type="text" name="a" value="<?= escape(pkg()->request('a')) ?>" placeholder="regex" />

    <strong>hostB</strong>
    <input type="text" name="b" value="<?= escape(pkg()->request('b')) ?>" placeholder="regex" />

    <strong>schema</strong>
    <input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="regex" />

    <input type="submit" value="Search" />
</form>

<table>

<tr>
    <th>Schema</th>
    <th>Table</th>
    <th>IndexA</th>
    <th>IndexB</th>
</tr>

<?php

foreach ($rows as $row)
{
    $cells = array(
        tag('td', array(
            'html' => escape($row['schema_name']),
        )),
        tag('td', array(
            'html' => escape($row['table_name']),
        )),
        tag('td', array(
            'html' => escape($row['index_name_a']) ?: '-',
        )),
        tag('td', array(
            'html' => escape($row['index_name_b']) ?: '-',
        )),
    );

    print tag('tr', array(
        'html' => $cells,
    ));
}

?>

</table>