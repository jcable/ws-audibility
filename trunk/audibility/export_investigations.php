<?php

require_once("report_template_functions.php");
require_once("excellib.php");

	function show_detail_header($investigation, $start_date, $stop_date, $month_name)
	{
		$title = "Detail for ".$investigation["language"]." in ".$investigation["ta_name"]." (".$investigation["ta"].")";
		$title .= " at ".$investigation["start"]." during ".$investigation["month_name"];
		echo start_sheet(htmlentities($investigation["sheet"]));
		echo start_table();
		echo start_row().new_cell($title, "String", "s_bold").end_row();
		echo start_row().new_cell("", "String").end_row();
		echo start_row();
		echo new_cell("day", "String", "rowheader");
		echo new_cell("time", "String", "rowheader");
		echo new_cell("frequency", "String", "rowheader");
		echo new_cell("stn", "String", "rowheader");
		echo new_cell("s", "String", "rowheader");
		echo new_cell("d", "String", "rowheader");
		echo new_cell("o", "String", "rowheader");
		echo new_cell("best", "String", "rowheader");
		echo end_row();
	}

	function show_detail_row($line)
	{
		if($line["best"]) {
			$used = true;
		} else {
			$used = false;
		}
		echo start_row();
		echo new_cell($line["date"], "String");
		echo new_cell($line["time"], "String");
		echo new_cell($line["frequency"], "Number");
		echo new_cell($line["stn"], "String");
		echo new_cell($line["s"], "Number");
		echo new_cell($line["d"], "Number");
		echo new_cell($line["o"], "Number");
		echo new_cell($used?"1":"", "Number");
		echo end_row();
	}

	function show_detail_score($score)
	{
		echo start_row();
		if($score==-1)
			new_cell("No In Target Scores for this time", "String", "s_bold");
		else
			echo new_cell($score, "Number", "s_sum", array("Index"=>"7", "Formula"=>
				'=SUMIF(R3C[1]:R[-1]C[1],"1",R3C:R[-1]C)/COUNTIF(R3C[1]:R[-1]C[1],"1")'));
		echo end_row();
	}

	function show_detail_trailer($investigation)
	{
		echo start_row();
		echo end_row();
		echo start_row().new_cell($investigation["action"], "String", "s_bold").end_row();
		echo start_row(90);
		echo new_cell($investigation["notes"], "String", "s_wrap", array("MergeAcross"=>"7"));
		echo end_row();
		echo end_table();
		echo end_sheet();
	}

class SummarySheet extends Region
{
	protected function show_language_header($region_name, $lang)
	{
	}

	protected function show_language_trailer($icons)
	{
	}

	protected function show_language_target_area_header($region_name, $lang, $ta, $map)
	{
		echo start_row().new_cell("", "String").end_row();
		echo start_row().new_cell($region_name." ".$lang." ".$map[$ta]->name." ($ta)", "String", "s_bold").end_row();
		echo start_row().new_cell("", "String").end_row();
		echo start_row();
		echo new_cell("Start", "String", "rowheader");
		echo new_cell("Target", "String", "rowheader");
		echo new_cell("In", "String", "rowheader");
		echo new_cell("Out", "String", "rowheader");
		echo new_cell("Link", "String", "rowheader");
		echo end_row();
	}

	protected function show_language_target_area_trailer($icons)
	{
	}

	protected function show_row($start, $s, $class, $url)
	{
		echo start_row();
		echo new_cell($start, "String", $class);
		echo new_cell($s["target"], "Number", $class);
		if(is_numeric($s["avg"]))
			echo new_cell($s["avg"], "Number", $class);
		else
			echo new_cell("", "Number", $class);
		if(is_numeric($s["out_avg"]))
			echo new_cell($s["out_avg"], "Number", $class);
		else
			echo new_cell("", "Number", $class);
		if(isset($s["investigation_action"]))
		{
			if($s["investigation_action"] == 'investigate') {
				$mark = "I";
			} else if($s["investigation_action"] == 'request monitoring') {
				$mark = "R";
			} else if($s["investigation_action"] == 'investigate lack of observations') {
				$mark = "S";
			} else if($s["investigation_action"] == 'ignore') {
				$mark = "-";
			}
			echo new_cell($mark, "String", "linkrow", array("HRef"=>$url));
		}
		echo end_row();
		return array();
	}

	protected function make_url($season, $month, $language, $ta, $start, $freeze, $score, $s)
	{
		if(isset($s["sheet"]))
		{
			$sheet = $s["sheet"];
			return "#'$sheet'!A1";
		}
		return "";
	}
}



// Connecting, selecting databases
$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');

$freeze = gmdate('Y-m-d');
$year = gmdate('Y');
$month_name = gmdate('m');


if(count($argv)==4)
{
  $year = $argv[1];
  $month_name = $argv[2];
  $freeze = $argv[3];
}

if(isset($_GET["freeze"])) {
	$freeze = $_GET["freeze"];
}
if(isset($_GET["year"])) {
	$year = $_GET["year"];
}
if(isset($_GET["month"])) {
	$month_name = $_GET["month"];
}
if(array_key_exists('date', $_GET))
{
        $d = explode('-', $_GET['date']);
	if( $d[0] != '')
	{
        	$year = $d[0];
        	$month_name = $d[1];
	}
}
$season = get_season($year, $month_name);
$month = season_month($season, $month_name);
$q = get_times($season, $month);
$start_date = $q->start_date;
$stop_date = $q->stop_date;
$month_name = $q->month_name;
$map = get_target_areas();
$regions = get_regions($map);
// output the xml file
$file = "Aubilility_$start_date.xls";
header('Content-Disposition: attachment; filename="'.$file.'"');
header('Content-type: application/vnd.ms-excel');
echo start_workbook("BBC World Service", "BBC");
?>
 <Styles>
   <Style ss:ID="Default" ss:Name="Normal">
      <Alignment ss:Vertical="Bottom"/>
	</Style>
	<Style ss:ID="rowheader">
	  <Alignment ss:Horizontal="Center" ss:Vertical="Bottom"/>
	  <Font x:Family="Swiss" ss:Bold="1"/>
	</Style>
	<Style ss:ID="s_bold"><Font x:Family="Swiss" ss:Bold="1"/></Style>
	<Style ss:ID="s_wrap"><Alignment ss:Vertical="Top" ss:WrapText="1"/></Style>
	<Style ss:ID="nodatarow"><Interior ss:Color="#C0C0C0" ss:Pattern="Solid"/></Style>
	<Style ss:ID="goodrow"><Interior ss:Color="#339966" ss:Pattern="Solid"/></Style>
	<Style ss:ID="badrow"><Interior ss:Color="#FF99CC" ss:Pattern="Solid"/></Style>
	<Style ss:ID="verybadrow"><Interior ss:Color="#FF0000" ss:Pattern="Solid"/></Style>
	<Style ss:ID="badtwicerow"><Interior ss:Color="#FF9900" ss:Pattern="Solid"/></Style>
	<Style ss:ID="linkrow"><Interior ss:Color="#FFFF00" ss:Pattern="Solid"/></Style>
	<Style ss:ID="s_sum"><NumberFormat ss:Format="0.0"/></Style>
 </Styles>
<?php
	$investigations = get_investigations($dbconn, $month, $season, $map);

//Start of text for Investigations sheet
	echo start_sheet("Investigations");
	echo start_table();
?>
	<Column ss:Width="27" />
	<Column ss:Width="51" />
	<Column ss:Width="195" />
	<Column ss:Width="35" />
	<Column ss:Width="70" />
	<Column ss:Width="72" />
	<Column ss:Width="903" />
<?php
	echo start_row().new_cell("Audibility Report for $month_name", "String", "s_bold").end_row();
        echo start_row().end_row();
        echo start_row();
        echo new_cell("ticket_id", "String", "rowheader");
	echo new_cell("Type", "String", "rowheader");
	echo new_cell("Summary", "String", "rowheader");
	echo new_cell("Status", "String", "rowheader");
	echo new_cell("Ops Change?", "String", "rowheader");
	echo new_cell("Mon Change?", "String", "rowheader");
	echo new_cell("Description", "String", "rowheader");
	echo end_row();
	foreach($investigations as $inv)
	{
		$id = $inv["ticket_id"];
		$sheet = "inv $id";
		$investigations[$id]["sheet"] = $sheet;
		echo start_row();
		echo new_cell($id, "Number", "", array("HRef"=>"#'$sheet'!A1"));
		echo new_cell($inv["action"], "String");
		echo new_cell($inv["summary"], "String");
		echo new_cell("", "String");
		echo new_cell("", "String");
		echo new_cell("", "String");
		echo new_cell($inv["notes"], "String");
		echo end_row(); 
	}
	echo start_row();
	echo end_row();
	echo end_table();
	echo end_sheet();
        // End of text for Investigations sheet    

	// work out sheet names
	$query = 'SELECT "Language" as language, "Net" as abbr FROM languages';
	$result = pg_query($dbconn, $query) or die('Query failed: ' . pg_last_error());
	$languages = array();
	for($i=0; $i<pg_num_rows($result); $i++) {
		$line = pg_fetch_array($result, null, PGSQL_ASSOC);
		$languages[$line["language"]] = $line["abbr"];
	}
	pg_free_result($result);
	//Start of original text
	echo start_sheet("summary");
	echo start_table();
	echo start_row().new_cell("Audibility Report for $month_name", "String", "s_bold").end_row();
	$dates->start = $start_date;
	$dates->stop = $stop_date;
	$dates->freeze = $freeze;
	$other = last_month($start_date, $month, $season);
	$q = get_times($other["season"], $other["month"]);
	$pdates->start = $q->start_date;
	foreach ($regions as $r)
	{
    		$scores = array();
		fetch_region_summary($dbconn, $scores, $r, $season, $dates, $map, "current");
		fetch_region_summary($dbconn, $scores, $r, $season, $pdates, $map, "previous");
		foreach($investigations as $inv)
		{
			$ta = $inv["ta_name"];
			$language=$inv["language"];
			$start=$inv["start"];
			$action=$inv["action"];
			$sheet=$inv["sheet"];
			if(isset($scores[$language][$ta][$start]))
			{
				$scores[$language][$ta][$start]["investigation_action"] = strtolower($action);
				$scores[$language][$ta][$start]["sheet"] = $sheet;
			}
		}
		$rs = new SummarySheet();
		$rs->show_summary($scores, $r->name, $season, $month, $start_date, $stop_date, $map);
	}
	echo end_table();
	echo end_sheet();
	foreach($investigations as $investigation) {
		$investigation["month_name"] = $month_name;
		$query = query_for_detail($language, $season, $ta, $start_date, $stop_date, $freeze, 'T');
		$score = show_detail($investigation, $start_date, $stop_date, $month_name, $freeze, fetch_query($query));
	}
	echo "</Workbook>";
	pg_close($dbconn);
?>
