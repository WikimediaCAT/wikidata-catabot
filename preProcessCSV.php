<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;

use League\Csv\Reader;
use League\Csv\Writer;

// Detect commandline args
$conffile = 'config.json';
$csvfile = 'list.csv';
$delimiter = "\t"; // Default separator
$enclosure = "\""; // Default delimiter
$positions = array(); // Resolve positions

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

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

if ( array_key_exists( "wikidata", $confjson ) ) {
	$wikidataconfig = $confjson["wikidata"];
}

if ( array_key_exists( "delimiter", $confjson ) ) {
	$delimiter = $confjson["delimiter"];
}

if ( array_key_exists( "enclosure", $confjson ) ) {
	$enclosure = $confjson["enclosure"];
}

if ( array_key_exists( "positions", $confjson ) ) {
	$positions = $confjson["positions"];
}

$api = new MwApi\MediawikiApi( $wikidataconfig['url'] );

$api->login( new MwApi\ApiUser( $wikidataconfig['user'], $wikidataconfig['password'] ) );


$dataValueClasses = array(
    'unknown' => 'DataValues\UnknownValue',
    'string' => 'DataValues\StringValue',
    'boolean' => 'DataValues\BooleanValue',
    'number' => 'DataValues\NumberValue',
    'time' => 'DataValues\TimeValue',
    'globecoordinate' => 'DataValues\Geo\Values\GlobeCoordinateValue',
    'wikibase-entityid' => 'Wikibase\DataModel\Entity\EntityIdValue',
);

$wbFactory = new WbApi\WikibaseFactory(
    $api,
    new DataValues\Deserializers\DataValueDeserializer( $dataValueClasses ),
    new DataValues\Serializers\DataValueSerializer()
);

// Cache variable to store queries
$cache = array();
$oresults = array();

// If positions to resolve...
if ( count( $positions ) > 0 ) {

	$reader = Reader::createFromPath( $csvfile );
	
	$reader->setDelimiter( $delimiter );
	$reader->setEnclosure( $enclosure );
	
	$results = $reader->fetch();

	$count = 0;
	
	foreach ( $results as $row ) {
		
		if ( $count === 0 ) {
			array_push( $oresults, $row );
			$count++;
			continue;
		}
		
		$orow = $row;
		
		foreach ( $positions as $pos ) {
			
			// We assume pos is int
			if ( array_key_exists( $pos, $orow ) ) {
				
				$orow[$pos] = resolveValue( $orow[$pos], $cache, $wikiconfig );
			}
		}

		array_push( $oresults, $orow );
	
	}
	

}

if ( count( $argv ) > 3 ) {
	
	$writer = Writer::createFromPath( $argv[3], 'w+' );
	$writer->setDelimiter( $delimiter );
	$writer->setEnclosure( $enclosure );
	$writer->insertAll( $oresults ); //using an array
}


/** Further resolve value **/

function resolveValue( $rowValue, $cache, $wikiconfig ) {

	$alreadyQ = false;
	
	if ( $rowValue ) {
	
		if ( substr( $rowValue, 0, 1 ) === "Q" ) {
	
			// Then its a variable
			
			if ( is_numeric( substr( $rowValue, 1 ) ) ) {
	
				$alreadyQ = true;
				
			}
		}
	
	}
	
	if ( ! $alreadyQ ) {
		
		if ( array_key_exists( $rowValue, $cache ) ) {
			
			$rowValue = $cache[ $rowValue ];
			
		} else {
		
			$wdid = retrieveWikidataId( $rowValue, $wikiconfig );
			
			if ( $wdid ) {
				
				$cache[ $rowValue ] = $wdid;
				$rowValue = $wdid;
				
			}
		
		}
	}
	
	return trim( $rowValue );
	
}



function retrieveWikidataId( $title, $wikiconfig ){

	// TODO: Handle redirect from wiki

	$wdid = null;
	
	# If Q value
	if ( preg_match( "/^Q\d+/", $title ) ) {
		
		$wdid = $title;
		
	} else {
	
		$title = str_replace( " ", "_", $title );
		
		// This is for getting all associated Wikidata ID
		// $url = $wikiconfig["url"]."?action=query&prop=wbentityusage&titles=".$title."&format=json";
		
		// Below for main WikiData ID
		$url = $wikiconfig["url"]."?action=query&titles=".$title."&format=json&prop=pageprops&ppprop=wikibase_item&redirects=true";
		
		// Process url
		$json = file_get_contents( $url );
	
		// Proceess JSON
		$obj = json_decode( $json, true );
	
		if ( $obj ) {
			
			if ( array_key_exists( "query", $obj ) ) {
	
				if ( array_key_exists( "pages", $obj['query'] ) ) {
			
					// Assume first key
					foreach ( $obj['query']["pages"] as $key => $struct ) {
											
						if ( array_key_exists( "pageprops", $struct ) ) {
							
							if ( array_key_exists( "wikibase_item", $struct["pageprops"] ) ) {
							
								$wdid = $struct["pageprops"]["wikibase_item"];
								
								break;
							}
						}
						
					}
				}	
				
			}
		}
	}
	
	return $wdid;
}
