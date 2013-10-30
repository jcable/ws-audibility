<?php

require_once("report_template_functions.php");
require_once('PHPExcel/PHPExcel.php'); // found when you download the PHPExcel
date_default_timezone_set("UTC");
$dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

// allow use from the command line
if(count($argv)==2)
{
        $date = DateTime::createFromFormat('Y-m-d', $argv[1]);
}
else
{
        $d = $_REQUEST['date'];
        $d = substr($d, 0, 8)."01";
        $date = DateTime::createFromFormat('Y-m-d', $d);
}

$year = $date->format("Y");
$month_name = $date->format("F");
$season = get_season($year, $month_name);
$month = season_month($season, $month_name);
$q = get_times($season, $month);
$start_date = $q->start_date;
$stop_date = $q->stop_date;
$month_name = $q->month_name;
$map = get_target_areas_pdo($dbh);
$regions = get_regions_pdo($dbh);
// output the xml file
$file = "Aubilility_$start_date.xlsx";

$objPHPExcel = new PHPExcel();

$objPHPExcel->getProperties()->setCreator("WS Audibility");
$objPHPExcel->getProperties()->setLastModifiedBy("WS Audibility");
$objPHPExcel->getProperties()->setTitle("Aubilility Report for $start_date");
$objPHPExcel->getProperties()->setSubject("World Service HF Audibility");
$objPHPExcel->getProperties()->setDescription("Investigation Requests");

	$goodrow = array( 'fill' => array( 'type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => '1CED46')));
	$badrow = array( 'fill' => array( 'type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => 'FF99CC')));

	// Investigations for the month

	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet()->setTitle("Investigations");
	$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
	$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
	$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(55);
	$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
	$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(12);
	$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(13);
	$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(200);

        $investigations = get_investigations_pdo($dbh, $month, $season, $map);
	$objPHPExcel->getActiveSheet()->SetCellValue("A1", "Audibility Report for $month_name");
	$objPHPExcel->getActiveSheet()->SetCellValue("A2", "ticket_id");
	$objPHPExcel->getActiveSheet()->SetCellValue("B2", "Type");
	$objPHPExcel->getActiveSheet()->SetCellValue("C2", "Summary");
	$objPHPExcel->getActiveSheet()->SetCellValue("D2", "Status");
	$objPHPExcel->getActiveSheet()->SetCellValue("E2", "Ops Change?");
	$objPHPExcel->getActiveSheet()->SetCellValue("F2", "Mon Change?");
	$objPHPExcel->getActiveSheet()->SetCellValue("G2", "Description");
	$objPHPExcel->getActiveSheet()->getStyle('A1:G2')->applyFromArray(array( 'font' => array( 'bold' => true )));
	$i=3;
        foreach($investigations as $inv)
        {
                $id = $inv["ticket_id"];
                $sheet = "inv $id";
                $investigations[$id]["sheet"] = $sheet;
		$objPHPExcel->getActiveSheet()->SetCellValue("A$i", $id);
		$objPHPExcel->getActiveSheet()->getCell("A$i")->getHyperlink()->setUrl("sheet://'$sheet'!A1");
		$objPHPExcel->getActiveSheet()->SetCellValue("B$i", $inv["action"]);
		$objPHPExcel->getActiveSheet()->SetCellValue("C$i", $inv["summary"]);
		$objPHPExcel->getActiveSheet()->SetCellValue("G$i", $inv["notes"]);
		$i++;
        }

	// Report Summary
	$sheetId = 1;
	$sheet = $objPHPExcel->createSheet(NULL, $sheetId);
	$sheet->setTitle("Summary");

	$title = "AAL Report for ".$date->format("F Y")."($season)";
	$sql = "SELECT DISTINCT * FROM aal_bsr ORDER BY \"Service\", \"Target Area\", \"Start\"";
	$ta=""; $service="";
	$step = new DateInterval("PT30M");
	$query = "SELECT target,score,freeze_date FROM aal_monthly_summaries WHERE start_time=? AND target_area=? AND service=? AND month=?";
	$sth = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	$row_number = 3;
	foreach ($dbh->query($sql) as $row) {
		if($row['Service']!=$service || $row['Target Area']!=$ta) {
			$h = $row['Service'];
			$sheet->getStyle("A$row_number")->applyFromArray(array( 'font' => array( 'bold' => true )));
			if($row['Network']!=$row['Target Area']) {
				$h .= " ".$row['Target Area'];
			}
			$sheet->SetCellValue("A$row_number", $h);
			$service = $row['Service'];
			$ta = $row['Target Area'];
			$row_number++;
		}
		$start_text = $row['Start'];
		$end_text = $row['End'];
		$start = DateTime::createFromFormat('G:i:sO', $start_text);
		$end = DateTime::createFromFormat('G:i:sO', $end_text);
		$month=$date->format('Y-m-d');
		$row = array();
		for($t = clone $start; $t<$end; $t->add($step)) {
			$s = $t->format("G:i:s");
			$p = array($s, $ta, $service, $month);
			$sth->execute($p);
			$scores = $sth->fetchAll();
			$row[$s] = $scores[0];
		}
		$col_number = 0;
		foreach($row as $s => $v) {
			$sheet->setCellValueByColumnAndRow($col_number, $row_number, $s);
			$link = "detail_aal.php"
				."?ta=$ta"
				."&service=$service"
				."&month=$month"
				."&service_start=".$start->format("G:i:s")
				."&slot_start=$s"
				."&target=".$v['target']
				."&score=".$v['score']
				."&freeze=".$v['freeze_date'];
			$col_number++;
		}
		$row_number++;
		$col_number = 0;
		foreach($row as $s => $v) {
			$t = $v['score']."/".$v['target'];
			$sheet->setCellValueByColumnAndRow($col_number, $row_number, $t);
			if($v['score']>=$v['target']) {
				$style = $goodrow;
			}
			else {
				$style = $badrow;
			}
			$sheet->getStyleByColumnAndRow($col_number, $row_number)->applyFromArray($style);
			$col_number++;
		}
		$row_number++;
	}

	// Detail pages for pages with investigations

	$start_date = $date->format("Y-m-d");
	$mo = new DateInterval("P1M");
	$dy = new DateInterval("P1D");
	$stop = $date->add($mo)->sub($dy);
	$stop_date = $stop->format("Y-m-d");
        foreach($investigations as $inv) {
		$sheetId++;
		$sheet = $objPHPExcel->createSheet(NULL, $sheetId);
		$sheet->setTitle($inv["sheet"]);
		$row_number=1;
		$sheet->setCellValueByColumnAndRow(0, $row_number, $inv['summary']);
		$row_number++;
		$sheet->setCellValueByColumnAndRow(0, $row_number, 'date');
		$sheet->setCellValueByColumnAndRow(1, $row_number, 'start');
		$sheet->setCellValueByColumnAndRow(2, $row_number, 'time');
		$sheet->setCellValueByColumnAndRow(3, $row_number, 'stn');
		$sheet->setCellValueByColumnAndRow(4, $row_number, 'frequency');
		$sheet->setCellValueByColumnAndRow(5, $row_number, 'aal');
		$sheet->setCellValueByColumnAndRow(6, $row_number, 's');
		$sheet->setCellValueByColumnAndRow(7, $row_number, 'd');
		$sheet->setCellValueByColumnAndRow(8, $row_number, 'o');
		$sheet->getStyle('A1:I2')->applyFromArray(array( 'font' => array( 'bold' => true )));
		$row_number++;
		$sql = query_for_detail_aal($inv['language'], $inv['ta_name'], "", $inv['start'], $start_date, $stop_date, "");
		foreach ($dbh->query($sql) as $row) {
			$start = DateTime::createFromFormat ("H:i:s", substr($row['start'], 0, 8));
			$time = DateTime::createFromFormat ("H:i:s", substr($row['time'], 0, 8));
			$sheet->setCellValueByColumnAndRow(0, $row_number, $row['date']);
			$sheet->setCellValueByColumnAndRow(1, $row_number, $start->format("H:i:s"));
			$sheet->setCellValueByColumnAndRow(2, $row_number, $time->format("H:i:s"));
			$sheet->setCellValueByColumnAndRow(3, $row_number, $row['stn']);
			$sheet->setCellValueByColumnAndRow(4, $row_number, $row['frequency']);
			$sheet->setCellValueByColumnAndRow(5, $row_number, $row['aal']);
			$sheet->setCellValueByColumnAndRow(6, $row_number, $row['s']);
			$sheet->setCellValueByColumnAndRow(7, $row_number, $row['d']);
			$sheet->setCellValueByColumnAndRow(8, $row_number, $row['o']);
			$row_number++;
		}
		$sheet->getColumnDimension('A')->setWidth(10);
		foreach(range('B','I') as $columnID) {
		    $sheet->getColumnDimension($columnID)->setAutoSize(true);
		}
        }
	$objPHPExcel->setActiveSheetIndex(0);
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="'.$file.'"');
header("Cache-Control: max-age=0");
$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save("php://output");
//$objWriter->save($file);

?>
