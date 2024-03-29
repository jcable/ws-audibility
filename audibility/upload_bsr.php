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
	$aal_mon_cols = array();
	$other_mon_cols = array();
	$aal_col = 0;
	for($c=1; $c<$highestColumnIndex; $c++) {
		$rgb = $objWorksheet->getStyleByColumnAndRow($c,2)->getFill()->getStartColor()->getRGB();
		$h = $objWorksheet->getCellByColumnAndRow($c,2)->getValue();
		if(substr($h, 0, 3) == "RMS")
		{
			if($rgb == "FFCC00") // orange for AAL
			{
				$aal_mon_cols[] = $c;
			}
			if($rgb == "1FB714") // Green for other monitoring
			{
				$other_mon_cols[] = $c;
			}
		}
		if(substr($h, 0, 3) == "AAL")
		{
			$aal_col = $c;
		}
	}
	$header = $objWorksheet->getCellByColumnAndRow('A',1)->getValue();
	$season = substr($header, 0, 3);
	clear_season($dbh, $season);
	for ($row = 3; $row <= $highestRow; ++$row) {
	    $data = get_bsr_row($objWorksheet, $row, $bsr_header);
	    $tx_data = get_tx_row($objWorksheet, $row, $tx_header);
	    if($data['Service'] != '' && $data['Service Grade'] != '' && $data['Timeslot Duration'] > 0) {
		$bsr_data = $data;
		$bsr_data['Season'] = $season;
		$aal = $objWorksheet->getCellByColumnAndRow($aal_col, $row)->getCalculatedValue();
		$count += put_bsr($dbh, $bsr_data);
	        $aal_mon_data = get_mon_row($objWorksheet, $row, $aal_mon_cols);
		$aal_mon_data["aal"] = $aal;
	        $other_mon_data = get_mon_row($objWorksheet, $row, $other_mon_cols);
		$other_mon_data["aal"] = $aal;
	        put_mon($dbh, $bsr_data, $tx_data, $aal_mon_data, $other_mon_data);
            }
	    put_tx($dbh, $bsr_data, $tx_data);
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
	        //put_mon($dbh, $bsr_data, $tx_data, $aal_mon_data, $other_mon_data);
function put_mon($dbh, $bsr_data, $tx_data, $aal_mon_data, $other_mon_data) {
    $query = 'INSERT INTO aal_mon(start, target_area, town, season, language, service, aal_stn, other_stn, target)'
	.'SELECT ?,?,?,?,?,?, ARRAY(SELECT stn FROM ms WHERE town=ANY(?)), ARRAY(SELECT stn FROM ms WHERE town=ANY(?)),?';
    $sth = $dbh->prepare($query);
    $ts = $bsr_data['Timeslot Start'];
    $te = $bsr_data['Timeslot End'];
    $dts = new DateTime($ts);
    if($dts->format('s')=='30')
	$dts->add(new DateInterval('PT30S'));
    $dte = new DateTime($te);
    if($dte->format('s')=='30')
	$dte->add(new DateInterval('PT30S'));
    $tss = $dts->getTimestamp();
    $tse = $dte->getTimestamp();
    if($tse<$tss)
    {
       $tse += 86400;
    }
    $dur = $tse - $tss;
    $minutes = $dur / 60;
    $slots = round($minutes / 30);
    for($i=0; $i<$slots; $i++)
    {
	    $n = new DateTime();
	    $n->setTimestamp($tss+1800*$i);
	    $slot_start = $n->format("H:i:s");
	    $sth->bindParam(1, $slot_start, PDO::PARAM_STR);
	    $sth->bindParam(2, $bsr_data['WPLOT Target Area'], PDO::PARAM_STR);
	    $t1 = $aal_mon_data['town'][0];
	    $sth->bindParam(3, $t1, PDO::PARAM_STR);
	    $sth->bindParam(4, $bsr_data['Season'], PDO::PARAM_STR);
	    $language = $tx_data['Language'];
	    if($language == 'Krwanda/Krundi')
		$language = 'Kinyarwanda/Kirundi';
	    $language = "{".$language."}";
	    $sth->bindParam(5, $language, PDO::PARAM_STR);
	    $sth->bindParam(6, $bsr_data['Service'], PDO::PARAM_STR);
	    $aal_town = '{'.implode(',',$aal_mon_data['town']).'}';
	    $sth->bindParam(7, $aal_town, PDO::PARAM_STR);
	    $other_town = '{'.implode(',',$other_mon_data['town']).'}';
	    $sth->bindParam(8, $other_town, PDO::PARAM_STR);
	    $target = $aal_mon_data["aal"];
	    $sth->bindParam(9, $target, PDO::PARAM_INT);
	    $sth->execute();
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
    if($col == "Timeslot Duration") {
	// Work around bug in PHPExcel
        $s = $sheet->getCellByColumnAndRow($header["Timeslot Start"], $row)->getCalculatedValue();
        $e = $sheet->getCellByColumnAndRow($header["Timeslot End"], $row)->getCalculatedValue();
	$val = 1 - ($s - $e);
    }
    if($val == '#VALUE!')
	$val = '0';
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

function get_mon_row($sheet, $row, $cols)
{
    $town = array();
    foreach($cols as $col) { 
        $t = trim($sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue());
	if($t != '')
		$town[] = $t;
    }
    $data = array('town' => $town);
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
