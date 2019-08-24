<?php

require_once( __DIR__ . '/vendor/autoload.php' );
require_once( __DIR__ . '/lib/resolve.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;

use League\Csv\Reader;

// Detect commandline args
$conffile = 'config.json';
$csvfile = 'list.csv';
$taskname = null; // If no task given, exit
$resolve = true; // If we allow wikipedia resolving
$delimiter = "\t"; // Default separator
$enclosure = "\""; // Default delimiter

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$csvfile = $argv[2];
}

if ( count( $argv ) > 3 ) {
	$taskname = $argv[3];
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

if ( array_key_exists( "tasks", $confjson ) ) {
	$tasksConf = $confjson["tasks"];
}

if ( array_key_exists( "resolve", $confjson ) ) {
	$resolve = $confjson["resolve"];
}

if ( array_key_exists( "delimiter", $confjson ) ) {
	$delimiter = $confjson["delimiter"];
}

if ( array_key_exists( "enclosure", $confjson ) ) {
	$enclosure = $confjson["enclosure"];
}


$tasks = array_keys( $tasksConf );
$props = null;

if ( count( $tasks ) < 1 ) {
	// No task, exit
	exit;
}

if ( ! $taskname ) {
	echo "No task specified!";
	exit;
} else {
	if ( in_array( $taskname, $tasks ) ) {
		$props = $tasksConf[ $taskname ];
	} else {
		// Some error here. Stop it
		exit;
	}
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


$reader = Reader::createFromPath( $csvfile );

$reader->setOffset(1);
$reader->setDelimiter( $delimiter );
$reader->setEnclosure( $enclosure );

$results = $reader->fetch();

foreach ( $results as $row ) {
	
	$wdid = null;
	
	# If smaller size, continue
	if ( count( $row ) < 1 ) {
		continue;
	}
	
	if ( substr( $row[0], 0, 1 ) === "#" ) {
		# Skip if # -> Handling errors, etc.
		
		continue;
	}
	
	echo $row[0]."\n";

	// Do we resolve WikiData from Wikipedia?
	if ( $resolve ) {	
		$wdid = retrieveWikidataId( $row[0], $wikiconfig, $wikidataconfig );
	}
	
	if ( $wdid ) {
		// $wdid = "Q13406268"; // Dummy, for testing purposes. Must be changed
		// Add statement and ref
		echo $wdid."\n"; // Only considers id -> ACTION done via configuration
		performAction( $wbFactory, $wdid, $row, $props, $wikiconfig );
		sleep( 5 ); // Delay 5 seconds
	} else {
		
		echo "- Missing ".$row[0]."\n";

		$wdid = createItem( $wbFactory, $row, $props );
		
		if ( $wdid ) {
			
			performAction( $wbFactory, $wdid, $row, $props, $wikiconfig );
			sleep( 5 ); // Delay 5 seconds
		
		} else {
			echo "- Could not create ".$row[0]."\n";
		}
	}
	
}

$api->logout();




