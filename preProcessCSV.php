<?php

require_once( __DIR__ . '/vendor/autoload.php' );
require_once( __DIR__ . '/lib/resolve.php' );


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
$dpositions = array(); // Resolve date positions
$dschema = array(); // Resolve date positions
$dschemaout = array(); // Resolve date positions

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

if ( array_key_exists( "dpositions", $confjson ) ) {
	$dpositions = $confjson["dpositions"];
}

if ( array_key_exists( "dschema", $confjson ) ) {
	$dschema = $confjson["dschema"];
}

if ( array_key_exists( "dschemaout", $confjson ) ) {
	$dschemaout = $confjson["dschemaout"];
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
				
				$orow[$pos] = resolveValue( $orow[$pos], $cache, $wikiconfig, $wikidataconfig );
			}
		}

		foreach ( $dpositions as $dpos ) {
						
			// We assume pos is int
			if ( array_key_exists( $dpos, $orow ) ) {
				
				$schema = null;
				$schemaout = null;
				
				if ( $dschema && $dschema[ $dpos ] ) {
					$schema = $dschema[ $dpos ];
				}
				
				if ( $dschemaout && $dschemaout[ $dpos ] ) {
					$schemaout = $dschemaout[ $dpos ];
				}

				$orow[$dpos] = resolveDate( $orow[$dpos], $schema, $schemaout );
				
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

