<HTML>
<HEAD><TITLE>WS HF Audibility Verification</TITLE></HEAD>
<BODY>
<?php
  require_once("report_template_functions.php");

function get_string($val, $def)
{
	$r = trim($val);
	if($r == '')
		return $def;
	return $r;
}

function get_array($inp, $cols)
{
    $aout = array();
    for($i=0; $i<count($cols); $i++)
    {
	$aout[] = trim($inp[$cols[$i]]);
    }
    $out = "{".implode(",", $aout)."}";
    $out = ereg_replace(",+}", "}", $out);
    $out = ereg_replace(",+", ",", $out);
    return $out;
}

  $file = $_FILES["file"]["tmp_name"];
  $filename = $_FILES["file"]["name"];
  $season = strtoupper(substr($filename, 0, 3));
  $q = get_season_dates($season);
  $lang = "Vernaculars";
  if(strpos($filename, "WSE")>0 || strpos(strtoupper($filename), "ENGLISH")>0)
	$lang = "English";
  print "<H2>Import records for $season ($q->start_date to $q->stop_date) into Targets for $lang</H2>";

  $dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');

  $dates = season_dates($season);
  //$default_valid_from = $dates["start"];
  //$default_valid_to = $dates["end"];
  $default_valid_from = $q->start_date;
  $default_valid_to = $q->stop_date;
  pg_free_result($result);
  $query = "delete from sla where season='$season'";
  if($lang=="English")
    $query .= " and language='English'";
  else
    $query .= " and language!='English'";

  $result = pg_query($query) or die('Query failed: ' . pg_last_error());
  print "Deleted ".pg_affected_rows($result)." rows from Targets Table\n";
  pg_free_result($result);
  flush(); ob_flush();
try {
  $h=fopen($file, "r");
  if(is_resource($h))
  {
    print "<br>opened<br>\n"; flush(); ob_flush();
  } else {
    print "<br>can't open $file<br>\n"; flush(); ob_flush();
  }
} catch (Exception $e) {
  print 'Caught exception: '.$e->getMessage()."<BR>\n";
}
try {
	$line = fgetcsv($h);
	$c_pri = array();
	$c_sec = array();
	$c_ref = array();
	for($i=0; $i<count($line); $i++)
	{
		$head = strtoupper($line[$i]);
		$head = str_replace("-", "_", $head);
		$head = str_replace(" ", "_", $head);
		if($head=="TIME_1") $c_start = $i;
		if($head=="TIME_2") $c_stop = $i;
		if($head=="LANG") $c_lang = $i;
		if($head=="REGION") $c_region = $i;
		if($head=="SERVICE") $c_target_area = $i;
		if($head=="SLA") $c_sla = $i;
		if(substr($head,0,7)=="PRIMARY") $c_pri[] = $i;
		if(substr($head,0,3)=="SEC") $c_sec[] = $i;
		if($head=="DAYS_IBB_CODE") $c_days = $i;
		if(substr($head,0,12)=="DAY_REF_FREQ") $c_ref[] = $i;
		if($head=="VALID_FROM") $c_vf = $i;
		if($head=="VALID_TO") $c_vt = $i;
	}
} catch (Exception $e) {
  print 'Caught exception: '.$e->getMessage()."<BR>\n";
}
  flush(); ob_flush();
  $count = 0;
  while(!feof($h))
  {
  	$line = fgetcsv($h);
	if(!is_array($line))
		continue;
	$hh = substr($line[$c_start],0,-2);
	switch(strlen($hh))
	{
	case 0:
		$hh = "00";
		break;
	case 1:
		$hh = "0$hh";
		break;
	}
	$mm = substr($line[$c_start],-2);
	switch(strlen($mm))
	{
	case 0:
		$mm = "00";
		break;
	case 1:
		$mm = "0$mm";
		break;
	}
    $start = "$hh:$mm";
    $stop = substr($line[$c_stop],0,-2).":".substr($line[$c_stop],-2);
    $lang = trim($line[$c_lang]);
	if($lang == '')
		continue;
	if($lang == 'WSE')
	  $lang = 'English';
    $region = trim($line[$c_region]);
    $target_area = trim($line[$c_target_area]);
    $sla = $line[$c_sla];
    $pri = get_array($line, $c_pri);
    $sec = get_array($line, $c_sec);
    $ref = get_array($line, $c_ref);
    $day = get_string($line[$c_days], '1234567');
    $valid_from = get_string($line[$c_vf], $default_valid_from);
    $valid_to = get_string($line[$c_vt], $default_valid_to);
    $query = "INSERT INTO sla (";
	$query .= "season, language, region, target_area, ";
	$query .= "start_time, target, valid_from, valid_to, primary_frequency, secondary_frequency, ibb_days";
	$query .= ") VALUES (";
	$query .= "'$season', '$lang', '$region', '$target_area',";
	$query .= "'$start', $sla, '$valid_from', '$valid_to', '$pri', '$sec', '$day'";
	$query .= ")";
	flush(); ob_flush();
    $result = pg_query($query) or die('Query failed: ' . pg_last_error()."<PRE>\n".$query."</PRE>");
    pg_free_result($result);
	$count++;
  }
  fclose($h);
?>
Added appox. <?php print $count; ?> rows into the SLA table.
</BODY>
</HTML>
