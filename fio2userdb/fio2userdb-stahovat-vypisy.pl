#!/usr/bin/perl
#
# Import bankovnich vypisu z https://www.fio.cz/ib_api/rest/ -> UserDB
# vpithart@lhota.hkfree.org pro hkfree.org
#
# Exit Codes:
# 0  ... OK, transactions imported
# 1  ... OK, no new transactions
# 2  ... failed, temporary error on import
# 255 ... failed, parse error, invalid file content
#
# Pouziti:
# ./fio2userdb-stahovani-vypisu.pl
#
use Data::Dumper; # Not necessary
use Getopt::Long;
use lib ".";
use lib "/opt/fio2userdb";
use database;
use JSON;
use File::Copy;
no warnings 'utf8';

$|= 1;

$main::cfg{logging}{database}= 0;

my $overwrite;
my $debug;
my $import_incremental;
my $reimport_last_week;
my $from_file;

GetOptions (
	"debug" => \$debug,
	"overwrite"   => \$overwrite,
  "import-incremental" => \$import_incremental,
  "reimport-last-week" => \$reimport_last_week,
  "from-file=s" => \$from_file,
);

$main::cfg{logging}{database}= 1 if $debug;

my $url = "https://www.fio.cz/ib_api/rest/last/TOKEN/transactions.json";
my $url_reimport = "https://www.fio.cz/ib_api/rest/periods/TOKEN/DATE1/DATE2/transactions.json";

if ( ! $import_incremental && ! $from_file && ! $reimport_last_week )
{
	print "Pouziti:\n";
  print " $0 [options] --import-incremental\n";
  print "    ... stáhne nové výpisy od minule a importuje do DB\n";
  print "        ($url)\n";
  print "        (tohle spouštěj z cronu 1x za hodinu)\n";
  print " $0 [options] --reimport-last-week\n";
  print "    ... stáhne výpisy za posledních 7 dní (do včera), porovná s tím co je v DB a doimportuje chybějící\n";
  print "        ($url_reimport)\n";
  print "        (tohle spouštěj z cronu 1x za den)\n";
  print " $0 [options] --from-file </tmp/fio.2018-06-06.211350366.id16586408377.json>\n";
  print "    ... importuje ze souboru namísto stahování z Fio API\n";
  print " Options\n";
  print "  --overwrite ... přepsat data pokud už stejný soubor (podle MD5) byl importován\n";
  print "  --debug ... zobrazit všechny provedený SQL queries\n";
	exit;
}

my $token = $ENV{'FIO_READONLY_TOKEN'};
$token or die "Fio Bank secret token is missing (set the FIO_READONLY_TOKEN env variable)";

my $dbhost = $ENV{'USERDB_DB_HOST'} || 'localhost';
my $dbuser = $ENV{'USERDB_DB_USERNAME'} || 'root';
my $dbpasswd = $ENV{'USERDB_DB_PASSWORD'} || '';
my $dbname = $ENV{'USERDB_DB_NAME'} || 'userdb';

$cfg{database}{DBIstring}= "DBI:mysql:database=$dbname;host=$dbhost";
$cfg{database}{db_user}= $dbuser;
$cfg{database}{db_password}= $dbpasswd;
db_connect() or die;

db_do("CREATE TABLE IF NOT EXISTS `FioDownloadedFiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(500) COLLATE utf8mb3_czech_ci NOT NULL,
  `local_account_no` char(16) COLLATE utf8mb3_czech_ci NOT NULL,
  `local_account_name` char(20) CHARACTER SET utf8mb3 NOT NULL DEFAULT '',
  `balance_before_date` date NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `debit_sum` decimal(15,2) DEFAULT NULL,
  `credit_sum` decimal(15,2) DEFAULT NULL,
  `seq_no` decimal(3,0) DEFAULT NULL,
  `statement_date_created` date NOT NULL,
  `date_imported` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `file_md5sum` char(32) COLLATE utf8mb3_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45956 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci");

db_do("LOCK TABLES FioDownloadedFiles WRITE, PrichoziPlatba WRITE");

my $delete_on_overwrite;

my $trxNumber= 0;
my $recordsImported= 0;
my $recordsMissing= 0;
my $totalCredit= 0;
my $totalDebit= 0;

# Anti-duplicate
if ( -f "/tmp/fio2userdb_is_running" )
{
	my $PID=`cat /tmp/fio2userdb_is_running`;
  chomp $PID;
	if ( $PID && -d "/proc/$PID" )
	{
		print "fio2userdb: another instance is running (pid $PID), exiting.\n";
		exit 0;
  }
	else
  {
		print "fio2userdb: removing stale lockfile /tmp/fio2userdb_is_running\n";
		unlink "/tmp/fio2userdb_is_running";
  }
}
`echo $$ > /tmp/fio2userdb_is_running`;
# Anti-duplicate - end

my $file_md5sum;

my $keep_copies_in = "/var/fio-bank-statements/";
mkdir $keep_copies_in unless -d $keep_copies_in;
my $filename = "fio-statements-".($reimport_last_week?'last7days':'incremental').'.'.`date +"%FT%T"|tr -d '\n'`.".json";

local $/ = undef;
if ( $from_file )
{
  print "Starting $0 v$VERSION: loading [$from_file]\n";
  open( FILE, $from_file ) or die "Cannot open input file [$from_file]: $!";
  $file_md5sum= trim(`md5sum '$from_file' | cut -d ' ' -f 1`);
}
else
{
  if ($reimport_last_week)
  {
    $url = $url_reimport;
    our ($DATE1)= db_get("SELECT date_sub(curdate(), interval 7 day)");
    our ($DATE2)= db_get("SELECT date_sub(curdate(), interval 1 day)");
    $url =~ s/DATE1/$DATE1/;
    $url =~ s/DATE2/$DATE2/;
  }
  my $url_real = $url;
  $url_real =~ s/TOKEN/$token/;
  print "Starting $0 v$VERSION: loading [$url]\n";

  my $tmpfile = "/tmp/fio.transactions.00.tmp.json";
  my $cmd = "curl --output '$tmpfile' --retry 3 -s -S '$url_real'";
  system("bash -o pipefail -c \"" . $cmd . " > >(sed s/$token/.../) 2> >(sed s/$token/.../ 1>&2)\"");

  if ($? == -1) { die "internal error: [$cmd]: $!"; }
  elsif ($? & 127) { die "curl died with signal %d", ($? & 127); }
  else
  {
    my $exval= $? >> 8;
    _exit(2) if ($exval != 0);
  }

  -f $tmpfile or die "internal error: the tmpfile [$tmpfile] not found after running curl.";

  # keep local copy of downloaded JSON file
  copy($tmpfile, $keep_copies_in.$filename) or die "Copy [$tmpfile] [$keep_copies_in$filename] failed: $!";
  print "Copy saved into $keep_copies_in$filename\n";

  open( FILE, $tmpfile ) or die "Cannot open tmpfile [$tmpfile]: $!";
  $file_md5sum= trim(`md5sum '$tmpfile' | cut -d ' ' -f 1`);
}
binmode FILE;
my $json_encoded = <FILE>;
close FILE;
unlink $tmpfile;

my $json = decode_json($json_encoded);
my %json = %$json;

my $info = $json{'accountStatement'}{'info'};
my %info = %$info;
my $trxlist = $json{'accountStatement'}{'transactionList'}{'transaction'};
my @trxlist = @$trxlist;

# print Dumper \%info;
# {
#   "accountId": "107207255",
#   "bankId": "2010",
#   "currency": "CZK",
#   "iban": "CZ1420100000000107207255",
#   "bic": "FIOBCZPPXXX",
#   "openingBalance": 0.00,
#   "closingBalance": 2.99,
#   "dateStart": "2025-05-01+0200",
#   "dateEnd": "2025-06-06+0200",
#   "yearList": null,
#   "idList": null,
#   "idFrom": 16586377,
#   "idTo": 16586377,
#   "idLastDownload": null
# },

my $localAccountNo= $info{accountId};		# 107207255
my $num_trx= scalar(@trxlist);
print "Statements for $info{accountId}/$info{bankId} $info{currency}";
print " (incremental)" if $import_incremental && !$from_file;
print " (recent 7 days $DATE1 ... $DATE2)" if $reimport_last_week && !$from_file;
print ": opening $info{openingBalance}".($info{idFrom} ? " (idFrom $info{idFrom})":'').", closing $info{closingBalance}".($info{idTo} ? " (idTo $info{idTo})":'')."; $num_trx transaction(s)";
print " (last ID $info{idLastDownload})" if $info{idLastDownload};
print "\n";

if ( $num_trx == 0 )
{
  print "No new transactions since last run (ID $info{idLastDownload})\n";
  _exit(1);
}

my $delete_on_overwrite;
my ($duplicate_exists,$duplicate_filename,$duplicate_date_created,$duplicate_date_imported)= db_get("SELECT id,filename,statement_date_created,date_imported FROM FioDownloadedFiles WHERE file_md5sum='$file_md5sum' LIMIT 1");

if ( $duplicate_exists )
{
	if ( $overwrite )
	{
		print "Bank statements import: $filename ($file_md5sum): DUPLICATE FILE: the same content imported already from [$duplicate_filename] at $duplicate_date_imported (statement date: $duplicate_date_created) as record #$duplicate_exists in table FioDownloadedFiles; WILL BE OVERWRITTEN\n";
		$delete_on_overwrite= "DELETE FROM FioDownloadedFiles WHERE id=$duplicate_exists LIMIT 1";
	}
	else
	{
		print "Bank statements import: $filename ($file_md5sum): DUPLICATE FILE: the same content imported already from [$duplicate_filename] at $duplicate_date_imported (statement date: $duplicate_date_created) as record #$duplicate_exists in table FioDownloadedFiles; ignored\n";
		_exit(1);
	}
}

db_do("START TRANSACTION");

db_do($delete_on_overwrite) if ( $delete_on_overwrite );

my $i= sprintf 'INSERT INTO FioDownloadedFiles (filename, file_md5sum, local_account_no, balance_before, balance_before_date, balance_after, statement_date_created) '.
  'VALUES ("%s", "%s", "%s", %.2f, "%s", %.2f, "%s" )',
  $filename, $file_md5sum, $localAccountNo, $info{openingBalance}, substr($info{dateStart},0,10), $info{closingBalance}, substr($info{dateEnd},0,10)
;

my ($result)= db_do($i);
if ( not defined $result )
{
  db_do("ROLLBACK");
  _exit(2);		# temporary error on import
}

my ($bsfid)= db_get("SELECT last_insert_id()");


foreach $trxref (@trxlist)
{
  my %trx = %$trxref;

	++$trxNumber;

	#print "DEBUG: Trx$trxNumber: raw: [". Dumper(\%trx) ."]\n" if $debug;

	my $date= $trx{column0}{value};			          # "column0": { "value": "2018-06-06+0200","name": "Datum", "id": 0 },
	my $recordType= $trx{column8}{value};		      # "column8": { "value": "Příjem převodem uvnitř banky", "name": "Typ", "id": 8 },
	my $recordId= $trx{column22}{value};		      # "column22": { "value": 16586408377, "name": "ID pohybu", "id": 22 },
	my $remoteAccountNo= $trx{column2}{value};	  # "column2": { "value": "2601406638", "name": "Protiúčet", "id": 2 },
	my $remoteBankCode= $trx{column3}{value};	    # "column3": { "value": "2010", "name": "Kód banky", "id": 3 }
  my $type= $trx{column8}{value};               # "column8": { "value": "Příjem převodem uvnitř banky", "name": "Typ", "id": 8 },
	my $amount= $trx{column1}{value};	        	  # "column1": { "value": 2.99, "name": "Objem", "id": 1 },
	my $symConstant= $trx{column4}{value};		    # "column4": { "value": "0123", "name": "KS", "id": 4 },
	my $symVariable= $trx{column5}{value};		    # "column5": { "value": "0001090028", "name": "VS", "id": 5 },
	my $symSpecific= $trx{column6}{value};        # "column6": { "value": "0102030405", "name": "SS", "id": 6 },
	my $note= $trx{column16}{value};		      	  # "column16": { "value": "Příliš žluťoučký kůň úpěl ďábelské ódy. Hleď, toť čarovný je loužek kde, hedvábné štěstíčka září. Ó, náhlý déšť již zvířil prach a čilá laň.", "name": "Zpráva pro příjemce", "id": 16 },
  my $remoteAccountName= $trx{column10}{value}; # "column10": { "value": "Ing. Pithart,  Vojtěch", "name": "Název protiúčtu", "id": 10 },
  # "column12": { "value": "Fio banka, a.s.", "name": "Název banky", "id": 12 },
  # "column7": null,
  # "column9": null,
  # "column18": null,
  # "column25": { "value": "Příliš žluťoučký kůň úpěl ďábelské ódy. Hleď, toť čarovný je loužek kde, hedvábné štěstíčka září. Ó, náhlý déšť již zvířil prach a čilá laň.", "name": "Komentář", "id": 25 },
  # "column26": null,
  # "column17": { "value": 19348010455, "name": "ID pokynu", "id": 17 }

	print "DEBUG: Record$trxNumber: recordId=\"$recordId\" remoteAccount=$remoteAccountNo/$remoteBankCode \"$remoteAccountName\" amount=\"$amount\" symVariable=\"$symVariable\" symSpecific=\"$symSpecific\" symConstant=\"$symConstant\" date=\"$date\" note=\"$note\"\n" if $debug;

	$remoteAccountNo= trimZero($remoteAccountNo);

  # Typy pohybů na účtu (www.fio.cz Verze 17. 4. 2018)
  # 1. Příjem převodem uvnitř banky 21. Evidovaný úrok
  # 2. Platba převodem uvnitř banky 22. Poplatek
  # 3. Vklad pokladnou 23. Evidovaný poplatek
  # 4. Výběr pokladnou 24. Převod mezi bankovními konty (platba)
  # 5. Vklad v hotovosti 25. Převod mezi bankovními konty (příjem)
  # 6. Výběr v hotovosti 26. Neidentifikovaná platba z bankovního konta
  # 7. Platba 27. Neidentifikovaný příjem na bankovní konto
  # 8. Příjem 28. Vlastní platba z bankovního konta
  # 9. Bezhotovostní platba 29. Vlastní příjem na bankovní konto
  # 10. Bezhotovostní příjem 30. Vlastní platba pokladnou
  # 11. Platba kartou 31. Vlastní příjem pokladnou
  # 12. Bezhotovostní platba 32. Opravný pohyb
  # 13. Úrok z úvěru 33. Přijatý poplatek
  # 14. Sankční poplatek 34. Platba v jiné měně
  # 15. Posel - předání 35. Poplatek - platební karta
  # 16. Posel - příjem 36. Inkaso
  # 17. Převod uvnitř konta 37. Inkaso ve prospěch účtu
  # 18. Připsaný úrok 38. Inkaso z účtu
  # 19. Vyplacený úrok 39. Příjem inkasa z cizí banky
  # 20. Odvod daně z úroků 40. Evidovaný úrok

  my $TypPrichoziPlatby = 1; # Nova platba

  my $do_inserts= 0;

  if ($import_incremental)
  {
    $do_inserts = 1;
  }

  if ($reimport_last_week)
  {
    my $q= sprintf("SELECT * FROM PrichoziPlatba WHERE index_platby=%s", db_quote(trim($recordId)));
    my @existing= db_get($q);
    if ( not defined $existing[0] )
    {
      db_do("ROLLBACK");
      _exit(2);		# temporary error on import
    }

    unless ($existing[0])
    {
      print "MISSING record$trxNumber: recordId=\"$recordId\" remoteAccount=$remoteAccountNo/$remoteBankCode \"$remoteAccountName\" amount=\"$amount\" symVariable=\"$symVariable\" symSpecific=\"$symSpecific\" symConstant=\"$symConstant\" date=\"$date\" note=\"$note\"\n";
      ++$recordsMissing;
      $do_inserts = 1;
    }
  }

  if ($do_inserts)
  {
    my $i= sprintf "INSERT INTO PrichoziPlatba (vs, ss, datum, cislo_uctu, nazev_uctu, castka, kod_cilove_banky, index_platby,
      zprava_prijemci, TypPrichoziPlatby_id, identifikace_uzivatele, info_od_banky)
      VALUES( %s, %s, %s, %s,%s, %s, %s, %s,%s, %s, %s, %s)
      ON DUPLICATE KEY UPDATE datum = values(datum)",
      db_quote(trimZero($symVariable)), db_quote(trimZero($symSpecific)),
      db_quote(substr($date, 0, 10)),
      db_quote(trimZero($remoteAccountNo) . '/' . $remoteBankCode),
      db_quote(trim($remoteAccountName)),
      $amount,
      '2010',
      db_quote(trim($recordId)),
      db_quote(trim($note)),
      $TypPrichoziPlatby,
      db_quote(''),
      db_quote($recordType)
    ;

    my ($result)= db_do($i);

    if ( not defined $result )
    {
      db_do("ROLLBACK");
      _exit(2);		# temporary error on import
    }

    ++$recordsImported;
  	$totalDebit+= $amount if $amount < 0;
  	$totalCredit+= $amount if $amount > 0;
  }
}

if ($import_incremental)
{
  print "Done: $recordsImported record(s))";
}
if ($reimport_last_week)
{
  print "Done: imported $recordsImported out of $recordsMissing mising record(s))";
}
print ", total debit $totalDebit CZK" if $totalDebit;
print ", total credit $totalCredit CZK" if $totalCredit;
print ", FioDownloadedFiles #$bsfid.\n";

db_do(sprintf "UPDATE FioDownloadedFiles SET debit_sum=%.2f, credit_sum=%.2f WHERE id=%u", $totalDebit, $totalCredit, $bsfid);

db_do("COMMIT");
db_do("UNLOCK TABLES");
unlink "/tmp/fio2userdb_is_running";

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
  unlink "/tmp/fio2userdb_is_running";
  exit @_[0];
}
