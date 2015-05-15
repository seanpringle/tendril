<style type="text/css">

#innodb .purge {
	text-align: right;
}

#innodb .bps {
	text-align: right;
}

#innodb .lfs {
	text-align: right;
}

#innodb .flatc {
	text-align: right;
}

#innodb .fpt.off {
	color: red;
}

#innodb .purge.long {
	color: red;
}

#innodb .bphr {
	text-align: right;
}

#innodb .bphr.poor {
	color: red;
}

#innodb .bpwf {
	text-align: right;
}

#innodb .io {
	text-align: right;
}

#innodb .waits {
	text-align: right;
}

#innodb .deadlocks {
	text-align: right;
}

#innodb .waits.problem {
	color: red;
}

#innodb .unit {
	color: #999;
}

</style>

<form method="GET" class="search">
    <table cellspacing="0" cellpadding="0">
    <tr>
        <th>Host</th>
        <th></th>
    </tr>
    <tr>
        <td>
            <input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="submit" value="Search" />
        </td>
    </tr>
    </table>
</form>

<table id="innodb">

<tr>
	<th>
		Host
	</th>
	<th class="fpt" title="innodb_file_per_table">
		FPT
	</th>
	<th class="bps" title="innodb_buffer_pool_size (GB)">
		BPS
	</th>
	<th class="lfs" title="innodb_log_file_size (MB)">
		LFS
	</th>
	<th class="flatc" title="innodb_flush_log_at_trx_commit">
		FL
	</th>
	<th class="purge" title="Innodb_history_list_length (purge lag)">
		Purge
	</th>
	<th class="bpwf" title="Innodb_buffer_pool_wait_free">
		BPWF
	</th>
	<th class="deadlocks" title="Innodb_deadlocks, last 1 hr">
		DL
	</th>
	<th class="bphr" title="buffer pool hit rate, last 1 hr">
		BPHR
	</th>
	<th class="io" title="Innodb_data_read / Innodb_data_written, last 1 hr">
		I/O
	</th>
	<th class="waits" title="Innodb_s_lock_spin_waits/sec, last 1 hr">
		SWS
	</th>
	<th class="waits" title="Innodb_x_lock_spin_waits/sec, last 1 hr">
		SWX
	</th>
	<th class="waits" title="Innodb_s_lock_spin_rounds/sec, last 1 hr">
		SRS
	</th>
	<th class="waits" title="Innodb_x_lock_spin_rounds/sec, last 1 hr">
		SRX
	</th>
	<th class="waits" title="Innodb_s_lock_os_waits/sec, last 1 hr">
		OWS
	</th>
	<th class="waits" title="Innodb_x_lock_os_waits/sec, last 1 hr">
		OSX
	</th>
</tr>

<?php

foreach ($rows as $row)
{
	$row = $row->export();
	$server = new Server($row);

	$bphr = expect($row, 'buffer_pool_hit_rate', 'float', 0);

	$spin_s_waits  = round(expect($row, 'spin_s_waits',  'float', 0));
	$spin_x_waits  = round(expect($row, 'spin_x_waits',  'float', 0));
	$spin_s_rounds = round(expect($row, 'spin_s_rounds', 'float', 0));
	$spin_x_rounds = round(expect($row, 'spin_x_rounds', 'float', 0));
	$os_s_waits    = round(expect($row, 'os_s_waits',    'float', 0));
	$os_x_waits    = round(expect($row, 'os_x_waits',    'float', 0));

	$cells = array(
		tag('td', array(
			'html' => tag('a', array(
				'href' => sprintf('/host/view/%s/%s', $server->name(), $server->port()),
				'html' => escape($server->describe()),
			))
		)),
		tag('td', array(
			'class' => sprintf('fpt %s', expect($row, 'innodb_file_per_table') == 'ON' ? 'on': 'off'),
			'html' => expect($row, 'innodb_file_per_table') == 'ON' ? 'on': 'off',
		)),
		tag('td', array(
			'class' => sprintf('bps'),
			'html' => sprintf('%s<span class="unit">G</span>', expect($row, 'innodb_buffer_pool_size', 'pint', 0)),
		)),
		tag('td', array(
			'class' => sprintf('lfs'),
			'html' => sprintf('%s<span class="unit">M</span>', expect($row, 'innodb_log_file_size', 'pint', 0)),
		)),
		tag('td', array(
			'class' => sprintf('flatc'),
			'html' => expect($row, 'innodb_flush_log_at_trx_commit', 'uint', 0),
		)),
		tag('td', array(
			'class' => sprintf('purge %s', expect($row, 'innodb_history_list_length', 'uint', 0) > 1000000 ? 'long': 'short'),
			'html' => expect($row, 'innodb_history_list_length', 'uint', 0),
		)),
		tag('td', array(
			'class' => sprintf('bpwf'),
			'html' => expect($row, 'innodb_buffer_pool_wait_free', 'uint', 0),
		)),
		tag('td', array(
			'class' => 'deadlocks',
			'html' => expect($row, 'deadlocks', 'uint', 0),
		)),
		tag('td', array(
			'class' => sprintf('bphr %s', $bphr < 95 ? 'poor': 'good'),
			'html' => number_format($bphr, 1).'<span class="unit">%</span>',
		)),
		tag('td', array(
			'class' => sprintf('io'),
			'html' => number_format(expect($row, 'io_ratio', 'float', 0), 0),
		)),
		tag('td', array(
			'class' => sprintf('waits %s', $spin_s_waits > 100000 ? 'problem': 'ok'),
			'html' => $spin_s_waits,
		)),
		tag('td', array(
			'class' => sprintf('waits %s', $spin_x_waits > 100000 ? 'problem': 'ok'),
			'html' => $spin_x_waits,
		)),
		tag('td', array(
			'class' => sprintf('waits %s', $spin_s_rounds > 100000 ? 'problem': 'ok'),
			'html' => $spin_s_rounds,
		)),
		tag('td', array(
			'class' => sprintf('waits %s', $spin_s_rounds > 100000 ? 'problem': 'ok'),
			'html' => $spin_x_rounds,
		)),
		tag('td', array(
			'class' => sprintf('waits %s', $os_s_waits > 10000 ? 'problem': 'ok'),
			'html' => $os_s_waits,
		)),
		tag('td', array(
			'class' => sprintf('waits %s', $os_x_waits > 10000 ? 'problem': 'ok'),
			'html' => $os_x_waits,
		)),
	);

	print tag('tr', array(
		'html' => $cells,
	));
}

?>

</table>