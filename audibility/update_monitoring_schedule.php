<?php

require_once("report_template_functions.php");

function insert_record($action)
{
	$query  = <<<EOS
INSERT INTO monitoring_schedule (
	bcstr, rms, ibb_language, start_time, stop_time, frequency, station,
	ibb_days, valid_from, valid_to
) VALUES (
	'$action->bcstr', '$action->rms', '$action->lang',
	'$action->start', '$action->stop', $action->frequency,
	'$action->station', '$action->days', 
	'$action->validfrom', '$action->validto'
)
EOS;
	$result = pg_query($query);
	if($result)
	{
		pg_free_result($result);
		return TRUE;
	}
	return FALSE;
}

function insert_record2($action)
{
	$query  = <<<EOS
INSERT INTO monitoring_schedule (
	bcstr, rms, ibb_language, start_time, stop_time, frequency, station,
	ibb_days, valid_from
) VALUES (
	'$action->bcstr', '$action->rms', '$action->lang',
	'$action->start', '$action->stop', $action->frequency,
	'$action->station', '$action->days', 
	'$action->validfrom'
)
EOS;
	$result = pg_query($query);
	if($result)
	{
		pg_free_result($result);
		return TRUE;
	}
	return FALSE;
}

function update_validto($action)
{
	$query  = <<<EOS
UPDATE monitoring_schedule SET valid_to='$action->validto'
WHERE bcstr='$action->bcstr' AND rms='$action->rms'
AND ibb_language='$action->lang' AND start_time='$action->start' AND stop_time='$action->stop'
AND frequency=$action->frequency AND station='$action->station' AND ibb_days='$action->days'
EOS;
//AND valid_from='$action->validfrom'
	$result = pg_query($query);
	if($result)
	{
		pg_free_result($result);
		return TRUE;
	}
	return FALSE;
}

function process_mon_record($action, $pass)
{
	switch($action->action)
	{
	case 'created':
	case 'changed':
		if($action->validfrom=='')
			$action->validfrom = $action->timestamp;
		if($pass==2)
			if($action->validto=='')
				return insert_record2($action);
			else
				return insert_record($action);
		else
			return TRUE;
	case 'original':
	case 'removed':
		if($action->validto=='')
			$action->validto = $action->timestamp;
		if($pass==1)
			return update_validto($action);
		else
			return TRUE;
	default:
		print $action->action."\n";
		return FALSE;
	}
}

function process_mon_msg($strdate, $html)
{
	$date = strftime('%Y-%m-%d', strtotime($strdate));
	$doc = new DOMDocument;
	if($html=="" || $doc->loadHTML($html)==FALSE)
		return FALSE;
	$tables = $doc->getElementsByTagName('table');
	foreach($tables as $table) {
		$rows = $table->getElementsByTagName('tr');
		foreach($rows as $row) {
			$cols = $row->getElementsByTagName('td');
			if($cols->length<12)
			{
				#print $row->textContent;
				continue;
			}
			$start = $cols->item(4)->nodeValue;
			$stop = $cols->item(5)->nodeValue;
			$action = new stdClass();
			$action->rms = $cols->item(0)->nodeValue;
			$action->bcstr = $cols->item(1)->nodeValue;
			$action->lang = $cols->item(3)->nodeValue;
			$action->start = $start[0].$start[1].':'.$start[2].$start[3];
			$action->stop = $stop[0].$stop[1].':'.$stop[2].$stop[3];
			$action->frequency = $cols->item(6)->nodeValue;
			$action->station = preg_replace('/[^A-Za-z0-9]/', '', $cols->item(7)->nodeValue);
			$action->days = $cols->item(8)->nodeValue;
			$action->validfrom = $cols->item(9)->nodeValue;
			$action->validto = $cols->item(10)->nodeValue;
			$action->action = $cols->item(11)->textContent;
			$action->timestamp = $date;
			$actions[]= $action;
		}
	}
	$ok = TRUE;
	foreach($actions as $action)
	{
		$ok &= process_mon_record($action, 1);
	}
	foreach($actions as $action)
	{
		$ok &= process_mon_record($action, 2);
	}
	return $ok;
}

$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');
$mbox = imap_open ("{imap.gmail.com:993/imap/ssl}INBOX",
		"wsaudibility@gmail.com", "trusted-steed", 0, 1)
     or die("can't connect: " . imap_last_error());

$n = imap_num_msg($mbox);
if($n>0)
{
	$result = imap_fetch_overview($mbox,"1:{$n}",0);
	$imported = array();
	foreach ($result as $overview)
	{
	if(isset($overview->from) && $overview->from=='schedmanager@ibb.his.com')
	{
		$body = imap_body($mbox, $overview->msgno)
		or print "$overview->msgno is bad\n";
		$result = process_mon_msg($overview->date, $body);
		if($result)
			$imported[] = $overview->msgno;
	}
	}

	imap_mail_move($mbox, join(',', $imported), 'Imported');
}

imap_close($mbox);
pg_close($dbconn);
?>
