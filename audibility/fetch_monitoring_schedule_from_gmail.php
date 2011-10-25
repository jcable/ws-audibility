<?php
$conn   = imap_open ("{imap.gmail.com:993/imap/ssl}INBOX", "wsaudibility@gmail.com", "trusted-steed", 0, 1);

$some   = imap_search($conn, 'ALL UNSEEN SUBJECT "BBC Monitoring Schedule"', SE_UID);
if(is_array($some)) {
 foreach($some as $uid) {
  $s = imap_fetchstructure($conn, $uid, FT_UID);
  foreach($s->parts as $i => $part) {
    if( $part->subtype == "VND.MS-EXCEL") {
	$body = imap_fetchbody($conn, $uid, $i+1, FT_UID);
	//$f = fopen($part->description, "wb");
	$f = fopen('/var/www/html/audibility/import/monitoring_schedule.xls', "wb");
	fwrite($f, base64_decode($body));
	fclose($f);
    }
  }
 }
}
?> 
