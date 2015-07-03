<?php

function make_detail_url($language, $ta, $start, $freeze, $score, $target, $month_date)
{
        $url = "detail.php?ta=$ta&language=$language&date=$month_date";
        $url .= "&start=$start&target=".$target."&score=$score";
        if($freeze!="")
                $url .="&freeze=$freeze";
        return $url;
}

function readConfig()
{
  $f = fopen('/etc/default/audibility', 'r');
  $map = array();
  while(!feof($f)) {
    $line = fgets($f);
    if(strpos($line, '=') == false)
	continue;
    list($key, $value) = explode("=", $line);
    $map[$key] = trim($value);
  }
  return $map;
}

function db_login($db, $u, $p)
{
  $kw = readConfig();
  $user = $kw[$u];
  $password = $kw[$p];
  $dbconn = pg_connect("host=localhost dbname=$db user=$user password=$password")
   or die('Could not connect: ' . pg_last_error());
  return $dbconn;
}

function pdo_login($db, $u, $p)
{
  $kw = readConfig();
  $user = $kw[$u];
  $password = $kw[$p];
  try {
    $dbh = new PDO("pgsql:host=localhost;dbname=$db", $user, $password);
  } catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "<br/>";
    exit(1);
  }
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $dbh;
}

abstract class Region
{
	abstract protected function show_language_header($region_name, $lang);
	abstract protected function show_language_trailer($icons);
	abstract protected function show_language_target_area_header($region_name, $lang, $ta, $map);
	abstract protected function show_language_target_area_trailer($icons);
	abstract protected function make_url($season, $month, $lang, $ta, $start, $freeze, $score, $s);
	abstract protected function show_row($start, $s, $class, $url);

	public function show_summary($scores, $region_name, $season, $month, $start_date, $stop_date, $map)
	{
	  /* now show the table */
	  $freeze = $scores["freeze"];
	  ksort($scores);
	  foreach($scores as $lang =>$scores_for_ta)
	  {
	    if($lang == "freeze")
		continue;
	    $licons = array();
	    $this->show_language_header($region_name, $lang);
	    foreach($scores_for_ta as $ta =>$scores_for_time)
	    {
	      $icons = array();
	      $this->show_language_target_area_header($region_name, $lang, $ta, $map);
	      foreach($scores_for_time as $start => $s)
	      {
			$score = in_or_out_score($s["avg"], $s["out_avg"]);
			$pscore = in_or_out_score($s["pavg"], $s["pout_avg"]);
			if($score == 0)
			  $class = "nodatarow";
		    else if($score >= $s["target"])
		      $class = "goodrow";
		    else if($s["target"] - $score >= 0.6)
		      $class = "verybadrow";
		    else if($pscore < $s["ptarget"])
		      $class = "badtwicerow";
		    else
		      $class = "badrow";
		    $url = $this->make_url($season, $month, $lang, $ta, $start, $freeze, $score, $s);
		    $icons += $this->show_row($start, $s, $class, $url);
	      }
	      $this->show_language_target_area_trailer($icons);
	      $licons += $icons;
	    }
	    $this->show_language_trailer($licons);
	  }
	}
}

function flush_to_screen()
{
  flush();
}

function fetch_query($query)
{
  global $dbconn;
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error().": $query");
  $obs = pg_fetch_all($result);
  pg_free_result($result);
  return $obs;
}

function get_season($year, $month)
{
  date_default_timezone_set('UTC');
  $d = mktime(0, 0, 0, 1, 1, $year);
  $seasons = array("April" =>"A", "May" =>"A", "June" =>"A", "July" =>"A",
 			 "August" =>"A", "September" =>"A", "October" =>"A",
			 "November" =>"B", "December" =>"B", "January" =>"B", "February" =>"B", "March" =>"B",
    '01'=>"B", '02'=>"B", '03'=>"B", '04'=>"A", '05'=>"A", '06'=>"A", '07'=>"A", '08'=>"A", '09'=>"A", '10'=>"A", '11'=>"B", '12'=>"B",
	1=>"B", 2=>"B", 3=>"B", 4=>"A", 5=>"A", 6=>"A", 7=>"A", 8=>"A", 9=>"A", 10=>"A", 11=>"B", 12=>"B"
  );
  $season = $seasons[$month];
  if($season == '')
  {
	$x = $month+0;
	  $season = $seasons[$x];
  }
  $dy = array("April" =>0, "May" =>0, "June" =>0, "July" =>0, "August" =>0,
 		 "September" =>0, "October" =>0, "November" =>0,
		 "December" =>0, "January" =>-1, "February" =>-1, "March" =>-1,
	'01'=>-1, '02'=>-1, '03'=>-1, '04'=>0, '05'=>0, '06'=>0, '07'=>0, '08'=>0, '09'=>0, '10'=>0, '11'=>0, '12'=>0,
	1=>-1, 2=>-1, 3=>-1, 4=>0, 5=>0, 6=>0, 7=>0, 8=>0, 9=>0, 10=>0, 11=>0, 12=>0
	);
  $y = date("y", $d) + $dy[$month];
  if($y < 10)
    $y = "0".$y;
  return $season.$y;
}

function
season_month($season, $month_name)
{
  $Amonths = array("April" =>1, "May" =>2, "June" =>3, "July" =>4, "August" =>5, "September" =>6, "October" =>7,
	'04'=>1, '05'=>2, '06'=>3, '07'=>4, '08'=>5, '09'=>6, '10'=>7,
	4=>1, 5=>2, 6=>3, 7=>4, 8=>5, 9=>6, 10=>7);
  $Bmonths = array("November" =>1, "December" =>2, "January" =>3, "February" =>4, "March" =>5,
	'11'=>1, '12'=>2, '01'=>3, '02'=>4, '03'=>5,
	11=>1, 12=>2, 1=>3, 2=>4, 3=>5);
  if($season[0] == "A")
    $month = $Amonths[$month_name];
  else
    $month = $Bmonths[$month_name];
  return $month;
}

function
season_dates($season)
{
  $y = substr($season, 1)+2000;
  $dates = array();
  $y1 = $y; $y2=$y+2;
  $dates["all"] = explode("\n", `zdump -v -c $y1,$y2 Europe/London`);
  if($season[0] == "A")
  {
	  $dates["start"] = $dates["all"][3];
	  $dates["end"] = $dates["all"][4];
  }
  else
  {
	  $dates["start"] = $dates["all"][5];
	  $dates["end"] = $dates["all"][6];
  }
  $s = explode(' ', $dates["start"]);
  $e = explode(' ', $dates["end"]);
  $months = array("Jan" =>1, "Feb" =>2, "Mar" =>3, "Apr" =>4, "May" =>5, "Jun" =>6,
		"Jul" =>7, "Aug" =>8, "Sep" =>9, "Oct" => 10, "Nov" =>11, "Dec" =>12);
  $dates["start"] = $s[6].'-'.$months[$s[3]].'-'.$s[4];
  $dates["end"] = $e[6].'-'.$months[$e[3]].'-'.$e[4];
  return $dates;
}

function
query_for_month($language, $target_area, $season, $start_date, $stop_date, $freeze_date, $status)
{

  // this sub query selects the observations for the month, language and target area
  $obs_query = "SELECT ob.frequency, ob.\"date\", ob.\"time\", ob.o"
              ." FROM parsed_observations ob JOIN ms_use ms USING (stn)"
              ." WHERE status='$status'"
              ." AND ob.\"date\" BETWEEN '$start_date' AND '$stop_date'"
 	      ." AND ob.\"language\" = '$language' AND ms.\"language\" = '$language'";
  if($freeze_date != "")
    $obs_query .= " AND ob.row_timestamp <= TIMESTAMP '$freeze_date'";

  // this sub query returns the sla targets for the ta, language and month
  $sla_query =
    "SELECT start_time, min(target) AS target, primary_frequency, secondary_frequency FROM sla"
    ." WHERE season = '$season'"." AND target_area = '$target_area'"
    ." AND valid_from <= '$stop_date'"
    ." AND(valid_to IS NULL OR valid_to >= '$start_date')"
    ." AND \"language\" = '$language'"
    ." GROUP BY start_time, primary_frequency, secondary_frequency";

  // this sub query collects the obervations into the sla bins and finds the max score for each bin
  return
    " SELECT s.start_time, target, o.\"date\", max(o.o) AS o"
    ." FROM($sla_query) AS s LEFT JOIN($obs_query) AS o"
    ." ON o.\"time\" BETWEEN s.start_time AND(s.start_time + '00:30:00'::interval)"
    ." WHERE (o.frequency = ANY(s.primary_frequency) OR o.frequency = ANY(s.secondary_frequency))"
    ." GROUP BY s.start_time, s.target, o.\"date\"";
}

function
query_for_monthly_summary($language, $target_area, $season, $dates, $status)
{
  $start_date = $dates->start;
  $stop_date = $dates->stop;
  $freeze_date = $dates->freeze;

  $query = query_for_month($language, $target_area, $season, $start_date, $stop_date, $freeze_date, $status);

  // this sub query calculates the average score
  $query =
    "SELECT '$target_area' as target_area, '$language' as \"language\", start_time, target, round(avg(o), 1) AS score"
    ." FROM($query) AS av GROUP BY start_time, target"
    ." ORDER BY start_time";
	//print "<PRE>$query</PRE>";
 return $query;
}

function
create_saved_monthly_summaries_table()
{
  global $dbconn;
  $query =
    "CREATE TABLE saved_monthly_summaries("
	."\"language\" text, month date, target_area text,"
	." start_time time, target float, score float, out_score float, freeze_date date".
    ")";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
}

function dump_q($query, $title)
{
  global $dbconn;
  print "<h3>$title</h3>";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error().": $query");
  for($i=0; $i<pg_num_rows($result); $i++) {
	$line = pg_fetch_array($result, null, PGSQL_ASSOC);
	print_r($line);
	print "<p>";
  }
  pg_free_result($result);
}

function count_rows($dbconn,$tab)
{
  $result = pg_query($dbconn, "SELECT COUNT(*) AS n FROM $tab");
  $line = pg_fetch_array($result, null, PGSQL_ASSOC);
  print "<p>$tab has ".$line['n']." rows<p>\n";
  pg_free_result($result);
}

function
save_monthly_aal_summary($dbconn, $season, $dates)
{
  $start_date = $dates->start;
  $stop_date = $dates->stop;
  $freeze_date = $dates->freeze;
  $result = pg_query($dbconn, "DELETE FROM aal_monthly_summaries WHERE month='$start_date'") or die('Query failed: '.pg_last_error());
  $query = <<<"EOD"
INSERT INTO aal_monthly_summaries (target_area,service,start_time,month,target,freeze_date,aal_score,other_score)
	 SELECT a.target_area,a.service, a.start as start_time,
	 '$start_date'::date as month, avg(target) as target, '$freeze_date'::date,
	 round(20*avg(CASE WHEN o.o IS NULL THEN 0 ELSE o.o END)),
	 round(20*avg(CASE WHEN q.o IS NULL THEN 0 ELSE q.o END))
	 FROM aal_mon a
	 JOIN parsed_observations o
	 ON o.stn = any(a.aal_stn) AND o.language = any(a.language)
	 JOIN parsed_observations q
	 ON q.stn = any(a.other_stn) AND q.language = any(a.language)
	 WHERE o.date BETWEEN '$start_date' AND '$stop_date'
	 AND o.row_timestamp <= '$freeze_date'::timestamp
	 AND o.time BETWEEN a.start AND (a.start + '00:30:00'::interval)
	 AND q.date BETWEEN '$start_date' AND '$stop_date'
	 AND q.row_timestamp <= '$freeze_date'::timestamp
	 AND q.time BETWEEN a.start AND (a.start + '00:30:00'::interval)
	 GROUP by target_area,service,start
EOD;
	//$result = pg_query($dbconn, $query);
	//pg_free_result($result);
    $result = pg_query("CREATE TEMP TABLE p AS SELECT round(20*avg(CASE WHEN o IS NULL THEN 0 ELSE o END)) AS o, target_area, service, start FROM parsed_observations o JOIN aal_mon a ON o.stn = any(a.aal_stn) AND o.language = any(a.language) WHERE o.date BETWEEN '$start_date' AND '$stop_date' AND o.row_timestamp <= '$freeze_date'::timestamp AND o.time BETWEEN a.start AND (a.start + '00:30:00'::interval) GROUP by target_area,service,start");
    pg_free_result($result);
echo "created AAL scores\n";
    $result = pg_query("CREATE TEMP TABLE q AS SELECT round(20*avg(CASE WHEN o IS NULL THEN 0 ELSE o END)) AS o, target_area, service, start FROM parsed_observations o JOIN aal_mon a ON o.stn = any(a.other_stn) AND o.language = any(a.language) WHERE o.date BETWEEN '$start_date' AND '$stop_date' AND o.row_timestamp <= '$freeze_date'::timestamp AND o.time BETWEEN a.start AND (a.start + '00:30:00'::interval) GROUP by target_area,service,start");
    pg_free_result($result);
echo "created other scores\n";
    $query = <<<"EOD"
INSERT INTO aal_monthly_summaries (target_area,service,start_time,month,target,freeze_date,aal_score,other_score)
	 SELECT a.target_area, a.service, a.start as start_time,
	 '$start_date'::date as month, a.target, '$freeze_date'::date, p.o, q.o
	 FROM aal_mon a
	 LEFT JOIN p ON a.service=p.service AND a.target_area=p.target_area AND a.start=p.start
	 LEFT JOIN q ON a.service=q.service AND a.target_area=q.target_area AND a.start=q.start
EOD;
	$result = pg_query($dbconn, $query);
	pg_free_result($result);
  print "<br>created summaries<br>\n";
  flush(); ob_flush();
}

function
save_monthly_aal_summary_for_timeslot($dbconn, $target_area, $service, $service_start_time, $slot_start_time, $start_date, $stop_date, $freeze)
{
	$query = "INSERT INTO aal_monthly_summaries (target_area,service,start_time,month,target,freeze_date,score)"
	." SELECT a.target_area,a.service,'$slot_start_time'::time as start_time,"
	." '$start_date'::date, target, '$freeze'::date, round(20*avg(CASE WHEN o IS NULL THEN 0 ELSE o END)) as score"
	." FROM aal_mon a LEFT JOIN parsed_observations o"
	." ON o.stn = any(a.stn) AND o.language = any(a.language)"
	." WHERE a.aal=true"
	." AND a.start = '$service_start_time'::time"
	." AND a.service ='$service'"
	." AND a.target_area='$target_area'"
	." AND o.date BETWEEN '$start_date' AND '$stop_date'"
	." AND o.time BETWEEN '$slot_start_time'::time AND ('$slot_start_time'::time + '00:30:00'::interval)"
	." GROUP by target_area,service,start";
	$result = pg_query($dbconn, $query);
	pg_free_result($result);
}

function
save_monthly_aal_summary1($dbconn, $season, $dates)
{
  $start_date = $dates->start;
  $stop_date = $dates->stop;
  $freeze_date = $dates->freeze;
  $result = pg_query($dbconn, "DELETE FROM aal_monthly_summaries WHERE month='$start_date'") or die('Query failed: '.pg_last_error());
  $aal_query =
    'SELECT "Target Area", "Service", "Start", "End", "Score", "Network"'
    .' FROM aal_bsr b'
    .' JOIN aal_reference r ON b."Service Grade" = r."Level"'
    ." WHERE \"Season\" = '$season'";
  $result = pg_query($dbconn, $aal_query) or die('Query failed: '.pg_last_error());
  $groups = pg_fetch_all($result);
  pg_free_result($result);
  if($groups == FALSE) {
    print "No service grades defined for $season<br>\n";
    return;
  }
  $step = new DateInterval("PT30M");
  //print_r($groups);
  foreach($groups as $group) {
    try {
        $target_area = $group['Target Area'];
        $service = $group['Service'];
        $start_time = $group['Start'];
        $score = $group['Score'];
	$start = DateTime::createFromFormat('G:i:sO', $group['Start']);
	$end = DateTime::createFromFormat('G:i:sO', $group['End']);
	for($t = $start; $t<$end; $t->add($step)) {
		$duration = $t->diff($end);
		$ss = $duration->s+60*($duration->i+60*$duration->h);
		if($ss>=60) {
			$slot_start = $t->format('G:i:s');
			print "<br/>creating summary for $service in $target_area at $slot_start.\n";
			save_monthly_aal_summary_for_timeslot($dbconn, 
				$target_area, $service, $start_time, $slot_start, $score,
                		$start_date, $stop_date, $freeze_date);
		}
		else {
			print "<br/>Not creating summary for $service in $target_area at $slot_start because "
				. $duration->format("%H:%I") . " is too short a duration.\n";
		}
	}
    } catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
  }
  print "<br>created summaries<br>\n";
  flush(); ob_flush();
}

/*
                 target_area                  | language | start_time | target |   month    | score | out_score | freeze_date
----------------------------------------------+----------+------------+--------+------------+-------+-----------+-------------
 Bangladesh Bhutan East India Nepal Sri Lanka | Bengali  | 00:30:00   |    3.6 | 2011-03-01 |     4 |           | 2011-10-22
*/
function
save_monthly_summary_for_timeslot($dbconn, $target_area, $language, $start_time, $target, $season,$start_date,$stop_date,$freeze)
{
	$obs_query = query_for_detail($language, $season, $target_area, $start_time, $start_date, $stop_date, $freeze, 'T');

	$query = "INSERT INTO saved_monthly_summaries"
		." (target_area, language, start_time, target, month, score, freeze_date)"
		." SELECT '$target_area','$language','$start_time',$target,'$start_date', round(avg(o),1) AS score, '$freeze'"
		." FROM (SELECT \"date\", max(o) AS o FROM ($obs_query) AS o GROUP BY \"date\") AS d";

	$result = pg_query($dbconn, $query);
	pg_free_result($result);

	$obs_query = query_for_detail($language, $season, $target_area, $start_time, $start_date, $stop_date, $freeze, 'F');

	$query = "UPDATE saved_monthly_summaries SET out_score = o"
		." FROM (SELECT round(avg(o),1) AS o"
		." FROM (SELECT \"date\", max(o) AS o FROM ($obs_query) AS o GROUP BY \"date\") AS d) AS a"
		." WHERE \"language\"='$language'"
		." AND target_area='$target_area'"
		." AND start_time = '$start_time'"
		." AND month = '$start_date'";

	$result = pg_query($dbconn, $query);
	pg_free_result($result);
}

function
save_monthly_summary($dbconn, $season, $dates)
{
  $start_date = $dates->start;
  $stop_date = $dates->stop;
  $freeze_date = $dates->freeze;
  $sla_query =
    "SELECT target_area, language, start_time, max(target) AS target"
    ." FROM sla"
    ." WHERE season = '$season'"
    ." AND valid_from <= '$stop_date'"
    ." AND(valid_to IS NULL OR valid_to >= '$start_date')"
    ." GROUP BY target_area, language, start_time";
  $result = pg_query($dbconn, $sla_query) or die('Query failed: '.pg_last_error());
  $groups = pg_fetch_all($result);
  pg_free_result($result);

  if($groups == FALSE) {
    print "No target levels defined for $season<br>\n";
    return;
  }

  $query = "DELETE FROM saved_monthly_summaries WHERE month = '$start_date'";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);

  foreach($groups as $group) {
	$target_area = $group['target_area'];
	$language = $group['language'];
	$start_time = $group['start_time'];
	$target = $group['target'];
	print "creating summary for $language in $target_area at $start_time<br>\n";
	save_monthly_summary_for_timeslot($dbconn, $target_area, $language, $start_time, $target,
		$season,$start_date,$stop_date,$freeze_date);
  }
}

function
save_monthly_summary1($dbconn, $season, $dates)
{
  $start_date = $dates->start;
  $stop_date = $dates->stop;
  $freeze_date = $dates->freeze;

  $sla_query =
    //"SELECT target_area, language, start_time, min(target) AS target,"
    "SELECT target_area, language, start_time, max(target) AS target"
	//.", primary_frequency, secondary_frequency"
	." FROM sla"
    ." WHERE season = '$season'"
    ." AND valid_from <= '$stop_date'"
    ." AND(valid_to IS NULL OR valid_to >= '$start_date')"
    ." GROUP BY target_area, language, start_time"
    //." , start_time, primary_frequency, secondary_frequency"
	;

  $query = "CREATE TEMPORARY TABLE m_sla AS $sla_query";
  print "\n<p>".date("H:i:s")." Creating SLA/Period List"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  //count_rows($dbconn,"m_sla");
  $query = "CREATE INDEX m_sla_i ON m_sla(start_time,language)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);

  $query = "CREATE TEMPORARY TABLE m_sum (LIKE saved_monthly_summaries)";
  flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  $query = "INSERT INTO m_sum SELECT target_area, language, start_time, target,"
  ." TO_DATE('$start_date','YYYY-MM-DD') AS month,"
  ." NULL as score, NULL AS out_score,"
  ." TO_DATE('$freeze_date','YYYY-MM-DD') AS freeze_date FROM m_sla";
  print "\n<p>".date("H:i:s")." Create structure for scores"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  //count_rows($dbconn,"m_sum");

  $obs_query =
    "SELECT (extract(hour from time)||':'||30*floor(extract(minute from time)/30))::time AS start_time,"
    ." language, frequency, date, time, stn, o::double precision"
    ." FROM parsed_observations ob"
    ." WHERE ob.\"date\" BETWEEN '$start_date' AND '$stop_date'";
	//$obs_query .= " AND language='Farsi'";
  if($freeze_date != "")
    $obs_query .= " AND ob.row_timestamp <= TIMESTAMP '$freeze_date'";
  $query = "CREATE TEMPORARY TABLE m_obs AS $obs_query";
  print "\n<p>".date("H:i:s")." Creating table of observations";
  flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  $query = "CREATE INDEX m_obs_i ON m_obs(stn,language)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  //count_rows($dbconn,"m_obs");

  $in_obs_query =
    "SELECT target_area, language, start_time, frequency, date, time, stn, o"
    ." FROM m_obs ob JOIN ms_use USING (stn,language) WHERE status='T'";
  $query = "CREATE TEMPORARY TABLE m_in_obs AS $in_obs_query";
  print "\n<p>".date("H:i:s")." Creating table of in-target observations"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  $query = "CREATE INDEX m_in_obs_i ON m_in_obs(stn,language)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  $query = "CREATE INDEX m_in_obs_j ON m_in_obs(start_time,language)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  //count_rows($dbconn,"m_in_obs");

  $s_in_query =
    " SELECT s.target_area as sla_ta, s.language, s.start_time,"
    ." frequency, stn, o.target_area as o_ta, o.date, o"
    ." FROM m_sla AS s LEFT JOIN m_in_obs AS o"
	." USING(start_time,language)"
    //." ON o.\"time\" BETWEEN s.start_time AND(s.start_time + '00:30:00'::interval)"
    //." AND s.\"language\" = o.\"language\""
	//." WHERE o.frequency = ANY(s.primary_frequency) OR o.frequency = ANY(s.secondary_frequency)"
    ." ORDER BY s.target_area, \"language\", start_time";

  $query = "CREATE TEMPORARY TABLE m_s_in AS $s_in_query";
  print "\n<p>".date("H:i:s")." Creating table of in target observations by slot"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  $query = "CREATE INDEX m_s_in_i ON m_s_in(sla_ta, language, start_time, date)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);

  $in_query =
    "SELECT sla_ta as target_area, language, start_time, avg(o)::double precision AS score"
    ." FROM("
        ." SELECT sla_ta, language, start_time, date, max(o) AS o"
        ." FROM m_s_in AS s WHERE sla_ta = o_ta"
        ." GROUP BY sla_ta, language, start_time, date"
    ." ) AS av GROUP BY sla_ta, language, start_time ORDER BY language, start_time";

  $query = "CREATE TEMPORARY TABLE m_in AS $in_query";
  print "\n<p>".date("H:i:s")." Creating table of in target scores"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error().": $query");
  pg_free_result($result);

  //count_rows($dbconn,"m_in");

  //dump_q("SELECT * FROM m_sum WHERE language='Farsi'", "summary before adding in scores");
  $query = "UPDATE m_sum SET score = round(s.score::numeric,1) FROM m_in s"
  ." WHERE"
  ." m_sum.target_area=s.target_area"
  ." AND m_sum.language=s.language"
  ." AND m_sum.start_time=s.start_time";
  print "\n<p>".date("H:i:s")." Copying in target scores to monthly summary"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);

  $out_obs_query =
    "SELECT target_area, language, start_time, frequency, date, time, stn, o"
    ." FROM m_obs ob JOIN ms_use USING (stn,language) WHERE status='F'";
  $query = "CREATE TEMPORARY TABLE m_out_obs AS $out_obs_query";
  print "\n<p>".date("H:i:s")." Creating table of out of target observations"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  $query = "CREATE INDEX m_obs_out_i ON m_out_obs(time,language)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  $query = "CREATE INDEX m_out_obs_j ON m_out_obs(start_time,language)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);

  $s_out_query =
    " SELECT s.target_area as sla_ta, s.language, s.start_time,"
    ." frequency, stn, o.target_area as o_ta, o.date, o"
    ." FROM m_sla AS s LEFT JOIN m_out_obs AS o"
	." USING(start_time,language)"
    //." ON o.time BETWEEN s.start_time AND(s.start_time + '00:30:00'::interval)"
    //." AND s.language = o.language"
	//." WHERE o.frequency = ANY(s.primary_frequency) OR o.frequency = ANY(s.secondary_frequency)"
    ." ORDER BY s.target_area, language, start_time";

  $query = "CREATE TEMPORARY TABLE m_s_out AS $s_out_query";
  print "\n<p>".date("H:i:s")." Creating table of out-target observations by slot"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  $query = "CREATE INDEX m_s_out_i ON m_s_out(sla_ta, language, start_time, date)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);

  $out_query =
    "SELECT sla_ta as target_area, language, start_time, avg(o)::double precision AS score"
    ." FROM("
        ." SELECT sla_ta, language, start_time, date, max(o) AS o"
        ." FROM m_s_out AS s WHERE sla_ta = o_ta"
        ." GROUP BY sla_ta, language, start_time, date"
    ." ) AS av GROUP BY sla_ta, language, start_time ORDER BY language, start_time";

  $query = "CREATE TEMPORARY TABLE m_out AS $out_query";
  print "\n<p>".date("H:i:s")." Creating table of out of target scores"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error().": $query");
  pg_free_result($result);

  //count_rows($dbconn,"m_out");

  //dump_q("SELECT * FROM m_in", "In scores");
  //dump_q("SELECT * FROM m_sum WHERE language='Farsi'", "summary after adding out scores");
  //dump_q("SELECT * FROM m_out", "Out scores");

  $query = "UPDATE m_sum SET out_score = round(s.score::numeric,1) FROM m_out s"
  ." WHERE"
  ." m_sum.target_area=s.target_area"
  ." AND m_sum.language=s.language"
  ." AND m_sum.start_time=s.start_time";
  print "\n<p>".date("H:i:s")." Copying out of target scores to monthly summary"; flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);

  $query = "DELETE FROM saved_monthly_summaries WHERE month = '$start_date'";
  flush_to_screen(); 
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);

  //count_rows($dbconn,"m_sum");

  $query = "INSERT INTO saved_monthly_summaries SELECT * from m_sum ORDER BY target_area,language,start_time";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  pg_free_result($result);
  print "\n<p>".date("H:i:s")." Done</br>\n";
  flush_to_screen(); 
}

function
fetch_region_summary($dbconn, &$scores, $region, $season, $dates, $map, $which)
{
  global $dbconn;
	if($which=="current")
		$is_current = true;
	else
		$is_current = false;

	$freeze = "";
	$query = "SELECT * FROM ta_regions WHERE region = '".$region->name."'";
	$result0 = pg_query($dbconn, $query) or die('Query failed: ' . pg_last_error().": $query");
	for($l=0; $l<pg_num_rows($result0); $l++)
	{
		$line = pg_fetch_array($result0, null, PGSQL_ASSOC);
		$target_area = $line["target_area"];
		$lang = $line["language"];
		$query = "SELECT * FROM saved_monthly_summaries"
				." WHERE month = '$dates->start' AND target_area = '$target_area'"
				." AND \"language\" = '$lang'"
				." ORDER BY start_time";
		$result = pg_query($dbconn, $query) or die('Query failed: ' . pg_last_error().": $query");
		for($i=0; $i<pg_num_rows($result); $i++)
		{
			$line = pg_fetch_array($result, null, PGSQL_ASSOC);
			if($is_current)
			{
				$o = array("target"=>$line["target"], "avg"=>$line["score"], "out_avg" => $line["out_score"]);
			}
			else
			{
				$o = array("ptarget"=>$line["target"], "pavg"=>$line["score"], "pout_avg" => $line["out_score"]);
			}
			$start = $line["start_time"];
			$freeze = $line["freeze_date"];
			if(isset($scores[$lang][$target_area][$start]))
				$scores[$lang][$target_area][$start] = array_merge($scores[$lang][$target_area][$start], $o);
			else
				$scores[$lang][$target_area][$start] = $o;
		}
		pg_free_result($result);
	}
	$scores["freeze"] = $freeze;
}

function
create_region_summary($region, $season, $dates, $map)
{
  global $dbconn;
  $scores = array();

  $result = pg_query($dbconn, "SELECT \"Language\" FROM languages") or die('Query failed: '.pg_last_error());
  $languages = pg_fetch_all($result);
  pg_free_result($result);

  foreach($region->tas as $ta)
  {
    $target_area = $map[$ta]->name;
    foreach($languages as $language)
    {
      $query = query_for_monthly_summary($language, $target_area, $season, $dates, 'T');
      unset($result);
      $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
      for($i = 0; $i < pg_num_rows($result); $i++)
      {
		$line = pg_fetch_array($result, null, PGSQL_ASSOC);
		$start = $line["start_time"];
		$lang = $line["language"];
		$scores[$lang][$ta][$start]["avg"] = $line["score"];
		$scores[$lang][$ta][$start]["target"] = $line["target"];
      }
      pg_free_result($result);

      unset($result);
      $query = query_for_monthly_summary($language, $target_area, $season, $dates, 'F');
      $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
      for($i = 0; $i < pg_num_rows($result); $i++)
      {
		$line = pg_fetch_array($result, null, PGSQL_ASSOC);
		$start = $line["start_time"];
		$lang = $line["language"];
		$avg = $line["score"];
		$scores[$lang][$ta][$start]["out_avg"] = $avg;
      }
      pg_free_result($result);
    }
  }
  return $scores;
}

function in_or_out_score($in, $out)
{
	if(is_numeric($in))
	{
		$score = $in;
	}
	else
	{
		if(is_numeric($out))
	    {
			$score = $out;
		}
		else
		{
		    $score = 0;
		}
	}
	return $score;
}


function
show_region_summary($scores, $region_name, $season, $month, $start_date, $stop_date, $map)
{

  /* now show the table */
  $freeze = $scores["freeze"];
  ksort($scores);
  foreach($scores as $lang =>$scores_for_ta)
  {
    if($lang == "freeze")
	continue;
    $licons = array();
    show_language_header($region_name, $lang);
    foreach($scores_for_ta as $ta =>$scores_for_time)
    {
      $icons = array();
      show_language_target_area_header($region_name, $lang, $ta, $map);
      foreach($scores_for_time as $start => $s)
      {
	if(array_key_exists("target", $s))
	{
	  	$score = in_or_out_score($s["avg"], $s["out_avg"]);
	  	$pscore = in_or_out_score($s["pavg"], $s["pout_avg"]);
		if($score == 0)
		  $class = "nodatarow";
	    else if($score >= $s["target"])
	      $class = "goodrow";
	    else if($s["target"] - $score >= 0.6)
	      $class = "verybadrow";
	    else if($pscore < $s["ptarget"])
	      $class = "badtwicerow";
	    else
	      $class = "badrow";
	    $url = make_detail_url($lang, $ta, $start, $freeze, $score, $s["target"], $stop_date);
	    $icons += show_row($start, $s, $class, $url);
        }
      }
      show_language_target_area_trailer($icons);
      $licons += $icons;
    }
    show_language_trailer($licons);
  }
}

function
get_season_dates($season)
{
  $half = substr($season,0,1);
  $year = substr($season,1);
  $start_date = gmmktime(0,0,0,(($half=="A")?6:12),1,2000+$year);
  $timezone = new DateTimeZone("Europe/London");
  $tt = 0;
  $stop_date = 0;
  foreach($timezone->getTransitions() as $t)
  {
	$ts = $t["ts"];
	if($ts<$start_date)
	{
		if($ts>$tt)
			$tt = $ts;
	}
	if($stop_date == 0 && $ts>$start_date)
	{
		$stop_date = $ts - 86400;
	}
  }
  $q->start_date = gmdate("Y-m-d", $tt);
  $q->stop_date = gmdate("Y-m-d", $stop_date);
  return $q;
}

function
get_times($season, $month)
{
  $half = substr($season,0,1);
  $year = substr($season,1);
  switch($month)
  {
  case 1:
  	$q->start_date = gmmktime(0,0,0,(($half=="A")?3:10)+$month,1,2000+$year);
	$timezone = new DateTimeZone("Europe/London");
	$tt = 0;
	foreach($timezone->getTransitions() as $t)
	{
		$ts = $t["ts"];
		if($ts<$q->start_date)
		{
			if($ts>$tt)
				$tt = $ts;
		}
  	}
	$q->start_date = $tt;
  	$q->stop_date = gmmktime(0,0,0,(($half=="A")?3:10)+$month+1,1,2000+$year) - 86400;
  	break;
  case 5:
  	$q->start_date = gmmktime(0,0,0,(($half=="A")?3:10)+$month,1,2000+$year);
    if($half=="B")
	{
		$timezone = new DateTimeZone("Europe/London");
		$q->stop_date = 0;
		foreach($timezone->getTransitions() as $t)
		{
			$ts = $t["ts"];
			if($ts>$q->start_date)
			{
				$q->stop_date = $ts - 86400;
				break;
			}
		}
	}
	else
	{
  		$q->stop_date = gmmktime(0,0,0,(($half=="A")?3:10)+$month+1,1,2000+$year) - 86400;
	}
  	break;
  case 7:
  	$q->start_date = gmmktime(0,0,0,(($half=="A")?3:10)+$month,1,2000+$year);
	$timezone = new DateTimeZone("Europe/London");
	$q->stop_date = 0;
	foreach($timezone->getTransitions() as $t)
	{
		$ts = $t["ts"];
		if($ts>$q->start_date)
		{
			$q->stop_date = $ts - 86400;
			break;
		}
  	}
	break;
  default:
  	$q->start_date = gmmktime(0,0,0,(($half=="A")?3:10)+$month,1,2000+$year);
  	$q->stop_date = gmmktime(0,0,0,(($half=="A")?3:10)+$month+1,1,2000+$year) - 86400;
  	break;
  }
  $q->month_name = gmdate("F Y", (15*86400)+$q->start_date);
  $q->start_date = gmdate("Y-m-d", $q->start_date);
  $q->stop_date = gmdate("Y-m-d", $q->stop_date);
  return $q;
}

function
get_regions()
{
  global $dbconn;
	$regions = array();
	$query = 'SELECT * FROM "Region Reporting Order" ORDER BY "order"';
	$result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
	for($i = 0; $i < pg_num_rows($result); $i++)
	{
		$line = pg_fetch_array($result, null, PGSQL_ASSOC);
		$reg = $line["order"] + 0;
		$name = $line["name"];
		$regions[$reg]->name = $name;
	}
	pg_free_result($result);
	return $regions;
}

function
get_target_areas()
{
  global $dbconn;
  $map = array();
  $query = 'SELECT id,name,abbr FROM target_areas';
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  for($i = 0; $i < pg_num_rows($result); $i++)
    {
      $line = pg_fetch_array($result, null, PGSQL_ASSOC);
      $id = trim($line["id"]);
      $name = trim($line["name"]);
      $map[$id] =(object) array('id' =>$id, 'name' =>$name, 'abbr' =>trim($line["abbr"]));
      $map[$name] =(object) array('id' =>$id, 'name' =>$name, 'abbr' =>trim($line["abbr"]));
    }
  pg_free_result($result);
  return $map;
}

function
get_regions_pdo($conn)
{
  $regions = array();
  $sql = 'SELECT * FROM "Region Reporting Order" ORDER BY "order"';
  foreach ($conn->query($sql) as $row) {
	$reg = $row["order"] + 0;
	$name = $row["name"];
	$regions[$reg]->name = $name;
  }
  return $regions;
}

function
get_target_areas_pdo($conn)
{
  $map = array();
  $sql = 'SELECT id,name,abbr FROM target_areas';
  foreach ($conn->query($sql) as $row) {
      $id = trim($row["id"]);
      $name = trim($row["name"]);
      $map[$id] =(object) array('id' =>$id, 'name' =>$name, 'abbr' =>trim($row["abbr"]));
      $map[$name] =(object) array('id' =>$id, 'name' =>$name, 'abbr' =>trim($row["abbr"]));
  }
  return $map;
}

function
report_and_correct_sla_errors()
{
  global $dbconn;
  $query =
    "UPDATE sla SET target_area =( SELECT to_ta FROM ta_translations WHERE from_ta = target_area)";
  $query .=
    "WHERE EXISTS( SELECT from_ta FROM ta_translations WHERE from_ta = target_area)";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  $n = pg_affected_rows($result);
  if($n > 0)
    {
      print "<H2>Errors found.</H2>";
      print "$n entries were using known mis-spellings of the target areas.";
      print "<p>These have been corrected<p>";
      $errors = true;
    }
  pg_free_result($result);
  $query =
    'SELECT DISTINCT target_area FROM sla EXCEPT SELECT name FROM target_areas';
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  if(pg_num_rows($result) > 0)
    {
      if(!isset($errors))
	print "<H2>Errors found.</H2>";
      print "The following Target Areas in the sla table are unknown:<p>";
      $sep = "";
      for($i = 0; $i < pg_num_rows($result); $i++)
	{
	  $line = pg_fetch_array($result, null, PGSQL_ASSOC);
	  print $sep.$line["target_area"];
	  $sep = ", ";
	}
    }
  pg_free_result($result);
}


function
show_html_table($title, $id, $query)
{
  global $dbconn;
  $data = array();
  print "<div class='tabtitle'>$title</div>\n";
  print "<TABLE id='$id' class='sortable'>";
  $result = pg_query($dbconn, $query) or die('Query failed: '.pg_last_error());
  $n = pg_num_fields($result);
  print "<TR>";
  for($j = 0; $j < $n; $j++)
    {
      print "<TH>".pg_field_name($result, $j)."</TH>";
    }
  print "</TR>";
  for($i = 0; $i < pg_num_rows($result); $i++)
    {
      print "<TR>";
      $line = pg_fetch_row($result);
      $data[] = $line;
      for($j = 0; $j < $n; $j++)
	{
	  print "<TD>".$line[$j]."</TD>\n";
	}
      print "</TR>";
    }
  pg_free_result($result);
  print "</TABLE>";
  return $data;
}

function
show_array_in_html_table($title, $id, $data)
{
  print "<div class='tabtitle'>$title</div>\n";
  print "<TABLE id='$id' class='sortable'>";
  print "<TR>";
  foreach(array_keys($data[0]) as $name)
  {
    print "<TH>$name</TH>";
  }
  print "</TR>";
  foreach($data as $row)
  {
    print "<TR>";
    foreach($row as $name =>$val)
    {
      if($val[0]=="{")
	$v = substr($val, 1, strlen($val)-2);
      else
	$v = $val;
      print "<TD>$v</TD>\n";
    }
    print "</TR>";
  }
  print "</TABLE>";
}

function query_for_sla($language, $season, $target_area, $start_time, $start_date, $stop_date)
{
        $query = 'SELECT * FROM sla WHERE';
        $query .= ' "language"='."'$language'";
        $query .= ' AND season='."'$season'";
        $query .= " AND target_area='$target_area'";
        $query .= ' AND start_time='."'$start_time'";
        $query .= ' AND valid_from <='."'$stop_date'";
        $query .= " AND (valid_to IS NULL OR valid_to >= '$start_date')";
	return $query;
}

/*
 Service        | Service Grade |    Start    |     End     |  Days   | Timeslot Duration | Weekly Duration | Annual Duration |     Ta
rget Area      | Network | Season

 Level | Score |  O
-------+-------+-----
     1 |    76 | 3.8
     2 |    66 | 3.3
     3 |    56 | 2.8

*/
function query_for_aal($service, $season, $target_area, $start_time)
{
        $query = 'SELECT * FROM aal_bsr b JOIN aal_reference r ON b."Service Grade"=r."Level" WHERE';
        $query .= ' "Service"='."'$service'";
        $query .= ' AND "Season"='."'$season'";
        $query .= ' AND "Target Area"='."'$target_area'";
        $query .= ' AND '."'$start_time'".' BETWEEN "Start" AND "End"';
	return $query;
}

function query_for_detail($language, $season, $target_area, $start_time, $start_date, $stop_date, $freeze, $status)
{
  $sla_query = query_for_sla($language, $season, $target_area, $start_time, $start_date, $stop_date);
  $query =
    "SELECT DISTINCT"
    ." TO_CHAR(date,'dd') as \"date\", TO_CHAR(time,'HH24:MI') as \"time\","
    ." frequency, ob.stn, s, d, o, TO_CHAR(row_timestamp, 'DD-MM-YYYY') AS row_timestamp"
    ." FROM parsed_observations ob JOIN ms_use ms USING (stn,language), ($sla_query) s"
    ." WHERE ob.\"language\" = '$language'"
    ." AND ms.target_area = '$target_area' AND ms.status = '$status'"
    ." AND ob.\"date\" BETWEEN '$start_date' AND '$stop_date'"
    ." AND (ob.frequency = ANY(s.primary_frequency) OR ob.frequency = ANY(s.secondary_frequency))"
    ." AND ob.time BETWEEN '$start_time' AND('$start_time' + '00:30:00'::interval)";
	if($freeze != "")
		$query ." AND ob.row_timestamp <= TIMESTAMP '$freeze'";
    $query .= " ORDER BY \"date\", o DESC";
	return $query;
}

function query_for_detail_aal($service, $target_area, $slot_start_time, $start_date, $stop_date, $freeze)
{
    $query = "SELECT DISTINCT o.date, time, stn, frequency, target, stn=any(aal_stn) as aal, s, d, o.o"
	." FROM parsed_observations o JOIN aal_mon a"
	." ON o.language = any(a.language)"
	." WHERE a.target_area = '$target_area'"
	." AND a.service = '$service'"
	." AND o.time BETWEEN '$slot_start_time' AND ('$slot_start_time' + '00:30:00'::interval)"
	." AND o.date BETWEEN '$start_date' AND '$stop_date'";
    if($freeze != "")
	$query .= " AND o.row_timestamp <= TIMESTAMP '$freeze'";
    $query .= " ORDER BY o.date, o.o DESC";
//print "<pre>\n$query\n</PRE>\n";
    return $query;
}

function show_detail($investigation, $start_date, $stop_date, $month_name, $freeze, $obs)
{
  if(!is_array($obs))
  {
  	show_detail_header($investigation, $start_date, $stop_date, $month_name);
    	show_detail_trailer($investigation);
  	return 0;
  }
  show_detail_header($investigation, $start_date, $stop_date, $month_name);
  $date = "";
  $count = 0;
  $sum = 0;
  foreach($obs as $line)
  {
      if($date != $line["date"])
	{
	  $line["best"] = 1;
	  $date = $line["date"];
	  $sum += $line["o"];
	  $count++;
	}
      else
	{
	  $line["best"] = 0;
	}
      show_detail_row($line);
  }
  if($count > 0)
    {
      $score = round($sum / $count, 1);
      show_detail_score($score);
    }
  else
    {
      $score = -1;
    }
  show_detail_trailer($investigation);
  return $score;
}

function show_detail_aal($investigation, $start_date, $stop_date, $month_name, $freeze, $obs)
{
  if(!is_array($obs))
  {
  	show_detail_header($investigation, $start_date, $stop_date, $month_name);
    	show_detail_trailer($investigation);
  	return 0;
  }
  show_detail_header($investigation, $start_date, $stop_date, $month_name);
  $date = "";
  $count = 0;
  $sum = 0;
  foreach($obs as $line)
  {
      if($line["aal"]=='t') {
	 $sum += $line["o"];
	 $count++;
      }
      show_detail_row($line);
  }
  if($count > 0)
    {
      $score = round(20 * $sum / $count);
    }
  else
    {
      $score = -1;
    }
  show_detail_score($score);
  show_detail_trailer($investigation);
  return $score;
}

function get_investigations($tracconn, $month, $season, $map)
{
	$report_id = $month.$season;
	$trac_query = "SELECT"
		. " ticket.id as ticket_id,"
		. " ticket.type as action,"
		. " ticket.summary as summary,"
		. " ticket.description as notes"
		. " FROM ticket JOIN ticket_custom ON ticket.id = ticket_custom.ticket"
		. " WHERE ticket_custom.name = 'report_id'"
		. " AND ticket_custom.value = '$report_id'";
	$summary_pg_query = pg_query($tracconn, $trac_query) or die('Query failed: ' .pg_last_error());
	$investigations = array();
	for($i=0; $i<pg_num_rows($summary_pg_query); $i++)
	{
		$investigation = pg_fetch_array($summary_pg_query, null, PGSQL_ASSOC);
		// add detail from custom_ticket table
		$id = $investigation["ticket_id"];
		$query = "SELECT name, value FROM ticket_custom WHERE ticket = $id";
		$result = pg_query($tracconn, $query) or die('Query failed: ' . pg_last_error());
		for($j=0; $j<pg_num_rows($result); $j++)
		{
			$line = pg_fetch_array($result, null, PGSQL_ASSOC);
			if($line["name"] == 'language')
				$investigation["language"] = $line["value"];
			if($line["name"] == 'target_area')
			{
				$investigation["ta_name"] = $line["value"];
				$investigation["tan"] = $line["value"];
				$investigation["ta_abbr"] = $map[$line["value"]]->abbr;
				$investigation["ta"] = $map[$line["value"]]->id;
			}
			if($line["name"] == 'timeslot')
				$investigation["start"] = $line["value"];
		}
		pg_free_result($result);
		$investigations[$id] = $investigation;
	}
	pg_free_result($summary_pg_query);
	return $investigations;
}

function get_investigations_pdo($conn, $month, $season, $map)
{
	$report_id = $month.$season;
	$trac_query = "SELECT"
		. " ticket.id as ticket_id,"
		. " ticket.type as action,"
		. " ticket.summary as summary,"
		. " ticket.description as notes"
		. " FROM ticket JOIN ticket_custom ON ticket.id = ticket_custom.ticket"
		. " WHERE ticket_custom.name = 'report_id'"
		. " AND ticket_custom.value = '$report_id'";
	$investigations = array();
        foreach ($conn->query($trac_query) as $investigation) {
		// add detail from custom_ticket table
		$id = $investigation["ticket_id"];
		$query = "SELECT name, value FROM ticket_custom WHERE ticket = $id";
                foreach ($conn->query($query) as $line) {
			if($line["name"] == 'language')
				$investigation["language"] = $line["value"];
			if($line["name"] == 'target_area')
			{
				$investigation["ta_name"] = $line["value"];
				$investigation["tan"] = $line["value"];
				$investigation["ta_abbr"] = $map[$line["value"]]->abbr;
				$investigation["ta"] = $map[$line["value"]]->id;
			}
			if($line["name"] == 'timeslot')
				$investigation["start"] = $line["value"];
		}
		$investigations[$id] = $investigation;
	}
	return $investigations;
}

function last_month($start_date, $month, $season)
{
	$d = strptime($start_date, '%Y-%m-%d');
	$m = $d["tm_mon"]; $y = $d["tm_year"];
	if($m==0)
	{
		$d["tm_mon"] == 11;
		$d["tm_year"]--;
	}
	else
	{
		$d["tm_mon"]--;
	}
	if($month == 1)
	{
		if(substr($season, 0, 1) == 'A')
		{
			$other_season = sprintf("B%02d", substr($season, 1)-1);
			$other_month = 5;
		} else {
			$other_season = sprintf("A%02d", substr($season, 1));
			$other_month = 7;
		}
	}
	else
	{
		$other_season = $season;
		$other_month = $month - 1;
	}
	return array("season" => $other_season, "month" => $other_month);
}
?>
