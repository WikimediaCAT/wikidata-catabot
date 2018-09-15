<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;

use League\Csv\Reader;

// Detect commandline args
$conffile = 'config.json';
$csvfile = 'list.csv';


if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$csvfile = $argv[2];
}

// Detect if files
if ( ! file_exists( $conffile ) || ! file_exists( $csvfile ) ) {
	die( "Files needed" );
}

$confjson = json_decode( file_get_contents( $conffile ), 1 );

$wikiconfig = null;
$wikidataconfig = null;
$orcidconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

if ( array_key_exists( "wikidata", $confjson ) ) {
	$wikidataconfig = $confjson["wikidata"];
}

if ( array_key_exists( "orcid", $confjson ) ) {
	$orcidconfig = $confjson["orcid"];
	# TODO: to check if needed
}


// Detect if files
if ( ! file_exists( $conffile ) || ! file_exists( $csvfile ) ) {
	die( "Files needed" );
}

$researchers = array();

$inGroupNumber = 20; // To be included in configuration
$orcidarrays = array( );

$reader = Reader::createFromPath( $csvfile );

$reader->setOffset(1);
$reader->setDelimiter("\t");

$results = $reader->fetch();

$rstart = 0;
$rcount = 0;

foreach ( $results as $row ) {

	$row[0] = trim( $row[0] );
	
	if ( substr( $row[0], 0, 1 ) === "#" ) {
		# Skip if # -> Handling errors, etc.
		
		continue;
	}

	if ( $row[0] === "" ) {
		
		continue;
	}
	
	if ( $rcount == $inGroupNumber ) {
		$rcount = 0;
		$rstart = $rstart + 1;
	}
	
	if ( ! array_key_exists( $rstart, $orcidarrays ) ) {
		$orcidarrays[ $rstart ] = array( );
	}

	array_push( $orcidarrays[ $rstart ], $row[0] );

	$researchers[ $row[0] ] = array(); // Adding to array of researchers
	
	$rcount = $rcount + 1;	
}

foreach ( $orcidarrays as $orcidarray ) {
	
	$arr = array();
	
	foreach ( $orcidarray as $orarr ) {
		
		$orarr = "\"".$orarr."\"";
		array_push( $arr, $orarr );
	}
	
	# Researchers with ORCID and group added
	$query = "SELECT ?human ?humanLabel ?orcid WHERE  {
	?human wdt:P31 wd:Q5 .
	?human wdt:P496 ?orcid .
	FILTER ( 
		?orcid IN ( ".implode( ", ", $arr )." )
	)
	SERVICE wikibase:label { bd:serviceParam wikibase:language \"[AUTO_LANGUAGE],en\". }
}
";

	$url = "https://query.wikidata.org/sparql?query=".urlencode( $query )."&format=json";
	
	$obj = json_decode( file_get_contents( $url ), true );
	
	if ( $obj ) {
		$researchers = addQuery2array( $researchers, $obj );
	}
	
	sleep( 1 );
}

# Process ORCID script
// Create a stream
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "Accept: application/orcid+json\r\n"
    ]
];

$context = stream_context_create($opts);

foreach ( $researchers as $key => $value ) {
	
	$url = "https://pub.orcid.org/v2.1/".$key;
	$obj = json_decode( file_get_contents( $url , true, $context ), true );
	
	if ( $obj ) {
		$researchers = addORCID2array( $researchers, $obj );
	}
	
	sleep( 0.5 );

}

# From here process to Wikidata
print_r( $researchers );



function addQuery2array( $researchers, $obj ) {
	
	if ( array_key_exists( "results", $obj ) ) {
		
		if ( array_key_exists( "bindings", $obj["results"] ) ) {

			$results = $obj["results"]["bindings"];
		
			if ( count( $results > 0  ) ) {

				foreach ( $results as $match ) {
					
					# Minimal ORCID
					if ( array_key_exists( "orcid", $match ) ) {
						
						if ( array_key_exists( "value", $match["orcid"] ) ) {
							
							$orcidval = $match["orcid"]["value"];
							if ( $orcidval !== "" ) {

								if ( array_key_exists( "human", $match ) ) 
									
									if ( array_key_exists( "value", $match["human"] ) ) {
										
										$humanval = $match["human"]["value"];
										
										if ( $humanval !== "" ) {
											
											$parts = explode( "entity/", $humanval );
											if ( count( $parts ) === 2 ) {
												$researchers[ $orcidval ]["wdid"] = trim( $parts[1] );
											}
											
										}
									}

							}
							
							if ( array_key_exists( "humanLabel", $match ) ) {
									
								if ( array_key_exists( "value", $match["humanLabel"] ) ) {
								
									$labelval = $match["humanLabel"]["value"];

									if ( $labelval !== "" ) {
										
										$researchers[ $orcidval ]["label"] = $labelval;
									}
								}
							}
						}
					}
						
				}
					
			}
				
		}
		
	}
	
	return $researchers;
}
	
	
function addORCID2array( $researchers, $obj ) {
	
	
	
	return $researchers;
}


