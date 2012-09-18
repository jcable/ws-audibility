<?php

function get_quadrants($n)
{
 if(strpos($n, 'NE')) return array($n);
 if(strpos($n, 'NW')) return array($n);
 if(strpos($n, 'SE')) return array($n);
 if(strpos($n, 'SW')) return array($n);
 $noq = array(
 "1" => array("1"),
 "2" => array("2"),
 "3" => array("3"),
 "4" => array("4"),
 "5" => array("5"),
 "6" => array("6NE","6NW","6SE","6SW"),
 "6" => array("6NE","6NW","6SE","6SW"),
 "7" => array("7NE","7NW","7SE","7SW"),
 "8" => array("8NE","8NW","8SE","8SW"),
 "9" => array("9NE","9NW","9SE","9SW"),
 "10" => array("10NE","10NW","10SE","10SW"),
 "11" => array("11NE","11NW","11SE","11SW"),
 "12" => array("12NE","12NW","12SE","12SW"),
 "13" => array("13NE","13NW","13SE","13SW"),
 "14" => array("14NE","14NW","14SE","14SW"),
 "15" => array("15NE","15NW","15SE","15SW"),
 "16" => array("16NE","16NW","16SE","16SW"),
 "17" => array("17"),
 "18" => array("18NE","18NW","18SE","18SW"),
 "19" => array("19"),
 "20" => array("20"),
 "21" => array("21"),
 "22" => array("22"),
 "23" => array("23"),
 "24" => array("24"),
 "25" => array("25"),
 "26" => array("26"),
 "27" => array("27NE","27NW","27SE","27SW"),
 "28" => array("28NE","28NW","28SE","28SW"),
 "29" => array("29NE","29NW","29SE","29SW"),
 "67" => array("67"),
 "69" => array("69"),
 "70" => array("70"),
 "71" => array("71"),
 "72" => array("72"),
 "73" => array("73"),
 "74" => array("74"),
 "75" => array("75"));
 if(array_key_exists($n, $noq))
	return $noq[$n];
 if(strpos($n, 'N'))
 {
   $q = substr($n, 0, 2); return array($q.'NW', $q.'NE');
 }
 if(strpos($n, 'S'))
 {
   $q = substr($n, 0, 2); return array($q.'SW', $q.'SE');
 }
 if(strpos($n, 'W'))
 {
   $q = substr($n, 0, 2); return array($q.'NW', $q.'SW');
 }
 if(strpos($n, 'E'))
 {
   $q = substr($n, 0, 2); return array($q.'SE', $q.'NE');
 }
 return array($n."NE",$n."NW",$n."SE",$n."SW");
}

function cirafs2array($n)
{
        if(substr($n,0,1)=='"')
                $n = substr($n, 1, strlen($n)-1);
        $c = explode(' ', $n);
        $quadrants = array();
        foreach($c as $q) {
                $p = get_quadrants($q);
                foreach($p as $z)
                        $quadrants[$z] = 1;
        }
        $target = "";
        $p = array_keys($quadrants);
        foreach($p as $q) {
                $target .= "$q ";
        }
        $target = trim($target);
        return '{"'.preg_replace("/ /", '", "', $target).'"}';
}

function get_quadrants_from_db($zonestring, $fmt)
{
	$nz = array();
	$zonelist = split(" ", $zonestring);
	foreach($zonelist as $zone) {
		$id = '';
		$q = '';
		sscanf($zone, "%d%[NSEW]", $id, $q);
		if($id=='')
			continue;
		$query = "SELECT ciraf_id, quadrant FROM ciraf_zones WHERE ciraf_id = '$id'";
		if($q!='')
		 $query .= " AND (quadrant LIKE '%$q' OR quadrant LIKE '$q%')";
		$result = pg_query($query) or die('Query failed: ' . pg_last_error());
		for($i=0; $i<pg_num_rows($result); $i++) {
			$line = pg_fetch_array($result, null, PGSQL_ASSOC);
			if($fmt=="string")
				$nz[] = $line["ciraf_id"].$line["quadrant"];
			else
				$nz[] = array('ciraf_id' => $line["ciraf_id"], 'quadrant' => $line["quadrant"]);
		}
		pg_free_result($result);
	}
	return $nz;
}

function add_geometry_from_ciraf_quadrants($target_table, $footprint)
{
	foreach($footprint as $quadrants) {
		$query = "UPDATE \"$target_table\" SET the_geom FROM SELECT multi(ST_Union(the_geom))";
		//$query .= " FROM ciraf_zones WHERE ciraf_id||quadrant IN('".implode($quadrants,"','")."')";
		$query .= " FROM ciraf_zones WHERE ciraf_id||quadrant IN(quadrants)";
		print "$query\n";
	}
}

?>
