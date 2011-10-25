<HTML>
<HEAD><TITLE>WS HF Audibility Verification</TITLE></HEAD>
<BODY>
<H2>Import Monitoring Schedule</H2>
<PRE>
<?php

require_once('ciraf_lib.php');
require_once("report_template_functions.php");


$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');

/*
ID	TAB	LANG	START	STOP	FREQ	SITE	IBB DAY CODE	MON LOC	ADD DATE	PERMANENT DELETION DATE	TARGET AREA COMMENT
*/
function add_record($dbconn, $data)
{
	$query="INSERT INTO monitoring_schedule VALUES(".implode(", ", $data).")";
	$result = pg_query($query) or die('Query failed: ' . pg_last_error()." The offending line was:\n".$line);
	pg_free_result($result);
}

function cleanup_time($n)
{
  if(strlen($n)==4)
  {
	return "'".substr($n, 0, 2).":".substr($n, 2).":00'";
  }
  else
  {
	return "'0".substr($n, 0, 1).":".substr($n, 1).":00'";
  }
}

function cleanup_fields($data)
{
  $data[0] = "'".$data[0]."'";
  $data[1] = "'".$data[1]."'";
  $data[2] = "'".$data[2]."'";
  $data[3] = cleanup_time($data[3]);
  $data[4] = cleanup_time($data[4]);
  $data[5] = $data[5];
  $data[6] = "'".$data[6]."'";
  $data[7] = "'".$data[7]."'";
  $data[8] = "'".$data[8]."'";
  $data[9] = "'".$data[9]."'";
  if(strlen(trim($data[10]))==0)
    $data[10] = 'NULL';
  else
    $data[10] = "'".$data[10]."'";
  $data[11] = "'".cirafs2array($data[11])."'";
  $data[12] = "'".str_replace("'", "''", $data[12])."'";
  return $data;
}

function readcsv($f, $dbconn)
{
  $count = 0;
  while(!feof($f))
  {
    $line = fgetcsv($f, ",");
    if($line[0]=="ID") {
	$headers = $line;
	continue;
    }
    if($line[0]=="") {
	break;
    }
    $row = cleanup_fields($line);
    add_record($dbconn, $row);
    $count++;
  }
  return $count;
}

if(array_key_exists('file', $_FILES))
{
     $file = $_FILES["file"]["tmp_name"];
     $filename = $_FILES["file"]["name"];
     `mv $file import/$filename`;
     $file = "import/$filename";
}
else
{
     $file = $argv[1];
}

$query="TRUNCATE TABLE monitoring_schedule";
$result = pg_query($query) or die('Query failed: ' . pg_last_error()." The offending line was:\n".$line);

if(strlen($file)-strrpos(strtolower($file), 'xls')==3)
{
	$handle = popen("xls2csv '$file' A1:M2000", "r");
	$count = readcsv($handle, $dbconn);
	pclose($handle);
}
else
{
	$handle = fopen($file, "r");
	$count = readcsv($handle, $dbconn);
	fclose($handle);
}
print "Inserted $count rows into monitoring schedule\n";
?>
</PRE>
If you didn't see any error messages above the import of <?php echo basename($file); ?> was successful.
</BODY>
</HTML>
