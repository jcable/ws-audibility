<HTML>
<HEAD><TITLE>WS HF Audibility Verification</TITLE></HEAD>
<BODY>
<?php

  require_once('ciraf_lib.php');

  if(array_key_exists('file', $_FILES))
  {
     $file = $_FILES["file"]["tmp_name"];
     $filename = $_FILES["file"]["name"];
     `mv $file import/$filename`;
     $file = "import/$filename";
  }
  else
  {
     $file = "import/hf.txt";
  }
try {
    $dbh = new PDO('pgsql:host=localhost;dbname=wsdata', 'www', 'www');
} catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "<br/>";
    die();
}
try {  
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if (($handle = fopen($file, "r")) !== FALSE) {
    $dbh->beginTransaction();
    $dbh->exec('CREATE TEMPORARY TABLE import (LIKE prism_hf_schedule)');
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $data[12] = cirafs2array($data[12]);
        $query = "insert into import (valid_from, valid_to, start, stop, days, service, frequency, bearing, power, station, antenna_array, target_area, cirafs, sender, smds_receiver_group) values ('".implode("','", $data)."')";
        $dbh->exec($query);
    }
    fclose($handle);
    $dbh->exec('CREATE TEMPORARY TABLE r (f date, t date)');
    $dbh->exec('INSERT INTO r SELECT min(valid_from), max(valid_from) FROM import');
    $dbh->exec('DELETE FROM prism_hf_schedule USING r WHERE valid_from >= r.f AND valid_from <= r.t');
    $dbh->exec('INSERT INTO prism_hf_schedule SELECT * FROM import');
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
