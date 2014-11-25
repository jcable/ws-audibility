<?php

  require_once("report_template_functions.php");
  require_once("import_audibility_data_functions.php");
  date_default_timezone_set("UTC");
  $dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

  $match = "daily_";

  $sql = "select distinct filesource from parsed_observations where filesource like '$match%'";

echo "found imported filenames\n";
$available = glob("/var/www/html/audibility/import/ibb/$match*");

$parsed = array();
foreach( $dbh->query($sql) as $row) {
	$p = trim($row['filesource']);
	$parsed[] = $p;
	echo "$p\n";
}
echo "found available filenames\n";

foreach( $available as $path) {
	$filename = trim(basename($path));
	echo "$filename\n";
	if(!in_array($filename, $parsed)) {
		echo "importing $filename\n";
		import_ibb_file($dbh, $path);
		`logger -p daemon.info IBB file $filename added to database ok`;
	}
}
echo "done\n";

$dbh->exec("VACUUM parsed_observations");

?>
