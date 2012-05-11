<HTML>
<HEAD><TITLE>WS HF Audibility Verification</TITLE></HEAD>
<BODY>
<?php

  require_once('ciraf_lib.php');
  require_once('report_template_functions.php');


  // allow use with no parameters
  $file = "import/hf.txt";

  // allow use from the command line
  if(count($argv)==2)
  {
    $file = $argv[1];
  }

  // allow use with a form
  if(array_key_exists('file', $_FILES))
  {
     $file = $_FILES["file"]["tmp_name"];
     $filename = $_FILES["file"]["name"];
     `mv $file import/$filename`;
     $file = "import/$filename";
  }

  $dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

try {  

  if (($handle = fopen($file, "r")) !== FALSE) {
    $dbh->exec('CREATE TEMPORARY TABLE import (LIKE prism_hf_schedule)');
    $dbh->beginTransaction();
    $sth = $dbh->prepare("insert into import (valid_from, valid_to, start, stop, days, service, frequency, bearing, power, station, antenna_array, target_area, cirafs, sender, smds_receiver_group) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $n=0;
    while (($data = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
        $data[12] = cirafs2array($data[12]);
	if($data[13]=='')
		$data[13]=0;
	$sth->execute($data);
	$n++;
    }
    fclose($handle);
    print "read $n records from $file<br>\n";
    $dbh->exec('CREATE TEMPORARY TABLE r (f date, t date, sender integer)');
    $dbh->exec('INSERT INTO r SELECT min(valid_from), max(valid_from), sender FROM import GROUP BY sender');
    $n = $dbh->exec('DELETE FROM prism_hf_schedule s USING r WHERE s.sender = r.sender AND valid_from >= r.f AND valid_from <= r.t');
    print "deleted $n records from prism_hf_schedule<br>\n";
    $n = $dbh->exec('INSERT INTO prism_hf_schedule SELECT * FROM import');
    print "inserted $n records into prism_hf_schedule<br>\n";
    $dbh->commit();
  }
} catch (Exception $e) {
  $dbh->rollBack();
  echo "Failed: " . $e->getMessage();
}
  $dbh = null;
?>
</BODY>
</HTML>
