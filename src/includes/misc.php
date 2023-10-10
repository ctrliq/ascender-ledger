<?php

function stripUnwantedTagsAndAttrs($html_str){
	$xml = new DOMDocument();
  //Suppress warnings: proper error handling is needed
	libxml_use_internal_errors(false);
  //List the tags you want to allow here, NOTE you MUST allow html and body otherwise entire string will be cleared
	$allowed_tags = array("b", "br", "em", "i", "li", "ol", "u", "ul", "p");
  //List the attributes you want to allow here
	$allowed_attrs = array ();
	if (!strlen($html_str)){return false;}
	if ($xml->loadHTML($html_str, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)){
	  foreach ($xml->getElementsByTagName("*") as $tag){
		if (!in_array($tag->tagName, $allowed_tags)){
		  $tag->parentNode->removeChild($tag);
		}else{
		  foreach ($tag->attributes as $attr){
			if (!in_array($attr->nodeName, $allowed_attrs)){
			  $tag->removeAttribute($attr->nodeName);
			}
		  }
		}
	  }
	}
	$d = $xml->saveHTML();
	return strip_tags($d);
}

function random_str($length) {
	$keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$str = '';
	$max = mb_strlen($keyspace, '8bit') - 1;
	for ($i = 0; $i < $length; ++$i) {
		$str .= $keyspace[random_int(0, $max)];
	}
	return $str;
}

function reindex_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[] = $b[$c];
		}
	}

	return $d;
}

function reindex_arr_by_id($a) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b['id']] = $b;
		}
	}

	return $d;
}

function reindex_arr_by_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b[$c]] = $b;
		}
	}

	return $d;
}

function reindex_arr_by_id_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b['id']] = $b[$c];
		}
	}

	return $d;
}

function ppre($arr) {
	print "<pre>";
	print_r($arr);
	print "</pre>";
}

function build_filter($filters) {

	$sql = "SELECT DISTINCT f.`host` FROM `facts` as f\n";
	$p = array();
	$c = 0;
	foreach ($filters as $f) {
		$c++;

		$sql .= "INNER JOIN (SELECT * FROM `facts` WHERE `fact`= ? AND ";

		$p[] = $f['fact'];
		switch ($f['compare']) {
			case 'eq':
				$sql .= '`data` = ?';
				$p[] = $f['value'];
				break;
			case 'ne':
				$sql .= '`data` != ?';
				$p[] = $f['value'];
				break;
			case 'gt':
				$sql .= 'cast(`data` as signed) > ?';
				$p[] = $f['value'];
				break;
			case 'lt':
				$sql .= '`cast(data` as signed) < ?';
				$p[] = $f['value'];
				break;
			case 'contains':
				$sql .= '`data` LIKE ?';
				$p[] = "%" . $f['value'] . "%";
				break;
			case 'starts':
				$sql .= '`data` LIKE ?';
				$p[] = "%" . $f['value'];
				break;
			case 'ends':
				$sql .= '`data` LIKE ?';
				$p[] = "%" . $f['value'];
				break;
		}
		$sql .= ') as f' . $c . ' ON f.host = f' . $c . ".host\n";
	}

	return array($sql, $p);
}

function build_report ($id) {
	$id = intval($id);
	$report = new Report($id);
	if ($report->id) {
		$w = build_filter($report->filters);
		
		$sql = $w[0];
		$pr = $w[1];

		$hosts = db_fetch_assocs_prepare($sql, $pr);
    }

    $data = array();
    foreach ($hosts as $h) {
        $facts = db_fetch_assocs_prepare('SELECT `fact`,`data` FROM `facts` WHERE `host` = ?', array($h['host']));
        $x = 0;
        $data[$h['host']] = array();
        if (count($facts)) {
            foreach ($report->columns as $d => $k) {
                $data[$h['host']][$x] = '';
                foreach ($facts as $f) {
                    if (is_array($k)) {
                        if (in_array($f['fact'], $k)) {
                            if ($data[$h['host']][$x] != '') {
                                $data[$h['host']][$x] .= ', ';
                            }
                            $data[$h['host']][$x] .= $f['data'];
                        }
                    } else {
                        if ($k == $f['fact']) {
                            $data[$h['host']][$x] = $f['data'];
                        }
                    }
                }
                $x++;
            }
        }
        $e = true;
        foreach ($data[$h['host']] as $d) {
            if ($d != '') {
                $e = false;
            }
        }
        if ($e) {
            unset($data[$h['host']]);
        }
    }
    return $data;
}