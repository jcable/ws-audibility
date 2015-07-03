<?php

require_once("report_template_functions.php");

function ms_set($obs)
{
 $s = array();
 foreach($obs as $ob) {
	$stn = $ob['stn'];
	$s[$stn] = 1;
 }
 return array_keys($s);
}

function show_detail_header($investigation, $start_date, $stop_date, $month_name)
{
	print "<TABLE id=\"obs_tab\" class=\"sortable\">";
	print "<TR>";
	print "<TH>day</TH>";
	print "<TH>time</TH>";
	print "<TH>frequency</TH>";
	print "<TH>stn</TH>";
	print "<TH>s</TH>";
	print "<TH>d</TH>";
	print "<TH>o</TH>";
	print "<TH>aal</TH>";
	print "<TH>aal nominated</TH>";
	print "</TR>";
}

function show_detail_row($line)
{
    global $freeze;
    if($line["aal"]=="t") {
        $class="usedrow";
    } else {
        $class="unusedrow";
    }
    print "<TR CLASS=\"$class\"".$line["row_timestamp"].">";
    print "<TD>".$line["date"]."</TD>";
    print "<TD>".$line["time"]."</TD>";
    print "<TD>".$line["frequency"]."</TD>";
    print "<TD>".$line["stn"]."</TD>";
    print "<TD>".$line["s"]."</TD>";
    print "<TD>".$line["d"]."</TD>";
    print "<TD>".$line["o"]."</TD>";
    print "<TD>".(20*$line["o"])."/".$line["target"]."</TD>";
    print "<TD>";
    if($line["aal"]=="t") {
        print "Y";
    } else {
        print "N";
    }
    print "</TD>";
    print "</TR>\n";
    return 1;
}

function show_detail_score($score_to_show)
{
    global $freeze, $score;
    if($score_to_show!=-1) {
        print "<TR><TD CLASS=\"usedrow\" align=\"right\" colspan=\"6\">Average</TD><TD>$score_to_show</TD></TR>";
    }
    if($score!=-1) {
    	print "<TR><TD CLASS=\"usedrow\" align=\"right\" colspan=\"6\">Average Frozen at $freeze</TD><TD>$score</TD></TR>";
    }
}

function show_detail_trailer($investigation)
{
	print "</TABLE>";
}

// Connecting, selecting database
$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');

if(isset($_GET["freeze"])) {
	$freeze = $_GET["freeze"];
} else {
	$freeze = "";
}
$ta=$_GET["ta"] or $ta = 'ARABA_GULF';
$service=$_GET["service"] or $service= 'Arabic';
$slot_start=$_GET["slot_start"] or $slot_start = '03:00:00';
$target=$_GET["target"] or $target = 66;
$score=$_GET["score"] or $score = 0;
$year_number=$_GET["year"] or $year_number = 2014;
$month_number=$_GET["month"] or $month_number = 11;
$season = get_season($year_number, $month_number);
$month = season_month($season, $month_number);

$investigation = array(
	"ta"=>$ta, "tan" => $ta,
	"season"=>$season, "month"=>$month,
	"language"=>$service, "start"=>$slot_start, "target"=>$target
	);

$q = get_times($season, $month);
$start_date = $q->start_date;
$stop_date = $q->stop_date;
$month_name = $q->month_name;
$title="Detail for $service in $ta at $slot_start during $month_name";
if($service == "WS English")
	$language = "English";
else
	$language = $service;
	
 // retrieve in target observations
 $query = query_for_detail_aal($service, $ta, $slot_start, $start_date, $stop_date, $freeze);

 $obs = fetch_query($query);

?>
<HTML>
<HEAD>
<TITLE><?php echo $title; ?></TITLE>
<link rel="stylesheet" type="text/css" href="audibility.css" />
<SCRIPT src="sorttable_colored.js" type="text/javascript"></SCRIPT>
</HEAD>
<BODY>
<TABLE width="100%">
<TR>
<TD>
<H2><?php echo $title; ?></H2>
</TD>
<TD align="right">
<FORM action="monthly_aal.php" method="get">
<INPUT type="submit" value="Return to Monthly Summary" />
<INPUT type="hidden" name="date" value="<?php echo $date; ?>" />
</FORM>
</TD>
</TR>
</TABLE>
<TABLE border="0" width="100%">
<TR>
<TD colspan="5">
<?php
	$query = "SET datestyle = 'DMY';";
	$query .= query_for_aal($service, $season, $ta, $slot_start);
    	$sla = show_html_table("Service Level", "aal_tab", $query);
	$network = $sla[0][9];
?>
</TD>
</TR>
<TR>
<TD colspan="3">
<?php
	$query = "SET datestyle = 'DMY';";
	$query .= 'SELECT DISTINCT site, frequency, start_time, stop_time, days, target, bearing';
	$query .= ' FROM hf_schedule';
	$query .= ' WHERE';
	$query .= " language = '$language'";
	$query .= " AND target='$ta'";
	$query .= ' AND start_time <='."('$slot_start' + '00:00:30'::interval)";
	$query .= ' AND stop_time >='."('$slot_start' + '00:29:30'::interval)";
	$query .= ' AND valid_from <='."'$stop_date'";
	$query .= " AND valid_to >= '$start_date'";
	$query .= " ORDER BY start_time";
    	$broadcasts = show_html_table("Broadcasts", "hfsched_tab", $query);
?>
</TD>
<TD></TD>
<TD colspan="2">
<?php
	$mon_sched_query = "SELECT start, town, service, aal_stn, other_stn FROM aal_mon"
		." WHERE season='$season'"
		." AND '$language' = ANY(language)"
		." AND target_area = '$ta'"
		.' AND start::time >='."'$slot_start'"
		.' AND start::time <='."('$slot_start' + '00:29:30'::interval)";
	$result = pg_query($dbconn, $mon_sched_query) or die('Query failed: ' . pg_last_error()." for $mon_sched_query");
	$mon_sched = array();
	for($i=0; $i<pg_num_rows($result); $i++) {
		$mon_sched[] = pg_fetch_array($result, null, PGSQL_ASSOC);
	}
	if(count($mon_sched)>0)
    	show_array_in_html_table("Monitoring Schedule", "mon_tab", $mon_sched);
	else
	{
		//print "no monitoring schedule records for query $mon_sched_query";
	}
?>
</TD>
</TR>
</TABLE>
<TABLE border="0" width="100%">
<TR>
<TD width=="25%" valign=top>
<DIV class="tabtitle">Observations</DIV>
<?php
 if(is_array($obs))
 {
   $q = "q=".rawurlencode($query)."&x=date&y=o&c=".rawurlencode("frequency,stn"); 
   // TODO make maps work echo "<img src='plot_sql.php?$q'>"; 
 }
 show_detail_aal($investigation, $start_date, $stop_date, $month_name, $freeze, $obs);
 ?>
</TD>
<TD valign="top" width="50%">
<div class='tabtitle'>Existing Investigations Records</div>
<IFRAME width="100%" height="300px" src="<?php
	print "/trac/query?language=$service&target_area=$ta";
?>">
</IFRAME>
<?php
	$ticket = -1;
	$result = pg_query($dbconn,
"SELECT ticket FROM ticket_custom WHERE name='report_id' AND value='$month$season'"
	) or die('Query failed: ' . pg_last_error()." for $query");
	for($i=0; $i<pg_num_rows($result); $i++) {
		$line = pg_fetch_array($result, null, PGSQL_ASSOC);
		$t = $line['ticket'];
		$r2 = pg_query($dbconn, "SELECT name,value from ticket_custom WHERE ticket=$t");
		$ok = true;
		for($j=0; $j<pg_num_rows($r2); $j++) {
			$line = pg_fetch_array($r2, null, PGSQL_ASSOC);
			if($line['name']=='language' && $line['value'] != $service)
				$ok = false;
			if($line['name']=='target_area' && $line['value'] != $ta)
				$ok = false;
			if($line['name']=='timeslot' && $line['value'] != $slot_start)
				$ok = false;
		}
		if($ok)
			$ticket = $t;
	}
	pg_free_result($result);
	if($ticket != -1)
	{
		print "<div class='tabtitle'>Investigation Record</div>\n";
		print '<IFRAME width="100%" height="800px" src="';
		print "/trac/ticket/$ticket";
		print '">\n';
	}
	else
	{
?>
<div class='tabtitle'>Create New Investigation Record</div>
<IFRAME width="100%" height="800px" src="<?php
	print "/trac/newticket";
	print "?language=$service";
	print "&target_area=$ta";
	print "&timeslot=$slot_start";
	print "&report_id=$month$season";
	print "&summary=$service to $ta at $slot_start during $month_name";
?>"><?php
	}
?>
</IFRAME>
</TD></TR>
<TR>
<TD colspan="3">
<?php
?>
</TD>
</TR>
</TABLE>
<?php
	pg_close($dbconn);
?>
</BODY>
</HTML>
