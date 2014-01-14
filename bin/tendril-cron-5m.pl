#!/usr/bin/perl

use strict;
use DBI;
use Digest::MD5 qw(md5 md5_hex md5_base64);

my $dbi = "DBI:mysql:;mysql_read_default_file=./tendril.cnf;mysql_read_default_group=tendril";
my $db  = DBI->connect($dbi, undef, undef) or die("db?");
$db->do("SET NAMES 'utf8';");

my $servers = $db->prepare("select id, host, port from servers");

$servers->execute();

while (my $row = $servers->fetchrow_hashref())
{
	my $server_id = $row->{id};
	my $host = $row->{host};
	my $port = $row->{port};

	my ($lock) = $db->selectrow_array("select get_lock('tendril-cron-5m-$server_id', 1)");

	if ($lock == 1)
	{
		print "$host:$port\n";

		my $select = $db->prepare("select *, md5(info) as info_md5 from processlist_query_log where server_id = ? and info is not null and checksum is null");
		if ($select->execute($server_id))
		{
			while (my $row = $select->fetchrow_hashref())
			{
				my $query = $row->{info};
				$query =~ s/"(?:[^"\\]|\\.)*"/?/ig;
				$query =~ s/'(?:[^'\\]|\\.)*'/?/ig;
				$query =~ s/\b([0-9]+)\b/?/ig;
				$query =~ s/\/\*.*?\*\///ig;
				$query =~ s/\s+/ /ig;
				$query =~ s/^\s+//ig;
				$query =~ s/\s+$//ig;
				$query =~ s/[(][?,'" ]+?[)]/?LIST?/g;

				my $update = $db->prepare("update processlist_query_log set checksum = md5(?) where server_id = ? and id = ? and md5(info) = ?");
				my $rs = $update->execute($query, $server_id, $row->{id}, $row->{info_md5});
				$update->finish();

				print ".";
			}
		}
		print "\n";
		$select->finish();
	}
}
$servers->finish();
