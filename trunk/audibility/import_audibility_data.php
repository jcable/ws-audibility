<?php

  require_once("report_template_functions.php");
  require_once("import_audibility_data_functions.php");
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

$stmt = $dbh->prepare("DELETE FROM parsed_observations WHERE filesource=?");
$stmt->execute(array(basename($file)));

import_ibb_file($dbh, $file);

$dbh->exec("VACUUM parsed_observations");

?>
