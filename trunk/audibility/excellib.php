<?php

function start_workbook($author, $company)
{
	$doc = '<?xml version="1.0"?>';
	$doc.= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
	$doc.= '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">';
    $doc.="<Author>$author</Author>";
    $doc.="<LastAuthor>$author</LastAuthor>";
	$doc.="<Created>".gmdate("Y-n-d")."T".gmdate("H:i:s")."Z"."</Created>";
    $doc.="<Company>$company</Company>";
    $doc.="<Version>11.6568</Version>";
	$doc.="</DocumentProperties>";
	$doc.='<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel"/>';
	return $doc;
}

function end_workbook()
{
	return "</Workbook>";
}

function start_sheet($name)
{
	return "<Worksheet ss:Name=\"$name\">";
}

function end_sheet()
{
	return "</Worksheet>\n";
}

function start_table()
{
	return "<Table>\n";
}

function end_table()
{
	return "</Table>\n";
}

function start_row($height="")
{
	if($height=="")
		return "<Row>";
	else
		return "<Row ss:AutoFitHeight=\"0\" ss:Height=\"$height\">";
}

function end_row()
{
	return "</Row>\n";
}

function new_cell($content, $type, $style="", $attr=array())
{
	$c = "<Cell";
	if($style!="")
		$c .= " ss:StyleID=\"$style\"";
	foreach($attr as $name => $value)
	{
		$c .= " ss:$name=\"".htmlentities($value)."\"";
	}
	$c .= "><Data ss:Type=\"$type\">".htmlentities($content)."</Data></Cell>";
	return $c;
}

?>
