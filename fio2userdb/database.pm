#
# common database functions
#
package database;
use Exporter 'import'; # gives you Exporter's import() method directly

our %EXPORT_TAGS = (
    standard => [qw(db_connect db_get db_get_sth db_do db_quote db_disconnect test_db last_insert_id)],
);

our @EXPORT = ( @{$EXPORT_TAGS{standard}} );
our @EXPORT_OK = ( @{$EXPORT_TAGS{standard}} );

use DBI;
use Data::Dumper;
use Time::HiRes qw(gettimeofday tv_interval);
use Sys::Syslog qw(syslog);
our $mysql_error;
our $mysql_errno;

# Logs to stdout or to syslog
sub log_info
{
  my $line= shift;
  syslog ( "info", $line );
  print "$line\n" if not $main::use_syslog;
}
sub log_error
{
  my $line= shift;
  syslog ( "err", $line );
  print STDERR "$line\n" if not $main::use_syslog;
}

sub last_error {	return $mysql_error; }
sub last_errno {	return $mysql_errno; }

# Connects the database, sets global $dbh variable and returns it.
sub db_connect
{
	# Remove "" from DBIstring
	$main::cfg{database}{DBIstring} =~ s/(^\"|\"$)//g;

	if ( not $main::cfg{database}{DBIstring} )
	{
		my $msg= "Database: No connection configured - cfg{database}{DBIstring} is empty";
		log_info( $msg );
		undef $dbh;
		$mysql_error= $msg;
		return (undef,$msg);
	}

  $dbh = DBI->connect($main::cfg{database}{DBIstring},
                      $main::cfg{database}{db_user},
                      $main::cfg{database}{db_password},
                      { RaiseError => 0, AutoCommit => 0 });

  if ( $dbh )
  {
    #log_info "Database: connected ($main::cfg{database}{DBIstring})";

    $db_last_action= time;
    $db_last_keepalive_val= 0;

    my $logging_tmp= $main::cfg{logging}{database};
    $main::cfg{logging}{database}= 0;			# Nechci techto 6 query videt furt dokola v logu

    db_do ( "SET character_set_connection=utf8" );
    db_do ( "SET character_set_results=utf8" );
    db_do ( "SET character_set_client=utf8" );
    db_do ( "SET character_set_results=utf8" );
    db_do ( "SET character_set_database=utf8" );

    $main::cfg{logging}{database}= $logging_tmp;	# Pokud bylo logovani zapnute, obnovim to

    return $dbh;
  }
  else
  {
    log_error "Database: NOT connected (". $DBI::errstr .")";
    undef $dbh;
    return undef;
  }
}

# Disconnects the database
sub db_disconnect
{
  $dbh->disconnect() if defined $dbh;
  undef $dbh;
}


# Performs database query
# same function as db_get but no return value
# (use it for non-select queries: update, insert, drop etc.)
sub db_do
{
  return db_get(@_,1);
}

# db_get for multiple rows - returns $sth
# returns ($sth,$num_rows)
sub db_get_sth
{
  return db_get(@_,0,0,1);
}

# Returns array of first row fetchd from given SELECT
# (consider adding "LIMIT 1" in your query)
#
# @row= db_get( $query, $nofetch, $noexit )
# @row= db_get( "SELECT a,b,c FROM xyz LIMIT 1", 0, 1)
# my ($a,$b,$c)= db_get "select a, b, c from table where x=1 limit 1";
#  $nofetch ... set to 1 for INSERT queries. Then, auto_increment_id is returned is 1-item array
#  $noexit .... does not exit() while program on mysql error, but return undef and set $mysql_error
#
# Returns - undef on error
#         - one empty string ("") on empty result
# Sets - global variable $mysql_error
# On error, exits whole program -- calls exit_error() (but: see $noexit)
sub db_get
{
  my $q= shift;			# Query to execute
  my $nofetch= shift;		# used by db_do() - return last auto_increment and don't fetch rows
  my $noexit= shift;		# don't exit on mysql error
  my $return_sth= shift || 0;	# used by db_get_sth()
  my @return_values= undef;	# For returning value(s)
  my $round= 1;			# Counter of (re)try
#  my $max_retries= 3;		# Maximum attempts to repeat query before returning undef
#  my $retry_delay= 2;		# Sleep (sec) after database error (keep it short)
  my $sth;
  my $time_db= 0;		# Time spent in this function
  my $warn_msg= "";		# For stdout/syslog in case of failure
  my $affected;			# For number of affected rows (non-select queries by db_do)
  $mysql_error= undef;
  $mysql_errno= undef;

  log_info "database SQL [$q]" if $main::cfg{logging}{database};

  # Perform the real query on global $dbh
  my $t_db0= [gettimeofday];

  retry:
  if ( not $dbh ) {
    # Try to re-connect
    ($dbh,$error_info)= db_connect();
    $mysql_error= $error_info;
    return if not defined $dbh;
  }

  $sth= $dbh->prepare( $q );
  if ( not $sth )
  {
    log_error "database error [$q]: ".$dbh->err.": ". $dbh->errstr ." (prepare)";
    goto return_it;
  }
  if ( not ($affected= $sth->execute()) )
  {
    $mysql_error= $DBI::errstr;
    $mysql_errno= $DBI::err;
    log_error "database error [$q]: $mysql_errno:$mysql_error (execute)";

    # Good-morning bug?
    if ( $mysql_errno == 2006 )         # Server has gone away
	{
      # Log this even if cfg{debug}{database} is off:
      log_error "database error [$q]: $mysql_errno:$mysql_error (execute), trying to re-connect";
      db_disconnect();
      undef $dbh;
      sleep 1;
      goto retry;                       # Connect attempt is there
    }
    goto return_sth if $return_sth;
    goto return_it;
  }
  $mysql_error= $DBI::errstr;

  if ( $nofetch )
  {
    # non-SELECT
    @return_values= ($sth->{mysql_insertid});
  }
  else
  {
    if ( $return_sth == 1 )
    {
      return_sth:
      $time_db= int(100000*tv_interval ( $t_db0 ))/100; #miliseconds
      $t_db+= tv_interval ( $t_db0 ) if defined $t_db;
      log_info "database [$q]: num_rows=$affected time: $time_db ms $mysql_error" if $main::cfg{logging}{database};
      return ($sth,$affected);
    }
    else {
      # SELECT
      @return_values= $sth->fetchrow_array;
      # Bug 163: can't return undef (means: db error) if there is NULL in db
      if ( @return_values and not defined $return_values[0] ) {
        $return_values[0]= "";
      }
    }
  }
  $db_last_action= time;

  return_it:
  $time_db= int(100000*tv_interval ( $t_db0 ))/100; #miliseconds
  log_info "database [$q]: result: (@return_values) time: $time_db ms $mysql_error" if $main::cfg{logging}{database};
  if ( $mysql_error )
  {
    if ( $noexit )
    {
      return (undef);
    }
    my $t_db0= [gettimeofday];				# time measurement
    $dbh->rollback();
    $time_db= int(100000*tv_interval ( $t_db0 ))/100;	# time measurement - miliseconds
    log_info "database [ROLLBACK]: result: (@return_values) time: $time_db ms" if $main::cfg{logging}{database};
    return (undef);
  }
  return "" if not @return_values;
  return @return_values;
}

sub last_insert_id
{
	return $dbh->last_insert_id( undef, undef, undef, undef );
}

# Drops all tables as specified in parameters
# example: drop_tables("table1", "table2");
sub drop_tables
{
  my $table;
  while ( $table= shift )
  {
    log_info "Removing table [$table]";
    db_do( "DROP TABLE IF EXISTS $table" );
  }
}

# $db->quote() wrapper
sub db_quote
{
  if ( not $dbh ) {
    # Try to connect
    db_connect() or return;
  }

  return $dbh->quote( @_ );
}
1;
