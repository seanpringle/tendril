
<form method="GET">
	<strong>host</strong>
	<input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" />
	<input type="submit" value="Search" />
</form>

<?php include 'table.php'; ?>