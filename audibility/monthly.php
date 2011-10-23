<?php

require_once("report_template_functions.php");

function show_language_header($region_name, $lang)
{
	print "<H3>$lang</H3>";
	print "<TABLE><TR valign='top'>";
}

function show_language_trailer($icons)
{
	print "</TR></TABLE>";
}

function show_language_target_area_header($region_name, $lang, $ta, $map)
{
	print "<TD>";
	print "<H4>$ta</H4>";
	print "<TABLE border=1>";
	print "<TR><TH>Start</TH><TH>Target</TH><TH>In</TH><TH>Out</TH><TH>Last</TH><TH>*</TR></TR>\n";
}

function show_language_target_area_trailer($icons)
{
	print "</TABLE>";
	print "</TD>";
}

function show_row($start, $s, $class, $url)
{
	print "<TR CLASS=\"$class\">";
	print "<TD>$start</TD>";
	print "<TD>".$s["target"]."</TD>";
	print "<TD>";
	print '<A HREF="'.htmlentities($url).'">';
	if(is_numeric($s["avg"])) {
		print $s["avg"];
	}
	else
		print "#";
	print "</A>";
	print "</TD>";
	print "<TD>";
	/*
	if(is_numeric($s["avg"]) && $s["avg"] >= $s["target"]) {
		print "&nbsp;";
	} else {
		if(is_numeric($s["out_avg"])) {
			print $s["out_avg"];
		} else {
			print "&nbsp;";
		}
	}
	*/
	if(is_numeric($s["out_avg"])) {
		print $s["out_avg"];
	} else {
		print "&nbsp;";
	}
	print "</TD>";
	print "<TD>";
	if(is_numeric($s["pavg"])) {
		print $s["pavg"];
	} else {
		if(is_numeric($s["pout_avg"])) {
			print $s["pout_avg"];
		} else {
			print "&nbsp;";
		}
	}
	print "</TD>";
	print "<TD>";
	# trac uses mixed case
	$action = strtolower($s["action"]);
	if($action == 'investigate') {
		print "I";
	} else if($action == 'request monitoring') {
		print "R";
	} else if($action == 'investigate lack of observations') {
		print "O";
	} else if($action == 'ignore') {
		print "-";
	} else if(is_numeric($s["avg"]) && ($s["target"]-$s["avg"])>=0.6) {
		print "*";
	}
	print "</TD>";
	print "</TR>";
	return array();
}

// Connecting, selecting database
$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');
$tracconn = db_login('trac', 'TRAC_USER', 'TRAC_PASSWORD');

$freeze = gmdate('Y-m-d');
$year = gmdate('Y');
$month_name = gmdate('m');

if(isset($_GET["freeze"])) {
	$freeze = $_GET["freeze"];
}
if(array_key_exists('year', $_GET))
	$year = $_GET["year"];
if(array_key_exists('month', $_GET))
	$month_name = $_GET["month"];
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
$other = last_month($start_date, $month, $season);
$map = get_target_areas();
$regions = get_regions();
$title = "Audibility Report for $month_name ($season)";
?>
<HTML>
<HEAD>
<!--<meta http-equiv="refresh" content="120" />-->
<TITLE><?php echo $title; ?></TITLE>
<link rel="stylesheet" type="text/css" href="audibility.css" />
</HEAD>
<BODY>
<?php
	print "<H1>$title</H1>";

	$investigations = get_investigations($tracconn, $month, $season, $map);

	$dates->start = $start_date;
	$dates->stop = $stop_date;
	$dates->freeze = $freeze;
	$prev = last_month($start_date, $month, $season);
	$q = get_times($other["season"], $other["month"]);
	$pdates->start = $q->start_date;
	$pdates->stop = $q->stop_date;
	foreach ($regions as $r)
	{
		print "<H2>".$r->name."</H2>";
		//$scores = create_region_summary($r, $season, $dates, $map);
    		$scores = array();
		fetch_region_summary($dbconn, $scores, $r, $season, $dates, $map, "current");
		fetch_region_summary($dbconn, $scores, $r, $season, $pdates, $map, "previous");
		foreach($investigations as $inv) {
			$ta_name = $inv["ta_name"];
			if(isset($scores[$inv["language"]][$ta_name][$inv["start"]]))
				$scores[$inv["language"]][$ta_name][$inv["start"]]["action"] = $inv["action"];
		}
		show_region_summary($scores, $r->name, $season, $month, $start_date, $stop_date, $map);
	}
	pg_close($dbconn);
?>
</BODY>
</HTML>
