<?php
require_once("report_template_functions.php");

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
			$filename = $parameter->value;
		}
	    }
	    if( $part->subtype == "VND.MS-EXCEL" || $part->subtype == "OCTET-STREAM") {
		$body = imap_fetchbody($conn, $uid, $i+1, FT_UID);
		$f = fopen("/var/www/html/audibility/import/$filename", "wb");
		fwrite($f, base64_decode($body));
		fclose($f);
		print $fileaame;
	    }
	}
  }
 }
}
?> 
