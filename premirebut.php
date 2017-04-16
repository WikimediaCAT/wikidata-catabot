<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;

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


$confjson = file_get_contents( $conffile );

$wikiconfig = null;
$wikidataconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

if ( array_key_exists( "wikidata", $confjson ) ) {
	$wikidataconfig = $confjson["wikidata"];
}

$api = new MwApi\MediawikiApi( $wikidataconfig['url'] );

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
$reader->setDelimiter("\t");

$results = $reader->fetch();

foreach ( $results as $row ) {
	
	echo $row[0]."\n";
	
	$wdid = retrieveWikidataId( $row[0] );
	
	if ( $wdid ) {
		// Add statement and ref
		addStatement( $wbFactory, $wdid, $row, $wikiconfig );
	}
	
}

$api->logout();

function retrieveWikidataId( $title, $wikiconfig ){

	$wdid = null;
	
	$title = str_replace( " ", "_", $title );
	
	$url = $wikiconfig["url"]."?action=query&prop=wbentityusage&titles=".$title;
	
	// Process url
	$json = file_get_contents( $url );
	
	// Proceess JSON
	$obj = json_decode( $json, true );
	
	if ( array_key_exists( "query", $obj ) ) {

		if ( array_key_exists( "pages", $obj['query'] ) ) {

			// Assume first key
			foreach ( $obj['query']["pages"] as $key => $struct ) {
				
				if ( array_key_exists( "wbentityusage", $struct ) ) {
					
					$wdid = retrieveWikidataIdfromStruct( $struct );
					
				}
				
			}
		}
	}
	
	return $wdid;
}

function retrieveWikidataIdfromStruct( $struct ){
	
	$wikidataid = null;
	
	foreach ( $struct as $key => $hash ) {
		
		if ( array_key_exists( "aspects", $hash ) ) {
					
			if ( in_array( "O", $hash["aspects"] ) && in_array( "S", $hash["aspects"] ) ) {
				$wikidataid = $key;
			}
		}	
	}
	
	return $wikidataid;
}

function addStatement( $wbFactory, $id, $row, $wikiconfig ){
	
	$saver = $wbFactory->newRevisionSaver();
	
	$revision = $wbFactory->newRevisionGetter()->getFromId( $id );
	$item = $revision->getContent()->getData();
	$statementList = $item->getStatements();

	$propId = 1320;
	if ( array_key_exists( 1, $row ) ) {
		
		$award = $row[1];
		$date = null;
		$ref = null;
		
		if ( array_key_exists( 2, $row ) ) {
			$date = $row[2];
		}

		if ( array_key_exists( 3, $row ) ) {
			$ref = $row[3];
		}
		
		$referenceSnaks = null;
		if ( $date ) {
			// Qualifier
			$qualifierSnaks = array(
				new PropertyValueSnak( new PropertyId( 'P854' ), new DateTime( $date ) ),
			);
		}
		
		if ( $ref ) {
			// Reference URL
			$referenceSnaks = array(
				new PropertyValueSnak( new PropertyId( 'P854' ), $ref ),
			);
		}
		
		if( $statementList->getByPropertyId( PropertyId::newFromNumber( $propId ) )->isEmpty() ) {
			$statement = $statementCreator->create(
				new PropertyValueSnak(
					PropertyId::newFromNumber( $propId ),
					PropertyId::newFromNumber( retrieveWikidataId( $award, $wikiconfig ) )
				),
				$id
			);
			
			if ( $referenceSnaks ) {
				$statement->addNewReference( $referenceSnaks );
			}
			
		} else {
			echo "Ignore for ".$id."\n";
		}
		
		$saver->save( $revision );
	
	}
}
