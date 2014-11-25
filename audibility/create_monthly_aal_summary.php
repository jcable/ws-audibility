<?php

require_once("report_template_functions.php");

ob_implicit_flush(true);

// Connecting, selecting database
$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');

// allow use with no parameters
$today = new DateTime('now', new DateTimeZone('UTC'));
$prev = clone $today;
$prev->sub(new DateInterval('P1M'));

$freeze = $today->format('Y-m-d');
$year = $prev->format('Y');
$month_name = $prev->format('m');

// allow use from the command line
if(count($argv)==4)
{
  $year = $argv[1];
  $month_number = $argv[2];
  $freeze = $argv[3];
}

// normal use from a form
if(array_key_exists('year', $_REQUEST)) {
	$year = $_REQUEST["year"];
}
if(array_key_exists('month', $_REQUEST)) {
	$a = $_REQUEST["month"];
	if($a != '')
		$month_name = $a;
}
if(array_key_exists('freeze', $_REQUEST)) {
	$f = $_REQUEST["freeze"];
	if($f != '')
		$freeze = $f;
}
if(array_key_exists('date', $_REQUEST))
{
        $d = explode('-', $_REQUEST['date']);
        if( $d[0] != '')
        {
                $year = $d[0];
                $month_number = $d[1];
        }
}

$season = get_season($year, $month_name);
$month = season_month($season, $month_number);
$q = get_times($season, $month);
$start_date = $q->start_date;
$stop_date = $q->stop_date;
$month_name = $q->month_name;
$map = get_target_areas();
$regions = get_regions($map);
$title = "Create Saved AAL Report for $month_name ($season) Frozen on $freeze";
?>
<HTML>
<HEAD>
<TITLE><?php echo $title; ?></TITLE>
<link rel="stylesheet" type="text/css" href="audibility.css" />
</HEAD>
<BODY>
<?php
	print "<H1>$title</H1>"; 
	$query = "DELETE FROM aal_monthly_summaries WHERE month = '$start_date'";
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());
	$dates->start = $start_date;
	$dates->stop = $stop_date;
	$dates->freeze = $freeze;
	save_monthly_aal_summary($dbconn, $season, $dates);
	pg_close($dbconn);
	print "<BR/>Done";
?>
</BODY>
</HTML>
