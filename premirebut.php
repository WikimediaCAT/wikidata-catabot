<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;

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

$api = new MwApi\MediawikiApi( "https://www.wikidata.org/w/api.php" );

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

// Process CSV List, ignore first row

// URL detect
// https://ca.wikipedia.org/w/api.php?action=query&titles=Amical_Wikimedia&prop=wbentityusage
// If page exists,
// If not, then redirect ...
// if entity exists of the type... then...


$revision = $wbFactory->newRevisionGetter()->getFromId( 'Q16943393' );
$item = $revision->getContent()->getData();

#var_dump( $item );

$statementList = $item->getStatements();

# ./vendor/wikibase/data-model/src/Statement/StatementList.php

$propIds = $statementList->getPropertyIds();

var_dump( array_keys( $propIds ) );

var_dump( $statementList );


$api->logout();
