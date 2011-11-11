<?php

require_once("report_template_functions.php");

ob_implicit_flush(true);

// Connecting, selecting database
$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');

$freeze = gmdate('Y-m-d');
$year = gmdate('Y');
$month_name = gmdate('m');

if(count($argv)==4)
{
  $year = $argv[1];
  $month_name = $argv[2];
  $freeze = $argv[3];
}

if(isset($_GET["year"])) {
	$year = $_GET["year"];
}
if(isset($_GET["month"])) {
	$month_name = $_GET["month"];
}
if(isset($_GET["freeze"])) {
	$freeze = $_GET["freeze"];
}
if(array_key_exists('date', $_GET))
{
        $d = explode('-', $_GET['date']);
        if( $d[0] != '')
        {
                $year = $d[0];
                $month_name = $d[1];
        }
}

$season = get_season($year, $month_name);
$month = season_month($season, $month_name);
$q = get_times($season, $month);
$start_date = $q->start_date;
$stop_date = $q->stop_date;
$month_name = $q->month_name;
$map = get_target_areas();
$regions = get_regions($map);
$title = "Create Saved Audibility Report for $month_name ($season) Frozen on $freeze";
?>
<HTML>
<HEAD>
<TITLE><?php echo $title; ?></TITLE>
<link rel="stylesheet" type="text/css" href="audibility.css" />
</HEAD>
<BODY>
<?php
	print "<H1>$title</H1>"; 
	$query = "DELETE FROM saved_monthly_summaries WHERE month = '$start_date'";
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());
	$dates->start = $start_date;
	$dates->stop = $stop_date;
	$dates->freeze = $freeze;
	save_monthly_summary($dbconn, $season, $dates);
	pg_close($dbconn);
?>
</BODY>
</HTML>
