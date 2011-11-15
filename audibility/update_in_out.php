<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<HTML>
<HEAD>
<TITLE>Update Monitoring Stations</TITLE>
</HEAD>
<BODY>
<?php
require_once('report_template_functions.php');
$stns = array();
foreach($_REQUEST as $key => $value)
{
  list($key1, $stn) = explode("_", $key);
  if($key1 == 'status')
  {
    $stns[$stn] = $value;
  }
  if($key == 'ta')
  {
    $ta = $value;
  }
  if($key == 'freeze')
  {
    $freeze = $value;
  }
  if($key == 'score')
  {
    $score = $value;
  }
  if($key == 'language')
  {
    $language = $value;
  }
  if($key == 'start')
  {
    $start = $value;
  }
  if($key == 'target')
  {
    $target = $value;
  }
  if($key == 'date')
  {
    $date = $value;
  }
}
  $url = make_detail_url($language, $ta, $start, $freeze, $score, $target, $date);
$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');
print "Setting the following monitoring station usage;<br>\n";
foreach($stns as $stn => $value) {
        switch($value){
       case 'T':
	print "$stn will count towards in target scores for $language in $ta<br>\n";
	break;
       case 'F':
	print "$stn will count towards out of target scores for $language in $ta<br>\n";
	break;
       case ' ':
	print "$stn will not count towards scores for $language in $ta<br>\n";
	break;
       }
	$query = "UPDATE ms_use SET status = '$value' WHERE language='$language' AND target_area='$ta' AND stn='$stn'";
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());
	pg_free_result($result);
}
pg_close($dbconn);
print "</PRE>\n";
print '<BUTTON onClick="document.location=\''.$url.'\'">Return</BUTTON>';

?>
</BODY>
</HTML>
