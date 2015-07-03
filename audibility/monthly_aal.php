<?php

date_default_timezone_set("UTC");
require_once("report_template_functions.php");
$dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');
$dagger = "&#8224;";

if(count($argv)==2)
{
	$date = DateTime::createFromFormat('Y-m-d', $argv[1]);
}
else
{
	$d = $_GET['date'];
	$d = substr($d, 0, 8)."01";
	$date = DateTime::createFromFormat('Y-m-d', $d);
}
$year_number = $date->format("Y");
$month_number = $date->format("m");
$season = get_season($date->format("Y"), $month_number);
$month = season_month($season, $month_number);
$q = get_times($season, $month);
$title = "AAL Report for ".$date->format("F Y")." ($season)";
?>
<HTML>
<HEAD>
<TITLE><?php echo $title; ?></TITLE>
<link rel="stylesheet" type="text/css" href="audibility.css" />
</HEAD>
<BODY>
<FORM><INPUT TYPE="button" VALUE="Back" onClick="history.go(-1);return true;"></FORM>
<?php
	print "<H1>$title</H1>";
	print "Note - scores prefixed by a $dagger symbol have no nominated monitoring location for AAL measurement.<BR/>\n";
	// TODO make sure DISTINCT not required
	$query = "SELECT DISTINCT service,target_area,start_time,target,aal_score,other_score,freeze_date"
		." FROM aal_monthly_summaries WHERE month=:month"
		." ORDER BY service,target_area,start_time";
	$sth = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	$month=$q->start_date;
    	$sth->execute(array(':month' => $month));
	$scores = $sth->fetchAll();
	$ts = "";
	$ta = "";
	$service = "";
	foreach ($scores as $row) {
		if($row['service']!=$service || $row['target_area']!=$ta) {
			$service = $row['service'];
			print $ts; $ts = "</TABLE>";
			if($row['target_area']!=$ta) {
				$ta = $row['target_area'];
			}
			print "<H2>$service in area $ta</H2>";
			print "<TABLE>";
			$rs = "";
			$hh = "";
		}
		$s = $row['start_time'];
		if(substr($s, 0, 2) != $hh) {
			$hh = substr($s, 0, 2);
			print $rs; $rs = "</TR>\n";
			print "<TR>";
		}
		$score = $row['aal_score'];
		if($score == '')
			$score = $row['other_score'];
		$link = "detail_aal.php"
			."?ta=$ta"
			."&service=$service"
			."&year=$year_number"
			."&month=$month_number"
			."&slot_start=".$s
			."&target=".$row['target']
			."&score=".$score
			."&freeze=".$row['freeze_date'];
		print "<TD class='";
		$score = $row['aal_score'];
		if($score == '')
			$score = $dagger.$row['other_score'];
		if($score == $dagger)
			$score = '?';
		if($score=='?') {
			print "nodatarow";
		}
		else if($score>=$row['target']) {
			print "goodrow";
		}
		else {
			print "badrow";
		}
		print "'>";
		print "<A href='$link'>$s: $score/".$row['target']."</A>";
		print "</TD>";
	}
	print "</TR>\n</TABLE>";
?>
</BODY>
</HTML>
