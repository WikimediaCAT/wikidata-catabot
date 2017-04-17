<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;
use \Wikibase\DataModel as WbDM;

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

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

if ( array_key_exists( "wikidata", $confjson ) ) {
	$wikidataconfig = $confjson["wikidata"];
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
$reader->setDelimiter("\t");

$results = $reader->fetch();

foreach ( $results as $row ) {
	
	echo $row[0]."\n";
	
	$wdid = retrieveWikidataId( $row[0], $wikiconfig );
	
	if ( $wdid ) {
		$wdid = "Q13406268"; // Dummy, for testing purposes. Must be changed
		// Add statement and ref
		addStatement( $wbFactory, $wdid, $row, $wikiconfig );
		sleep( 5 ); // Delay 5 seconds
	}
	
}

$api->logout();

function retrieveWikidataId( $title, $wikiconfig ){

	$wdid = null;
	
	$title = str_replace( " ", "_", $title );
	
	$url = $wikiconfig["url"]."?action=query&prop=wbentityusage&titles=".$title."&format=json";
	
	// Process url
	$json = file_get_contents( $url );

	// Proceess JSON
	$obj = json_decode( $json, true );
		
	if ( $obj ) {
	
		if ( array_key_exists( "query", $obj ) ) {
	
			if ( array_key_exists( "pages", $obj['query'] ) ) {
	
				// Assume first key
				foreach ( $obj['query']["pages"] as $key => $struct ) {
										
					if ( array_key_exists( "wbentityusage", $struct ) ) {
						
						$wdid = retrieveWikidataIdfromStruct( $struct["wbentityusage"] );
						
					}
					
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
	
	// $statementCreator = $wbFactory->newStatementCreator();
	
	$propId = 'P166'; // award given
	$datePropId = 'P585'; //date
	$refUrlPropId = 'P854'; // ref url
	
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
		
		$qualifierSnaks = null;
		$referenceSnaks = null;
		$referenceArray = null;

		if ( $date ) {
			// Qualifier
			
			$date = "+".$date."-00-00T00:00:00Z";
			$qualifierSnaks = array(
				// Year precision

				new WbDM\Snak\PropertyValueSnak( new WbDM\Entity\PropertyId( $datePropId ), new DataValues\TimeValue( $date, 0, 0, 0, 9, "http://www.wikidata.org/entity/Q1985727" ) ),
			);
						
		}
		
		
		if ( $ref ) {
			// Reference URL
			$referenceSnaks = array(
				new WbDM\Snak\PropertyValueSnak( new WbDM\Entity\PropertyId( $refUrlPropId ), new DataValues\StringValue( $ref ) ),
			);
			
			$referenceArray = array( new WbDM\Reference( $referenceSnaks ) );
			
		}
		
		
		$propIdObject = new WbDM\Entity\PropertyId( $propId );
		$itemId = retrieveWikidataId( $award, $wikiconfig );
		$itemIdObject = new WbDM\Entity\ItemId( $itemId );
		$entityObject = new WbDM\Entity\EntityIdValue( $itemIdObject );
		
		$statementListProp = $statementList->getByPropertyId(  $propIdObject );
		if( $statementListProp->isEmpty() ) {
			$mainSnak =
				new WbDM\Snak\PropertyValueSnak(
					$propIdObject,
					$entityObject
				);
				
			$statementList->addNewStatement( $mainSnak, $qualifierSnaks, $referenceArray );
			$saver->save( $revision );
			echo "+ ".$id." added\n";
			
		} else {
			
			$exists = false;
			$add = false;
			
			foreach ( $statementListProp as $statement ) {
				
				// Get Main Snak
				$mainSnak = $statement->getMainSnak();
				$datavalue = $mainSnak->getDataValue();
				
				if ( $datavalue->getEntityId()->getNumericId() === $itemIdObject->getNumericId() ) {
					
					$exists = true;
					
					// Already exists
										
					// Check qualifiers
					$qualifiers = $statement->getQualifiers();
					
					// If no qualifiers, add to statement
					if ( count( $qualifiers ) < 1 ) {
						$statement->setQualifiers( new WbDM\Snak\SnakList( $qualifierSnaks ) );
						$add = true;
					}
										
					// Check references
					$references = $statement->getReferences();

					// If no references, add to statement
					if ( count( $references ) < 1 ) {
						$statement->addNewReference( $referenceSnaks );
						$add = true;
					}

					break;
					
				}
				
			}
			
			if ( ! $exists ) {
				
				// Add for this case
				$mainSnak =
				new WbDM\Snak\PropertyValueSnak(
					$propIdObject,
					$entityObject
				);
				
				$statementList->addNewStatement( $mainSnak, $qualifierSnaks, $referenceSnaks );
				$saver->save( $revision );
				echo "+ ".$id." added\n";

			} else {
			
				if ( $add ) {
					$saver->save( $revision );
					echo "= ".$id." modified\n";

				}
			}
			
			echo "= ".$id." already exists\n";
		}

	
	}
}
