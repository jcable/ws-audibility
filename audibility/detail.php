<?php

require_once("report_template_functions.php");
require_once("ciraf_lib.php");

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
	print "<TH>best</TH>";
	print "</TR>";
}

function show_detail_row($line)
{
    global $freeze;
    if($line["best"]) {
        $class="usedrow";
    } else {
        $class="unusedrow";
    }
    print "<TR CLASS=\"$class\">";
    print "<TD>".$line["date"]."</TD>";
    print "<TD>".$line["time"]."</TD>";
    print "<TD>".$line["frequency"]."</TD>";
    print "<TD>".$line["stn"]."</TD>";
    print "<TD>".$line["s"]."</TD>";
    print "<TD>".$line["d"]."</TD>";
    print "<TD>".$line["o"]."</TD>";
    print "<TD TITLE=\"".$line["row_timestamp"]."\">";
    if($line["best"]) {
        print "Y";
    } else {
        print "N";
    }
    print "</TD>";
    print "</TR>\n";
}

function show_detail_score($score_to_show)
{
    global $freeze, $score;
    print "<TR><TD CLASS=\"usedrow\" align=\"right\" colspan=\"6\">Average</TD><TD>$score_to_show</TD></TR>";
    if($score!=-1)
    	print "<TR><TD CLASS=\"usedrow\" align=\"right\" colspan=\"6\">Average Frozen at $freeze</TD><TD>$score</TD></TR>";
}

function show_detail_trailer($investigation)
{
	print "</TABLE>";
}

// Connecting, selecting database
$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');
$tracconn = db_login('trac', 'TRAC_USER', 'TRAC_PASSWORD');

if(isset($_GET["freeze"])) {
	$freeze = $_GET["freeze"];
} else {
	$freeze = "";
}
$tan=$_GET["ta"] or $tan = 'Europe';
$season=$_GET["season"] or $season="A11";
$month=$_GET["month"] or $month = 1;
$language=$_GET["language"] or $language = 'English';
$start=$_GET["start"] or $start = '10:00';
$target=$_GET["target"] or $target = 3;
if(isset($_GET["score"])) {
	$score = $_GET["score"];
}

$query = "SELECT id FROM target_areas WHERE name = '$tan'";
$result = pg_query($dbconn, $query) or die('Query failed: '. pg_last_error()." for $query");
$line = pg_fetch_array($result, null, PGSQL_ASSOC);
$ta = trim($line["id"]);
pg_free_result($result);

$investigation = array(
	"ta"=>$ta, "tan" => $tan,
	"season"=>$season, "month"=>$month,
	"language"=>$language, "start"=>$start, "target"=>$target
	);

$q = get_times($season, $month);
$start_date = $q->start_date;
$stop_date = $q->stop_date;
$month_name = $q->month_name;
$title="Detail for $language in $tan ($ta) at $start during $month_name";
	
	$query = 'SELECT "IBB Language" FROM languages WHERE "Language"='."'$language'";
	$result = pg_query($dbconn, $query) or die('Query failed: ' . pg_last_error()." for $query");
	$line = pg_fetch_array($result, null, PGSQL_ASSOC);
	pg_free_result($result);
	$ibb_language = $line["IBB Language"];

 // retrieve in target observations
 $query = query_for_detail($language, $season, $tan, $start, $start_date, $stop_date, $freeze, 'T');

 $in_obs = fetch_query($query);

 $in_stations = ms_set($in_obs);

 // retrieve out of target observations
 $query = query_for_detail($language, $season, $tan, $start, $start_date, $stop_date, $freeze, 'F');
 $out_obs = fetch_query($query);

 $out_stations = ms_set($out_obs);

?>
<HTML>
<HEAD>
<TITLE><?php echo $title; ?></TITLE>
<link rel="stylesheet" type="text/css" href="audibility.css" />
<SCRIPT src="sorttable_colored.js" type="text/javascript"></SCRIPT>
</HEAD>
<BODY>
<H2><?php echo $title; ?></H2>
<TABLE border="0" width="100%">
<TR>
<TD colspan="5">
<?php
	$query = "SET datestyle = 'DMY';";
	$query .= query_for_sla($language, $season, $tan, $start, $start_date, $stop_date);
	//print $query;
    	$sla = show_html_table("Service Level", "sla_tab", $query);
	$pf = array();
	$sf = array();
	$primary_frequencies = "";
	foreach($sla as $rec) {
		$p = explode(",", substr($rec[8], 1, strlen($rec[8])-2));
		$s = explode(",", substr($rec[9], 1, strlen($rec[9])-2));
		$pf = array_merge($pf, $p);
		$sf = array_merge($sf, $p);
	}
	$primary_frequencies .= implode(",", array_unique($pf));
	$frequencies .= implode(",", array_unique(array_merge($pf,$sf)));
	$query = 'SELECT "SITE", "FREQ", "IBB DAY CODE", "MON LOC", "ADD DATE", "PERMANENT DELETION DATE"';
	$query .= ' FROM monitoring_schedule WHERE';
	$query .= ' "LANG" ='."'$ibb_language'";
	$query .= ' AND "START"<='."'$start'";
	$query .= ' AND "STOP" >='."'$start'";
	$query .= " AND '$stop_date' >= \"ADD DATE\"";
	$query .= " AND ('$start_date' <= \"PERMANENT DELETION DATE\" OR \"PERMANENT DELETION DATE\" IS NULL)";
	if($frequencies != "")
		$query .= ' AND "FREQ" IN ('.$frequencies.')';
	$query .= ' ORDER BY "FREQ", "MON LOC"';
	$result = pg_query($dbconn, $query) or die('Query failed: ' . pg_last_error()." for $query");
	$mon_sched_query = $query;

	$mon_sched = array();
	for($i=0; $i<pg_num_rows($result); $i++) {
		$mon_sched[] = pg_fetch_array($result, null, PGSQL_ASSOC);
	}
?>
</TD>
</TR>
<TR>
<TD colspan="5">
<?php
	$query = "SET datestyle = 'DMY';";
	$query .= 'SELECT DISTINCT station, frequency, start, stop, days, target_area, cirafs, bearing';
	$query .= ' FROM prism_hf_schedule h JOIN languages ON service="Net"';
	$query .= ' , polygons2d p, target_areas t';
	$query .= ' WHERE';
	$query .= ' "Language"='."'$language'";
	$query .= " AND t.name='$tan' AND intersects(p.the_geom, t.the_geom) AND p.name = ANY(h.cirafs)";
	$query .= ' AND start <='."('$start' + '00:00:30'::interval)";
	$query .= ' AND stop >='."('$start' + '00:29:30'::interval)";
	$query .= ' AND valid_from <='."'$stop_date'";
	$query .= " AND valid_to >= '$start_date'";
	$query .= " ORDER BY start";
#print "<pre>$query</pre><br>";
    	$broadcasts = show_html_table("Broadcasts", "hfsched_tab", $query);
	$cirafs = array();
	foreach($broadcasts as $bc){
		$cirafs[] = $bc[6];
	}
	switch(count($cirafs)) {
	case 0:
		$cirafs = "''";
		break;
	case 1:
		$cirafs = "'".$cirafs[0]."'";
		break;
	default:
		$cirafs = "ANY ('".implode("'::character varying[]||'", $cirafs)."'::character varying[])";
	}
	$mapurl = "ta.php?"
		. "tan=$tan"
		. "&when=".$start_date."T".$start."Z"
		. "&language=".$language;
?>
<!--
<BR><A HREF="<?php echo $mapurl; ?>">Show Target Area on a Map</A>
-->
</TD>
</TR>
<TR valign="top">
<TD>
<DIV class="tabtitle">Monitoring Stations counting towards scores</DIV>
<FORM METHOD='GET' ACTION='update_in_out.php'>
<TABLE id='ms_form_tab' class='sortable'><TR><TH>stn<TH/></TR>
<?php
	foreach($in_stations as $stn)
	{
		print "<TR><TD>$stn</TD><TD>";
		print "<input type='radio' name='status_$stn' value='T' checked/>in target";
		print "<input type='radio' name='status_$stn' value='F'/>out of target";
		print "<input type='radio' name='status_$stn' value=' '/>stop counting";
		print "</TD></TR>\n";
	}
	foreach($out_stations as $stn)
	{
		print "<TR><TD>$stn</TD><TD>";
		print "<input type='radio' name='status_$stn' value='T'/>in target";
		print "<input type='radio' name='status_$stn' value='F' checked/>out of target";
		print "<input type='radio' name='status_$stn' value=' '/>stop counting";
		print "</TD></TR>\n";
	}
?>
</TABLE>
<?php
	print "<input type='hidden' name='ta' value='$tan'/>";
	print "<input type='hidden' name='freeze' value='$freeze'/>";
	print "<input type='hidden' name='score' value='$score'/>";
	print "<input type='hidden' name='season' value='$season'/>";
	print "<input type='hidden' name='month' value='$month'/>";
	print "<input type='hidden' name='language' value='$language'/>";
	print "<input type='hidden' name='start' value='$start'/>";
	print "<input type='hidden' name='target' value='$target'/>";
	print "<input type='submit' name='submit' value='Update'/>";
?>
</FORM>
</TD>
<TD width="15%">
<?php
	$bquery = 'SELECT DISTINCT stn FROM ms, polygons2d WHERE';
	$bquery .= " polygons2d.name = $cirafs";
	$bquery .= " AND within(ms.the_geom, polygons2d.the_geom)";
#print $bquery;
    	$msba = show_html_table("Monitoring Stations In Broadcast Area", "msba_tab", $bquery);
?>
</TD>
<TD width="15%">
<?php
	$tquery = 'SELECT DISTINCT stn FROM ms, target_areas WHERE';
	$tquery .= " target_areas.name='$tan'";
	$tquery .= " AND within(ms.the_geom, target_areas.the_geom)";
    	$msta = show_html_table("Monitoring Stations In Target Area", "msta_tab", $tquery);
	$ta_in_stations = array();
	foreach($msta as $s) $ta_in_stations[] = $s[0];
?>
</TD>
<TD width="15%">
<?php
    	$ms_stations = array();
    	foreach($mon_sched as $rec) {
		$ms_stations[$rec['MON LOC']] = 1;
    	}
	$c = array();
	foreach(array_keys($ms_stations) as $s){
		$c[] = array("stn" => $s);
	}
	if(count($c)>0)
    	$msba = show_array_in_html_table("Monitoring Locations In Monitoring Schedule", "msms_tab", $c);
?>
</TD>
<TD width="15%">
<?php
	$query = 'SELECT DISTINCT stn FROM ';
	$query .= "($bquery) b EXCEPT ($tquery)";
	$ooaquery = $query;
    	$os = show_html_table("Monitoring Stations in Broadcast Area and outside Target Area", "msoa_tab", $query);
	$ba_out_stations = array();
	foreach($os as $s) $ba_out_stations[] = $s[0];
?>
</TD>
</TR>
</TABLE>
<TABLE border="0" width="100%">
<TR>
<TD width=="25%" valign=top>
<DIV class="tabtitle">In Target Observations</DIV>
<?php
 if(is_array($in_obs))
 {
   $q = "q=".rawurlencode($query)."&x=date&y=o&c=".rawurlencode("frequency,stn"); 
   // TODO make maps work echo "<img src='plot_sql.php?$q'>"; 
 }
 show_detail($investigation, $start_date, $stop_date, $month_name, $freeze, $in_obs);
 ?>
</TD>
<TD width="25%" valign="top">
<DIV class="tabtitle">Out of Target Observations</DIV>
<?php
 $s = $score; $score = -1;// hack to not show freeze for out obs
 show_detail($investigation, $start_date, $stop_date, $month_name, $freeze, $out_obs);
 $score = $s;
?>
</TD>
<TD valign="top" width="50%">
<div class='tabtitle'>Existing Investigations Records</div>
<IFRAME width="100%" height="300px" src="<?php
	print "/cgi-bin/trac.cgi/query?language=$language&target_area=$tan";
?>">
</IFRAME>
<?php
	$ticket = -1;
	$result = pg_query($tracconn,
"SELECT ticket FROM ticket_custom WHERE name='report_id' AND value='$month$season'"
	) or die('Query failed: ' . pg_last_error()." for $query");
	for($i=0; $i<pg_num_rows($result); $i++) {
		$line = pg_fetch_array($result, null, PGSQL_ASSOC);
		$t = $line['ticket'];
		$r2 = pg_query($tracconn, "SELECT name,value from ticket_custom WHERE ticket=$t");
		$ok = true;
		for($j=0; $j<pg_num_rows($r2); $j++) {
			$line = pg_fetch_array($r2, null, PGSQL_ASSOC);
			if($line['name']=='language' && $line['value'] != $language)
				$ok = false;
			if($line['name']=='target_area' && $line['value'] != $tan)
				$ok = false;
			if($line['name']=='timeslot' && $line['value'] != $start)
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
		print "/cgi-bin/trac.cgi/ticket/$ticket";
		print '">\n';
	}
	else
	{
?>
<div class='tabtitle'>Create New Investigation Record</div>
<IFRAME width="100%" height="800px" src="<?php
	print "/cgi-bin/trac.cgi/newticket";
	print "?language=$language";
	print "&target_area=$tan";
	print "&timeslot=$start";
	print "&report_id=$month$season";
	print "&summary=$language to $tan at $start during $month_name";
?>"><?php
	}
?>
</IFRAME>
</TD></TR>
<TR>
<TD colspan="3">
<?php
	if(count($mon_sched)>0)
    	show_array_in_html_table("Monitoring Schedule", "mon_tab", $mon_sched);
	else
		print "no monitoring schedule records for query $mon_sched_query";
?>
</TD>
</TR>
</TABLE>
<?php
	pg_close($dbconn);
	pg_close($tracconn);
?>
</BODY>
</HTML>
