<HTML>
<HEAD><TITLE>WS HF Audibility Verification</TITLE></HEAD>
<BODY>
<?php
  require_once("report_template_functions.php");
  require_once('PHPExcel/PHPExcel.php'); // found when you download the PHPExcel  


function get_string($val, $def)
{
	$r = trim($val);
	if($r == '')
		return $def;
	return $r;
}

function get_array($sheet, $row, $cols)
{
    $aout = array();
    for($i=0; $i<count($cols); $i++)
    {
	$col = $cols[$i];
	$aout[] = trim($sheet->getCellByColumnAndRow($col, $row)->getValue());
    }
    $out = "{".implode(",", $aout)."}";
    $out = ereg_replace(",+}", "}", $out);
    $out = ereg_replace(",+", ",", $out);
    return $out;
}

function get_header($sheet, $row, $highestColumnIndex)
{
	$header = array();
	$c_pri = array();
	$c_sec = array();
	$c_ref = array();
  for ($col = 0; $col <= $highestColumnIndex; ++$col) {
	$cell = $sheet->getCellByColumnAndRow($col, $row);
		$head = $cell->getValue();
		$head = strtoupper($head);
		$head = str_replace("-", "_", $head);
		$head = str_replace(" ", "_", $head);
		if($head=="TIME_1") $header['start'] = $col;
		if($head=="TIME_2") $header['stop'] = $col;
		if($head=="LANG") $header['lang'] = $col;
		if($head=="REGION") $header['region'] = $col;
		if($head=="SERVICE") $header['target_area'] = $col;
		if($head=="SLA") $header['sla'] = $col;
		if(substr($head,0,7)=="PRIMARY") $c_pri[] = $col;
		if(substr($head,0,3)=="SEC") $c_sec[] = $col;
		if($head=="DAYS_IBB_CODE") $header['days'] = $col;
		if(substr($head,0,12)=="DAY_REF_FREQ") $c_ref[] = $col;
		if($head=="VALID_FROM") $header['vf'] = $col;
		if($head=="VALID_TO") $header['vt'] = $col;
	}
	$header['pri'] = $c_pri;
	$header['sec'] = $c_sec;
	$header['ref'] = $c_ref;
	return $header;
}

function hhmm($t)
{
	$t1 = sprintf("%04d", $t);
	$hh = substr($t1,0,-2);
	$mm = substr($t1,-2);
	return "$hh:$mm";
}

	//. "season, language, region, target_area, "
	//. "start_time, target, valid_from, valid_to, primary_frequency, secondary_frequency, ibb_days"
function get_row($season, $sheet, $row, $header)
{
$data = array($season);
    $lang = trim($sheet->getCellByColumnAndRow($header['lang'], $row)->getValue());
    if($lang == 'WSE')
	  $lang = 'English';
    $data[] = $lang;
    $data[] = trim($sheet->getCellByColumnAndRow($header['region'], $row)->getValue());
    $data[] = trim($sheet->getCellByColumnAndRow($header['target_area'], $row)->getValue());
    $data[] = hhmm($sheet->getCellByColumnAndRow($header['start'], $row)->getValue());
    $data[] = $sheet->getCellByColumnAndRow($header['sla'], $row)->getValue();
    $data[] = $sheet->getCellByColumnAndRow($header['vf'], $row)->getValue();
    $data[] = $sheet->getCellByColumnAndRow($header['vt'], $row)->getValue();
    $data[] = get_array($sheet, $row, $header['pri']);
    $data[] = get_array($sheet, $row, $header['sec']);
    $data[] = get_string($sheet->getCellByColumnAndRow($header['days'], $row)->getValue(), '1234567');
    //$stop = hhmm($sheet->getCellByColumnAndRow($header['stop'], $row)->getValue());
    //$ref = get_array($sheet, $row, $header['ref']);
return $data;
}

  // allow use from the command line
  if(count($argv)==2)
  {
    $filename = $file = $argv[1];
  }

  // allow use with a form
  if(array_key_exists('file', $_FILES)) {
      $file = $_FILES["file"]["tmp_name"];
      $filename = $_FILES["file"]["name"];
  }

  // allow use with no parameters
  if(!isset($file))
  {
    $map = readConfig();
    $conn   = imap_open ("{imap.gmail.com:993/imap/ssl}INBOX", $map['GMAIL_USER'], $map['GMAIL_PASSWORD'], 0, 1);
    $some   = imap_search($conn, 'ALL UNSEEN SUBJECT "Audibility_Targets"', SE_UID);
    if(is_array($some)) {
        foreach($some as $uid) {
            $s = imap_fetchstructure($conn, $uid, FT_UID);
            foreach($s->parts as $i => $part) {
                if( $part->ifdisposition && $part->disposition == 'ATTACHMENT' ) {
                    $filename = 'target.xls';
                    if($part->ifparameters) {
                        foreach($part->parameters as $parameter) {
                            if($parameter->attribute == "NAME")
                                $file = $parameter->value;
                        }
                    }
                    if( $part->subtype == "VND.MS-EXCEL" || $part->subtype == "OCTET-STREAM") {
                        $body = imap_fetchbody($conn, $uid, $i+1, FT_UID);
                        $f = fopen("/var/www/html/audibility/import/$file", "wb");
                        fwrite($f, base64_decode($body));
                        fclose($f);
                    }
                }
            }
        }
    }
  }
  if(!isset($file))
  {
	$filename = $file = "/var/www/html/audibility/import/A12_English_HF Audibility_Targets FINAL.xls";
  }

  $Reader = PHPExcel_IOFactory::createReaderForFile($file);  
  $Reader->setReadDataOnly(true); // set this, to not read all excel properties, just data 

  $season = strtoupper(substr(basename($filename), 0, 3));
  $q = get_season_dates($season);
  $lang = "Vernaculars";
  if(strpos($filename, "WSE")>0 || strpos(strtoupper($filename), "ENGLISH")>0)
	$lang = "English";
  print "<H2>Import records for $season ($q->start_date to $q->stop_date) into Targets for $lang</H2>";

   $dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

  $dates = season_dates($season);
  $default_valid_from = $q->start_date;
  $default_valid_to = $q->stop_date;
  $query = "delete from sla where season='$season'";
  if($lang=="English")
    $query .= " and language='English'";
  else
    $query .= " and language!='English'";

  $result = $dbh->exec($query);
  print "Deleted ".$result." rows from Targets Table\n";

  $objXLS = $Reader->load($file);
$objWorksheet = $objXLS->getActiveSheet();
$highestRow = $objWorksheet->getHighestRow(); // e.g. 10
$highestColumn = $objWorksheet->getHighestColumn(); // e.g 'F'
$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn); // e.g. 5

$count=0;
$header = get_header($objWorksheet, 1, $highestColumnIndex);
    $query = "INSERT INTO sla ("
	. "season, language, region, target_area, "
	. "start_time, target, valid_from, valid_to, primary_frequency, secondary_frequency, ibb_days"
	. ") VALUES (?,?,?,?,?,?,?,?,?,?,?)";
$stmt = $dbh->prepare($query);
for ($row = 2; $row <= $highestRow; ++$row) {
$data = get_row($season, $objWorksheet, $row, $header);


    $result = $stmt->execute($data);
	$count++;
  }
 $objXLS->disconnectWorksheets();  

unset($objXLS); 

?>
Added appox. <?php print $count; ?> rows into the SLA table.
</BODY>
</HTML>
