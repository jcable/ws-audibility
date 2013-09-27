<?php

date_default_timezone_set("UTC");
require_once("report_template_functions.php");
$dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

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
$season = get_season($date->format("Y"), $date->format("m"));
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
	$sql = "SELECT DISTINCT * FROM aal_bsr ORDER BY \"Service\", \"Target Area\", \"Start\"";
	$ta=""; $service="";
	print "<TABLE>";
	$step = new DateInterval("PT30M");
	$query = "SELECT target,score,freeze_date FROM aal_monthly_summaries WHERE start_time=? AND target_area=? AND service=? AND month=?";
	$sth = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	foreach ($dbh->query($sql) as $row) {
		if($row['Service']!=$service || $row['Target Area']!=$ta) {
			print "</TABLE>";
			print "<H2>".$row['Service'];
			if($row['Network']!=$row['Target Area']) {
				print " ".$row['Target Area'];
			}
			print "</H2>";
			print "<TABLE>";
			$service = $row['Service'];
			$ta = $row['Target Area'];
		}
		$start_text = $row['Start'];
		$end_text = $row['End'];
		$start = DateTime::createFromFormat('G:i:sO', $start_text);
		$end = DateTime::createFromFormat('G:i:sO', $end_text);
		$month=$date->format('Y-m-d');
		$row = array();
		for($t = clone $start; $t<$end; $t->add($step)) {
			$s = $t->format("G:i:s");
			$p = array($s, $ta, $service, $month);
			$sth->execute($p);
			$scores = $sth->fetchAll();
			$row[$s] = $scores[0];
		}
		print "<TR>";
		foreach($row as $s => $v) {
			$link = "detail_aal.php"
				."?ta=$ta"
				."&service=$service"
				."&month=$month"
				."&service_start=".$start->format("G:i:s")
				."&slot_start=$s"
				."&target=".$v['target']
				."&score=".$v['score']
				."&freeze=".$v['freeze_date'];
			print "<TH><A href='".$link."'>$s</A></TH>";
		}
		print "</TR>\n";
		print "<TR>";
		foreach($row as $s => $v) {
			print "<TD class='";
			if($v['score']>=$v['target']) {
				print "goodrow";
			}
			else {
				print "badrow";
			}
			print "'>".$v['score']."/".$v['target']."</TD>";
		}
		print "</TR><TR></TR>\n";
	}
	print "</TABLE>";
?>
</BODY>
</HTML>
