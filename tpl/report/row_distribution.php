<p>
	EXPLAIN UNION is a cheap way to count rows by range. Run it on several slaves as InnoDB is vague.
	Useful for designing table partitioning.
</p>

<form method="GET" class="search">
	<strong>schema</strong>
	<input type="text" name="schema" value="<?= escape(pkg()->request('schema')) ?>" placeholder="name" />
	<strong>table</strong>
	<input type="text" name="table" value="<?= escape(pkg()->request('table')) ?>" placeholder="name" />
	<strong>field</strong>
	<input type="text" name="field" value="<?= escape(pkg()->request('field')) ?>" placeholder="name" />
	<strong>min</strong>
	<input style="width: 5em;" type="text" name="min" value="<?= escape(pkg()->request('min')) ?>" placeholder="name" />
	<strong>max</strong>
	<input style="width: 5em;" type="text" name="max" value="<?= escape(pkg()->request('max')) ?>" placeholder="name" />
	<strong>inc</strong>
	<input style="width: 5em;" type="text" name="inc" value="<?= escape(pkg()->request('inc')) ?>" placeholder="name" />
	<input type="submit" value="Generate" />
</form>

<p>
	<?= nl2br(escape($query)) ?>
</p>