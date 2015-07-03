<HTML>
<HEAD><TITLE>WS HF Audibility Verification</TITLE></HEAD>
<BODY>
<?php

  require_once("report_template_functions.php");
  set_include_path(get_include_path() . PATH_SEPARATOR . '../phpMyAdmin/libraries');
  require_once('PHPExcel/PHPExcel.php'); // found when you download the PHPExcel  

function importfile($dbh, $file, $season) {
  $Reader = PHPExcel_IOFactory::createReaderForFile($file);  
  $Reader->setReadDataOnly(true); // set this, to not read all excel properties, just data 
  $Reader->setLoadSheetsOnly("Shortwave"); 
  $objXLS = $Reader->load($file);
  $objWorksheet = $objXLS->getActiveSheet();
  $highestRow = $objWorksheet->getHighestRow(); // e.g. 10
  $highestColumn = $objWorksheet->getHighestColumn(); // e.g 'F'
  $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn); // e.g. 5

  $insert_query = 'INSERT INTO "aal_mon" ("season","Network","Start","Target Area","Station","aal") VALUES (?,?,?,?,?,?)';
  $stmt = $dbh->prepare($insert_query);


  $count=0;
  for ($row = 3; $row <= $highestRow; ++$row) {
    $data = get_row($objWorksheet, $row);
    if(count($data['aal_mon'])>0 && strlen($data['bsr']['lang'])>0) {
	foreach($data['aal_mon'] as $stn) {
          $result = $stmt->execute(array($season,$data['bsr']['network'], $data['bsr']['start'],$data['bsr']['target_area'],$stn,1));
          $count += $stmt->rowCount();
        }
    }
    if(count($data['other_mon'])>0 && strlen($data['bsr']['lang'])>0) {
	foreach($data['other_mon'] as $stn) {
          $result = $stmt->execute(array($season,$data['bsr']['network'], $data['bsr']['start'],$data['bsr']['target_area'],$stn,0));
          $count += $stmt->rowCount();
        }
    }
  }

  $objXLS->disconnectWorksheets();  
  unset($objXLS); 
  return $count;
}

function get_string($val, $def)
{
	$r = trim($val);
	if($r == '')
		return $def;
	return $r;
}

function get_row($sheet, $row)
{
    $bsr = array();
    $bsr['lang'] = trim($sheet->getCellByColumnAndRow(0, $row)->getValue());
    $bsr['grade'] = trim($sheet->getCellByColumnAndRow(1, $row)->getValue());
    $bsr['start'] = PHPExcel_Style_NumberFormat::toFormattedString($sheet->getCellByColumnAndRow(2, $row)->getValue(), "HH:MM:SS");
    $bsr['end'] = PHPExcel_Style_NumberFormat::toFormattedString($sheet->getCellByColumnAndRow(3, $row)->getValue(), "HH:MM:SS");
    $bsr['dow'] = strtoupper(trim($sheet->getCellByColumnAndRow(4, $row)->getValue()));
    $bsr['target_area'] = trim($sheet->getCellByColumnAndRow(8, $row)->getValue());
    $bsr['network'] = trim($sheet->getCellByColumnAndRow(9, $row)->getValue());
    $tx = array();
    $tx['frequency'] = trim($sheet->getCellByColumnAndRow(11, $row)->getValue());
    $tx['start'] = PHPExcel_Style_NumberFormat::toFormattedString($sheet->getCellByColumnAndRow(12, $row)->getValue(), "HH:MM:SS");
    $tx['end'] = PHPExcel_Style_NumberFormat::toFormattedString($sheet->getCellByColumnAndRow(13, $row)->getValue(), "HH:MM:SS");
    $tx['dow'] = strtoupper(trim($sheet->getCellByColumnAndRow(14, $row)->getValue()));
    $tx['site'] = trim($sheet->getCellByColumnAndRow(15, $row)->getValue());
    $tx['power'] = trim($sheet->getCellByColumnAndRow(16, $row)->getValue());
    $tx['azimuth'] = trim($sheet->getCellByColumnAndRow(17, $row)->getValue());
    $tx['lang'] = trim($sheet->getCellByColumnAndRow(18, $row)->getValue());
    $tx['target_area'] = trim($sheet->getCellByColumnAndRow(19, $row)->getValue());
    $tx['ciraf'] = trim($sheet->getCellByColumnAndRow(20, $row)->getValue());
    $tx['antenna_type'] = trim($sheet->getCellByColumnAndRow(21, $row)->getValue());
    $mon = array();
    $mon['aal'] = trim($sheet->getCellByColumnAndRow(22, $row)->getValue());
    $stn = array();
    for($i=23; $i<=31; $i++) {
        $s = trim($sheet->getCellByColumnAndRow($i, $row)->getValue());
	if(strlen($s)>0) $stn[] = $s;
    }
    $stn2 = array();
    for($i=32; $i<=37; $i++) {
        $s = trim($sheet->getCellByColumnAndRow($i, $row)->getValue());
	if(strlen($s)>0) $stn2[] = $s;
    }
    $result = array();
    $result['bsr'] = $bsr;
    $result['tx'] = $tx;
    if(count($stn)>0) $result['aal_mon'] = $stn;
    if(count($stn2)>0) $result['other_mon'] = $stn2;
    return $result;
}

  date_default_timezone_set("UTC");
  $dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

  // allow use from the command line
  if(isset($argv) && count($argv)==2)
  {
    $filename = $file = $argv[1];
  }

  // allow use with a form
  if(array_key_exists('file', $_FILES)) {
      $file = $_FILES["file"]["tmp_name"];
      $filename = $_FILES["file"]["name"];
  }

  if(file_exists($file)) {

   $season = strtoupper(substr(basename($filename), 0, 3));
   if(function_exists("get_season_dates")) {
      $q = get_season_dates($season);
      $dates = season_dates($season);
   } else {
      $q->start_date="";
      $q->stop_date="";
      $dates = array();
   }

   print "<H2>Import records for $season ($q->start_date to $q->stop_date)</H2>";

   $count = importfile($dbh, $file, $season);

   print "Deleted $deleted rows from Targets table<br/>";
   print "Added $count rows into the Targets table.";
}
else {
   print "no file provided.";
}

?>
<FORM><INPUT TYPE="button" VALUE="Back" onClick="history.go(-1);return true;"></FORM>
</BODY>
</HTML>
