<?php

  require_once("report_template_functions.php");
  date_default_timezone_set("UTC");
  $dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

// allow use from the command line
if(count($argv)==2)
{
    $file = $argv[1];
}

// allow use with a form
if(array_key_exists('file', $_FILES))
{
    $tmpname = $_FILES["file"]["tmp_name"];
    $filename = $_FILES["file"]["name"];
    $file = "import/ibb/$filename";
    `mv '$tmpname' '$file'`;
}

$dbh->exec("TRUNCATE TABLE raw_observations");
$stmt = $dbh->prepare("INSERT INTO raw_observations VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$row=0;
$handle = fopen($file, "rb");
fgets($handle);
fgets($handle);
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;
	$stmt->execute($data);
    }
fclose($handle);
$stmt = $dbh->prepare("DELETE FROM parsed_observations WHERE filesource=?");
$filename = basename($file);
$stmt->execute(array($filename));
$query = <<<EOT
INSERT INTO parsed_observations
(stn, frequency, language, date, time, month, s, d, o, row_timestamp, filesource)
SELECT DISTINCT
upper("FH") as fh, "Freq", "Language", "ObDate",
TO_TIMESTAMP("Time", 'HH24MI')::time without time zone AS t, DATE_PART('month', "ObDate") as d, "S", "D", "O",
TO_DATE("FileTime",'YYMMDDHH24MISS'),?
FROM raw_observations JOIN languages ON "Lang" = "IBB Language"
WHERE "BC" = 'BBC' AND "Freq" > 3000
ORDER BY fh, "Freq", "Language", "ObDate", t
EOT;
$stmt = $dbh->prepare($query);
$stmt->execute(array($filename));
$dbh->exec("VACUUM parsed_observations");

?>
