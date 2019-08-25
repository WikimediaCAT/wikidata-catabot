<?php

if ( count( $argv ) > 1 ) {
	$cantic = $argv[1];
} else {
	
	exit();
}

$url = "http://cantic.bnc.cat/registres/CUCId/".$cantic;

$html = file_get_contents( $url );

if ( preg_match( "/\/viaf\/(\d+)/", $html, $match ) ) {
	
	if ( count( $match ) > 1 ) {
		
		echo $match[1];
	} else {
		echo "";
	}
} else {
	echo "";
}


