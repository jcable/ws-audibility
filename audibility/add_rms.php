<HTML>
<HEAD>
<TITLE>Add Monitoring Station</TITLE>
<?php
require_once('report_template_functions.php');
$dbconn = db_login('wsdata', 'PG_USER', 'PG_PASSWORD');
?>
</HEAD>
<BODY>
<H1>Add Monitoring Station</H1>
<FORM>
<table>
<tr><td><label for="stn">Station Name: </label></td><td><INPUT TYPE='text' NAME='stn'></td></tr>
<tr><td><label for="stn">RMS Name: </label></td><td><INPUT TYPE='text' NAME='rms'></td></tr>
<tr><td><label for="geonameid">geoname id: </label></td><td><INPUT TYPE='text' NAME='geonameid'> (look up at <a href="http://www.geonames.org">www.geonames.org</a>)</td></tr>
</table>
</FORM>
</TABLE>
<?php
$query = "SELECT DISTINCT \"Language\" FROM languages";
$result = pg_query($dbconn, $query) or die('Query failed: '. pg_last_error()." for $query");
$languages = pg_fetch_all($result);
pg_free_result($result);
$query = "SELECT name FROM target_areas";
$result = pg_query($dbconn, $query) or die('Query failed: '. pg_last_error()." for $query");
$target_areas = pg_fetch_all($result);
pg_free_result($result);
$stn='';
?>
<TABLE>
<?php
foreach($target_areas as $row) {
	$ta = $row['name'];
	print "<TR><TD>$ta</TD></TR>";
	foreach($languages as $row) {
		$language = $row['Language'];
                print "<TD>$language</TD><TD>";
                print "<input type='radio' name='${language}_$ta' value='T'/>in target";
                print "<input type='radio' name='${language}_$ta' value='F'/>out of target";
                print "<input type='radio' name='${language}_$ta' value=' ' checked/>don't count";
                print "</TD></TR>\n";
	}
}
?>
</TABLE>
</FORM>
</BODY>
<?php
pg_close($dbconn);
?>
</HTML>
