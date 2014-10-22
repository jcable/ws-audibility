<?php

  require_once("report_template_functions.php");
  require_once("import_audibility_data_functions.php");
  date_default_timezone_set("UTC");
  $dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

  $match = "daily_";

  $sql = "select distinct filesource from parsed_observations where filesource like '$match%'";

$available = glob("$match*");

$parsed = array();
foreach( $dbh->query($sql) as $row) {
	$parsed[] = $row['filesource'];
}

foreach( $available as $filename) {
	if(!in_array($filename, $parsed)) {
		echo $filename." to do\n";
		import_ibb_file($dbh, $filename);
		`logger -p daemon.info IBB file $filename added to database ok`;
	}
}

$dbh->exec("VACUUM parsed_observations");

?>
