<HTML>
<HEAD><TITLE>WS HF Audibility Verification</TITLE></HEAD>
<BODY>
<H2>Import BSR</H2>
<PRE>
<?php

require_once("report_template_functions.php");
require_once('PHPExcel/PHPExcel.php'); // found when you download the PHPExcel
date_default_timezone_set("UTC");
$dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

function import_file($dbh, $file) {
	$Reader = PHPExcel_IOFactory::createReaderForFile($file);
	$Reader->setLoadSheetsOnly("Shortwave");
	$objXLS = $Reader->load($file);
	$objWorksheet = $objXLS->getActiveSheet();
	$highestRow = $objWorksheet->getHighestRow(); // e.g. 10
	$highestColumn = $objWorksheet->getHighestColumn(); // e.g 'F'
	$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn); // e.g. 5
	$count=0;
	$bsr_header = get_header($objWorksheet, 2, 0, 10);
	$tx_header = get_header($objWorksheet, 2, 11, 22);
	$aal_header = get_header($objWorksheet, 2, 23, 31);
	$mon_header = get_header($objWorksheet, 2, 32, 43);
	$header = $objWorksheet->getCellByColumnAndRow('A',1)->getValue();
	$season = substr($header, 0, 3);
	clear_season($dbh, $season);
	for ($row = 3; $row <= $highestRow; ++$row) {
	    $data = get_bsr_row($objWorksheet, $row, $bsr_header);
	    if($data['Service'] != '' && $data['Service Grade'] != '') {
		$bsr_data = $data;
		$bsr_data['Season'] = $season;
		$count += put_bsr($dbh, $bsr_data);
            }
	    $tx_data = get_tx_row($objWorksheet, $row, $tx_header);
	    $aal_data = get_mon_row($objWorksheet, $row, $aal_header);
	    $mon_data = get_mon_row($objWorksheet, $row, $mon_header);
	    put_tx($dbh, $bsr_data, $tx_data);
	    put_mon($dbh, $bsr_data, $tx_data, $aal_data, $mon_data);
	}

	$objXLS->disconnectWorksheets();
	unset($objXLS);

	return $count;
}

function clear_season($dbh, $season) {
    $sth = $dbh->prepare('DELETE FROM aal_bsr WHERE "Season"=?');
    $sth->bindParam(1, $season, PDO::PARAM_STR);
    $sth->execute();
    $sth = $dbh->prepare('DELETE FROM aal_mon WHERE "season"=?');
    $sth->bindParam(1, $season, PDO::PARAM_STR);
    $sth->execute();
    $dates = season_dates($season);
    $sth = $dbh->prepare('DELETE FROM hf_schedule WHERE "valid_from"=? AND valid_to=?');
    $sth->bindParam(1, $dates["start"], PDO::PARAM_STR);
    $sth->bindParam(2, $dates["end"], PDO::PARAM_STR);
    $sth->execute();
}

function put_bsr($dbh, $data) {
    $query = 'INSERT INTO aal_bsr("Service","Service Grade","Start","End","Days",'
	.'"Timeslot Duration","Weekly Duration","Annual Duration","Target Area","Network","Season")'
	.'VALUES(?,?,?,?,?,?,?,?,?,?,?)';
    $sth = $dbh->prepare($query);
    $sth->bindParam(1, $data['Service'], PDO::PARAM_STR);
    $sth->bindParam(2, $data['Service Grade'], PDO::PARAM_STR);
    $sth->bindParam(3, $data['Timeslot Start'], PDO::PARAM_STR);
    $sth->bindParam(4, $data['Timeslot End'], PDO::PARAM_STR);
    $sth->bindParam(5, $data['DOW'], PDO::PARAM_STR);
    $sth->bindParam(6, $data['Timeslot Duration'], PDO::PARAM_STR);
    $sth->bindParam(7, $data['Weekly Timeslot Duration'], PDO::PARAM_STR);
    $sth->bindParam(8, $data['Annual Timeslot Duration'], PDO::PARAM_STR);
    $sth->bindParam(9, $data['WPLOT Target Area'], PDO::PARAM_STR);
    $sth->bindParam(10, $data['Network'], PDO::PARAM_STR);
    $sth->bindParam(11, $data['Season'], PDO::PARAM_STR);
try {
    $sth->execute();
    return 1;
} catch(PDOException $e) {
	print $e;
	print_r($data);
	return 0;
}
}

function put_tx($dbh, $bsr_data, $tx_data) {
    if($tx_data['Frequency']=='')
        return;
    $query = 'INSERT INTO hf_schedule '
	.'('
	.'frequency,start_time,stop_time,days,site,power,bearing,network,language,target,cirafs,antenna_type'
	.',valid_from,valid_to'
	.') '
	.'VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $sth = $dbh->prepare($query);
    $days = str_replace('.', '_', strtoupper($tx_data['Days']));
    $sth->bindParam(1, $tx_data['Frequency'], PDO::PARAM_STR);
    $sth->bindParam(2, $tx_data['Start Time'], PDO::PARAM_STR);
    $sth->bindParam(3, $tx_data['Stop Time'], PDO::PARAM_STR);
    $sth->bindParam(4, $days, PDO::PARAM_STR);
    $sth->bindParam(5, $tx_data['Site'], PDO::PARAM_STR);
    $sth->bindParam(6, $tx_data['Power'], PDO::PARAM_STR);
    $sth->bindParam(7, $tx_data['Azimuth'], PDO::PARAM_STR);
    $sth->bindParam(8, $bsr_data['Network'], PDO::PARAM_STR);
    $sth->bindParam(9, $tx_data['Language'], PDO::PARAM_STR);
    //$sth->bindParam(10, $tx_data['Target'], PDO::PARAM_STR);
    $sth->bindParam(10, $bsr_data['WPLOT Target Area'], PDO::PARAM_STR);
    $sth->bindParam(11, $tx_data['Ciraf'], PDO::PARAM_STR);
    $sth->bindParam(12, $tx_data['Antenna Type'], PDO::PARAM_STR);
    $dates = season_dates($bsr_data['Season']);
    $sth->bindParam(13, $dates["start"], PDO::PARAM_STR);
    $sth->bindParam(14, $dates["end"], PDO::PARAM_STR);
    $sth->execute();
}
//start target_area town aal season language stn service 
function put_mon($dbh, $bsr_data, $tx_data, $aal_data, $mon_data) {
    $query = 'INSERT INTO aal_mon(start, target_area, town, aal, season, language, service, stn)'
	.'SELECT ?,?,?,?,?,?,?, ARRAY(SELECT stn FROM ms WHERE town=?)';
    $sth = $dbh->prepare($query);
    $sth->bindParam(1, $bsr_data['Timeslot Start'], PDO::PARAM_STR);
    $sth->bindParam(2, $bsr_data['WPLOT Target Area'], PDO::PARAM_STR);
    $sth->bindParam(5, $bsr_data['Season'], PDO::PARAM_STR);
    $language = "{".$tx_data['Language']."}";
    $sth->bindParam(6, $language, PDO::PARAM_STR);
    $sth->bindParam(7, $bsr_data['Service'], PDO::PARAM_STR);
    put_mon1($sth, $aal_data, 1);
    put_mon1($sth, $mon_data, 0);
}

function put_mon1($sth, $data, $aal) {
    $sth->bindParam(4, $aal, PDO::PARAM_BOOL);
    foreach($data['town'] as $town) {
if($town != '') {
        $sth->bindParam(3, $town, PDO::PARAM_STR);
        $sth->bindParam(8, $town, PDO::PARAM_STR);
        $sth->execute();
}
    }
}

function hhmm($t)
{
    $t1 = sprintf("%04d", $t);
    $hh = substr($t1,0,-2);
    $mm = substr($t1,-2);
    return "$hh:$mm";
}


function get_header($sheet, $row, $fcol, $lcol)
{
    $header = array();
    for ($col = $fcol; $col <= $lcol; ++$col) {
        $cell = $sheet->getCellByColumnAndRow($col, $row);
        $head = $cell->getValue();
        $header[$head] = $col;
    }
    return $header;
}


function get_cell(&$r, $sheet, $row, $col, $header)
{
  if(array_key_exists($col, $header)) { 
    $val = trim($sheet->getCellByColumnAndRow($header[$col], $row)->getCalculatedValue());//getValue());
    if($val == '#VALUE!')
	$val = '0';
    if($col == "Days")
	$val = strtoupper($val);
    if($col == "Timeslot Start")
        $val = PHPExcel_Style_NumberFormat::toFormattedString($val, 'hh:mm:ss');
    if($col == "Timeslot End")
        $val = PHPExcel_Style_NumberFormat::toFormattedString($val, 'hh:mm:ss');
    if($col == "Start Time")
        $val = PHPExcel_Style_NumberFormat::toFormattedString($val, 'hh:mm:ss');
    if($col == "Stop Time")
        $val = PHPExcel_Style_NumberFormat::toFormattedString($val, 'hh:mm:ss');
    $r[$col] = $val;
  }
}

function get_bsr_row($sheet, $row, $header)
{
    $data = array();
    get_cell($data, $sheet, $row, 'Service', $header);
    get_cell($data, $sheet, $row, 'Service Grade', $header);
    get_cell($data, $sheet, $row, 'Timeslot Start', $header);
    get_cell($data, $sheet, $row, 'Timeslot End', $header);
    get_cell($data, $sheet, $row, 'DOW', $header);
    get_cell($data, $sheet, $row, 'Timeslot Duration', $header);
    get_cell($data, $sheet, $row, 'Weekly Timeslot Duration', $header);
    get_cell($data, $sheet, $row, 'Annual Timeslot Duration', $header);
    get_cell($data, $sheet, $row, 'WPLOT Target Area', $header);
    get_cell($data, $sheet, $row, 'Network', $header);
    get_cell($data, $sheet, $row, 'Service Area', $header);
    get_cell($data, $sheet, $row, '"Extra"', $header);
    return $data;
}

function get_tx_row($sheet, $row, $header)
{
    $data = array();
    get_cell($data, $sheet, $row, "Frequency", $header);
    get_cell($data, $sheet, $row, "Start Time", $header);
    get_cell($data, $sheet, $row, 'Stop Time', $header);
    get_cell($data, $sheet, $row, 'Days', $header);
    get_cell($data, $sheet, $row, 'Site', $header);
    get_cell($data, $sheet, $row, 'Power', $header);
    get_cell($data, $sheet, $row, 'Azimuth', $header);
    get_cell($data, $sheet, $row, 'Language', $header);
    get_cell($data, $sheet, $row, 'Target', $header);
    get_cell($data, $sheet, $row, 'Ciraf', $header);
    get_cell($data, $sheet, $row, 'Antenna Type', $header);
    return $data;
}

function get_mon_row($sheet, $row, $header)
{
    $data = array();
    get_cell($data, $sheet, $row, 'AAL', $header);
    $town = array();
    for($i=1; $i<10; $i++) { 
      $col = "RMS $i";
      if(array_key_exists($col, $header)) { 
        $town[] = trim($sheet->getCellByColumnAndRow($header[$col], $row)->getCalculatedValue());
      }
    }
    $data['town'] = $town;
    return $data;
}

// allow use from the command line
if(count($argv)==2)
{
    $filename = $file = $argv[1];
}

// allow use with a form
if(array_key_exists('file', $_FILES))
{
    $file = $_FILES["file"]["tmp_name"];
    $filename = $_FILES["file"]["name"];
    `mv '$file' 'import/$filename'`;
    $file = "import/$filename";
}

if(isset($file))
{
  print "reading $file<br/>\n";
  $count = import_file($dbh, $file);
  print "Inserted $count rows into database\n";
} else {
  print "no BSR spreadsheet specified\n";
}
?>
<FORM><INPUT TYPE="button" VALUE="Back" onClick="history.go(-1);return true;"></FORM>
</BODY>
</HTML>
