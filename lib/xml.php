<?

function xml2array($data) {
	/* mvo voncken@mailandnews.com
	original ripped from  on the php-manual:gdemartini@bol.com.br    
	to be used for data retrieval(result-structure is Data oriented) */  
	$p = xml_parser_create();
	xml_parser_set_option($p, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($p, $data, &$vals, &$index);
	xml_parser_free($p);
	
	$tree = array();
	$i = 0;
	$tree = get_children($vals, $i);
	
	return $tree;
}

function get_children($vals, &$i) {      
	$children = array();
	
	if ($vals[$i]['value']) array_push($children, $vals[$i]['value']);
	
	$prevtag = "";
	
	while (++$i < count($vals)) {      
		switch ($vals[$i]['type']) {      
		case 'cdata':
			array_push($children, $vals[$i]['value']);
			break;
		case 'complete':                      
			$children{strtolower($vals[$i]['tag'])} = $vals[$i]['value'];            
			break;
		case 'open':                                
			$j++;
			
			if ($prevtag <> $vals[$i]['tag']) {
				$j = 0;
				$prevtag = $vals[$i]['tag'];
			}            
			
			$children{strtolower($vals[$i]['tag'])}[$j] = get_children($vals,$i);
			break;
		case 'close':        
			return $children;
		}
	}
}

?>
