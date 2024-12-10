#!/usr/bin/perl
use Data::Dumper; # Not necessary
use Getopt::Long;
use lib ".";
use lib "/opt/fio2userdb";
use database;
use JSON;
use File::Copy;
no warnings 'utf8';

$|= 1;

my $dbhost = $ENV{'USERDB_DB_HOST'} || 'localhost';
my $dbuser = $ENV{'USERDB_DB_USERNAME'} || 'root';
my $dbpasswd = $ENV{'USERDB_DB_PASSWORD'} || '';
my $dbname = $ENV{'USERDB_DB_NAME'} || 'userdb';

$cfg{database}{DBIstring}= "DBI:mysql:database=$dbname;host=$dbhost";
$cfg{database}{db_user}= $dbuser;
$cfg{database}{db_password}= $dbpasswd;
db_connect() or die;

# Anti-duplicate
if ( -f "/tmp/fio2userdb_parovani_is_running" )
{
	my $PID=`cat /tmp/fio2userdb_parovani_is_running`;
  chomp $PID;
	if ( $PID && -d "/proc/$PID" )
	{
		print "fio2userdb: another instance is running (pid $PID), exiting.\n";
		exit 0;
  }
	else
  {
		print "fio2userdb: removing stale lockfile /tmp/fio2userdb_parovani_is_running\n";
		unlink "/tmp/fio2userdb_parovani_is_running";
  }
}
`echo $$ > /tmp/fio2userdb_parovani_is_running`;
# Anti-duplicate - end

my $counter = 0;
my $ss_proVraceniPlateb = '6666666665'; # odchozí platba co má SS 6666666665 se považuje za vratku a userdb to pak odečítá podle VS daného usera z jeho virt. účtu

my $q = "SELECT id, vs, ss, datum, castka, datum, cislo_uctu, spolek, druzstvo FROM PrichoziPlatba WHERE TypPrichoziPlatby_id = '1' AND (castka > 0 OR ss = '$ss_proVraceniPlateb')";

($sth) = db_get_sth($q);
printf "$0: Párování start, %u plateb\n", $sth->rows;

while (my $row = $sth->fetchrow_hashref)
{
  db_do("START TRANSACTION");

  my $idPlatby = $row->{'id'};
  my $vsPlatby = $row->{'vs'};
  my $castkaPlatby = $row->{'castka'};
  my $datumPlatby = $row->{'datum'};

  printf "Párování %u/%u: datum %s částka %s protiúčet %s vs [%s] ss [%s]\n",
    ++$counter, $sth->rows, $datumPlatby, $castkaPlatby, $row->{'cislo_uctu'}, $vsPlatby, $row->{'ss'};

  my $poznamkaPlatby = sprintf 'Neznama platba, chybny VS:[%s]', $vsPlatby;
  my $TypPohybuNaUctu_id = 14;					# Neznama prichozi platba
  my $matchingUID = undef;

  if (!$vsPlatby | int($vsPlatby) == 0 )
  {
    $poznamkaPlatby = 'Neznama platba, nema VS'
  }
  else
  {
    ($matchingUID) = db_get(sprintf "SELECT id FROM Uzivatel WHERE id = %u", $vsPlatby);

    if ($matchingUID)
    {
      if ($castkaPlatby > 0)
      {
        $TypPohybuNaUctu_id = 1;					# Prichozi platba
      }
      else
      {
        $TypPohybuNaUctu_id = 16;					# Vrácení přeplatku členství
      }
    }
  }

	my $i = sprintf("INSERT INTO UzivatelskeKonto (PrichoziPlatba_id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, poznamka, zmenu_provedl, spolek, druzstvo)
    VALUES (%u, %s, %u, %f, '%s', '%s', '%s', %u, %u)",
     $idPlatby, $matchingUID ? $matchingUID : 'null',
     $TypPohybuNaUctu_id,
     $castkaPlatby, $datumPlatby,
     $poznamkaPlatby,
     1,
     $row->{'spolek'}, $row->{'druzstvo'}
  );
  my ($result)= db_do($i);
  if (not defined $result)
  {
    db_do("ROLLBACK");
    continue;
  }

  my $u = sprintf "UPDATE PrichoziPlatba SET TypPrichoziPlatby_id = 2 WHERE id = %u LIMIT 1", $idPlatby;
  my ($result)= db_do($u);
  if (not defined $result)
  {
    db_do("ROLLBACK");
    continue;
  }

  db_do("COMMIT");
}

unlink "/tmp/fio2userdb_parovani_is_running";

printf "$0: Párování konec\n";

exit 0;

sub trim
{
	my $str= shift;
	$str=~ s/^\s*//;
	$str=~ s/\s*$//;
	return $str;
}

sub trimZero
{
	my $str= shift;
	$str=~ s/^0*//;
	$str= '0' if $str eq '';
	return $str;
}

sub _exit
{
  unlink "/tmp/fio2userdb_parovani_is_running";
  exit @_[0];
}
