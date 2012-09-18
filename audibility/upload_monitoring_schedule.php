<HTML>
<HEAD><TITLE>WS HF Audibility Verification</TITLE></HEAD>
<BODY>
<H2>Import Monitoring Schedule</H2>
<PRE>
<?php

require_once('ciraf_lib.php');
require_once("report_template_functions.php");
require_once('PHPExcel/PHPExcel.php'); // found when you download the PHPExcel


	//.'"ID", "TAB", "LANG", "START", "STOP", "FREQ", "SITE", "IBB DAY CODE", '
	//.'"MON LOC", "ADD DATE", "PERMANENT DELETION DATE", "TARGET AREA", "COMMENT") '
function get_header($sheet, $row, $highestColumnIndex)
{
    $header = array();
    for ($col = 0; $col <= $highestColumnIndex; ++$col) {
        $cell = $sheet->getCellByColumnAndRow($col, $row);
        $head = $cell->getValue();
	if($head == "TARGET AREA (UNCONFIRMED)")
		$head = "TARGET AREA";
	$header[$head] = $col;
    }
    return $header;
}

function hhmm($t)
{
    $t1 = sprintf("%04d", $t);
    $hh = substr($t1,0,-2);
    $mm = substr($t1,-2);
    return "$hh:$mm";
}

function get_row($sheet, $row, $header)
{
    $data = array();
    $data[] = trim($sheet->getCellByColumnAndRow($header['ID'], $row)->getValue());
    $data[] = trim($sheet->getCellByColumnAndRow($header['TAB'], $row)->getValue());
    $data[] = trim($sheet->getCellByColumnAndRow($header['LANG'], $row)->getValue());
    $data[] = hhmm($sheet->getCellByColumnAndRow($header['START'], $row)->getValue());
    $data[] = hhmm($sheet->getCellByColumnAndRow($header['STOP'], $row)->getValue());
    $data[] = trim($sheet->getCellByColumnAndRow($header['FREQ'], $row)->getValue());
    $data[] = trim($sheet->getCellByColumnAndRow($header['SITE'], $row)->getValue());
    $data[] = trim($sheet->getCellByColumnAndRow($header['IBB DAY CODE'], $row)->getValue());
    $data[] = trim($sheet->getCellByColumnAndRow($header['MON LOC'], $row)->getValue());
    $data[] = PHPExcel_Shared_Date::ExcelToPHPObject($sheet->getCellByColumnAndRow($header['ADD DATE'], $row)->getValue())->format('Y-m-d');
    $pdd = $sheet->getCellByColumnAndRow($header['PERMANENT DELETION DATE'], $row)->getValue();
    if($pdd == '' || $pdd == 0)
        $data[] = '2111-12-31';
    else
        $data[] = PHPExcel_Shared_Date::ExcelToPHPObject($pdd)->format('Y-m-d');
    $data[] = cirafs2array($sheet->getCellByColumnAndRow($header['TARGET AREA'], $row)->getValue());
    $data[] = trim($sheet->getCellByColumnAndRow($header['COMMENT'], $row)->getValue());
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

// allow use with no parameters
if(!isset($file))
{
    $map = readConfig();
    $conn   = imap_open ("{imap.gmail.com:993/imap/ssl}INBOX", $map['GMAIL_USER'], $map['GMAIL_PASSWORD'], 0, 1);
    $some   = imap_search($conn, 'ALL UNSEEN SUBJECT "BBC Monitoring Schedule"', SE_UID);
    if(is_array($some)) {
        foreach($some as $uid) {
            $s = imap_fetchstructure($conn, $uid, FT_UID);
            foreach($s->parts as $i => $part) {
                if( $part->ifdisposition && $part->disposition == 'ATTACHMENT' ) {
                    $filename = 'monitoring_schedule.xls';
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


if(isset($file))
{
print "reading $file<br/>\n";

$Reader = PHPExcel_IOFactory::createReaderForFile($file);

$dbh = pdo_login('wsdata', 'PG_USER', 'PG_PASSWORD');

date_default_timezone_set("UTC");

$objXLS = $Reader->load($file);
$objWorksheet = $objXLS->getActiveSheet();
$highestRow = $objWorksheet->getHighestRow(); // e.g. 10
$highestColumn = $objWorksheet->getHighestColumn(); // e.g 'F'
$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn); // e.g. 5

$dbh->exec("TRUNCATE TABLE monitoring_schedule");

$count=0;
$query = 'INSERT INTO monitoring_schedule ('
	.'"ID", "TAB", "LANG", "START", "STOP", "FREQ", "SITE", "IBB DAY CODE", '
	.'"MON LOC", "ADD DATE", "PERMANENT DELETION DATE", "TARGET AREA", "COMMENT") '
        .'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
$stmt = $dbh->prepare($query);

$header = get_header($objWorksheet, 1, $highestColumnIndex);
for ($row = 2; $row <= $highestRow; ++$row) {
    $data = get_row($objWorksheet, $row, $header);
    if($data[2] != '') {
        $result = $stmt->execute($data);
        $count++;
    }
}

$objXLS->disconnectWorksheets();
unset($objXLS);

print "Inserted $count rows into monitoring schedule\n";

} else {
print "no monitoring schedule spreadsheet specified\n";
}
?>
<FORM><INPUT TYPE="button" VALUE="Back" onClick="history.go(-1);return true;"></FORM>
</BODY>
</HTML>
