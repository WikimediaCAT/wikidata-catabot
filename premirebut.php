<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;

use League\Csv\Reader;

// Detect commandline args
$conffile = 'config.json';
$csvfile = 'list.csv';
$taskname = null; // If no task given, first key will be provided

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

$tasks = array_keys( $tasksConf );
$props = null;

if ( count( $tasks ) < 1 ) {
	// No task, exit
	exit;
}

if ( ! $taskname ) {
	$taskname = array_shift( $tasks );
	$props = $tasksConf[ $taskname ];
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
$reader->setDelimiter("\t");

$results = $reader->fetch();

foreach ( $results as $row ) {
	
	if ( substr( $row[0], 0, 1 ) === "#" ) {
		# Skip if # -> Handling errors, etc.
		
		continue;
	}
	
	echo $row[0]."\n";
	
	// TODO: Handle redirect from wiki
	$wdid = retrieveWikidataId( $row[0], $wikiconfig );
	if ( $wdid ) {
		// $wdid = "Q13406268"; // Dummy, for testing purposes. Must be changed
		// Add statement and ref
		echo $wdid."\n";
		addStatement( $wbFactory, $wdid, $row, $props, $wikiconfig );
		sleep( 5 ); // Delay 5 seconds
	} else {
		echo "- Missing ".$row[0]."\n";
	}
	
}

$api->logout();

function retrieveWikidataId( $title, $wikiconfig ){

	$wdid = null;
	
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
	
		// Below for all associated wikidata
		//if ( array_key_exists( "query", $obj ) ) {
		//
		//	if ( array_key_exists( "pages", $obj['query'] ) ) {
		//
		//		// Assume first key
		//		foreach ( $obj['query']["pages"] as $key => $struct ) {
		//								
		//			if ( array_key_exists( "wbentityusage", $struct ) ) {
		//				
		//				$wdid = retrieveWikidataIdfromStruct( $struct["wbentityusage"] );
		//				
		//			}
		//			
		//		}
		//	}
		//}
		
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
	
	return $wdid;
}

/** Unused function below **/
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


/* Return timevalue */
function transformDate( $datestr, $precision="year", $calendar="http://www.wikidata.org/entity/Q1985727" ) {
	
	// TODO: This should be adapted to different precisions 
	$t1 = 0;
	$t2 = 0;
	$t3 = 0;
	$t4 = 9;
	
	$datestr = "+".$datestr."-00-00T00:00:00Z";
	
	$timeValue =  new DataValues\TimeValue( $datestr, $t1, $t2, $t3, $t4, $calendar );
	
	return $timeValue;
}

/* Function for adding statements */
function addStatement( $wbFactory, $id, $row, $props, $wikiconfig ){
	
	$saver = $wbFactory->newRevisionSaver();
	
	$revision = $wbFactory->newRevisionGetter()->getFromId( $id );
	$item = $revision->getContent()->getData();
	
	$statementList = $item->getStatements();
	
	// $statementCreator = $wbFactory->newStatementCreator();
	
	$editdesc = $props["desc"];

	// Value types should be considered here or in config somehow
	$propId = $props["entity"];
	$qualifierPropId = $props["qualifier"];
	$refPropId = $props["ref"];
	$precision = $props["precision"];
	
	if ( ! $propId ) {
		// Kill it if no Prop
		exit;
	}
	
	if ( array_key_exists( 1, $row ) ) {
		
		$entityValue = $row[1];
		$qualifierValue = null;
		$refValue = null;
		
		if ( array_key_exists( 2, $row ) ) {
			$qualifierValue = $row[2];
		}

		if ( array_key_exists( 3, $row ) ) {
			$refValue = $row[3];
		}
		
		$qualifierSnaks = null;
		$referenceSnaks = null;
		$referenceArray = null;

		if ( $qualifierValue ) {
			// Qualifier
			
			$qualifierSnaks = array(
				// Year precision

				new WbDM\Snak\PropertyValueSnak( new WbDM\Entity\PropertyId( $qualifierPropId ), transformDate( $qualifierValue, $precision ) ),
			);
						
		}
		
		
		if ( $refValue ) {
			// Reference URL
			$referenceSnaks = array(
				new WbDM\Snak\PropertyValueSnak( new WbDM\Entity\PropertyId( $refPropId ), new DataValues\StringValue( $refValue ) ),
			);
			
			$referenceArray = array( new WbDM\Reference( $referenceSnaks ) );
			
		}
		
		
		$propIdObject = new WbDM\Entity\PropertyId( $propId );
		$itemId = retrieveWikidataId( $entityValue, $wikiconfig );
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
			$saver->save( $revision, new MwDM\EditInfo( $editdesc ) );
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

				// Moved to $referenceArray

				$statementList->addNewStatement( $mainSnak, $qualifierSnaks, $referenceArray );	
				$saver->save( $revision, new MwDM\EditInfo( $editdesc ) );
				echo "+ ".$id." added\n";

			} else {
			
				if ( $add ) {
					$saver->save( $revision, new MwDM\EditInfo( $editdesc ) );
					echo "= ".$id." modified\n";

				}
			}
			
			echo "= ".$id." already exists\n";
		}

	
	}
}
