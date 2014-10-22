<?php

	function import_ibb_file($dbh, $file) {
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
	$filename = basename($file);
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
	return $stmt->execute(array($filename));
}
?>
