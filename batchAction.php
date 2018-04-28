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
		$wdid = retrieveWikidataId( $row[0], $wikiconfig );
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

function createItem( $wbFactory, $row, $props ) {
	
	$saver = $wbFactory->newRevisionSaver();
	
	$fingerprint = addFingerprintFromRow( $row, $props );
	
	$itemId = null;
	
	if ( $fingerprint ) {
		
		$item = new WbDM\Entity\Item( null, $fingerprint );
		
		$edit = new MwDM\Revision( new WbDM\ItemContent( $item ) );
		$editdesc = new MwDM\EditInfo( "Adding ".$row[0] );
		
		$resultingItem = $saver->save( $edit, $editdesc );
		
		$itemId = $resultingItem->getId();
		
		if ( $itemId ) {
			echo "Added item:\t".$itemId."\t".$row[0]."\n";
		}
	
	}
	
	return $itemId;
}

function addFingerprintFromRow( $row, $props ) {
	
	$lang = "en";
	
	if ( array_key_exists( "lang", $props ) ) {
		$lang = $props["lang"];
	}
	
	$labels = null;
	$descriptions = null;
	$aliases = null;
	$fingerprint = null;
	
	// For now consider only label
	
	if ( $row[0] ) {

		$labelObj = new WbDM\Term\Term( $lang, $row[0] );
		$labels = new WbDM\Term\TermList( array( $labelObj ) );
		$fingerprint = new WbDM\Term\Fingerprint( $labels, $descriptions, $aliases );
	}
	
	return  $fingerprint;
	
}


/* Return timevalue */
function transformDate( $datestr, $calendar="http://www.wikidata.org/entity/Q1985727" ) {
	
	// TODO: This should handle exceptions
	
	$input = "";
		
	$t1 = 0;
	$t2 = 0;
	$t3 = 0;
	$t4 = 9;
	
	$split = explode( "-", $datestr );
	if ( count( $split ) > 2 ) {
		# Day -> Assume. problem if more stuff
		
		$t4 = 11;
		$input = "+".$datestr."T00:00:00Z";
		
	} elseif ( count( $split ) == 2 )  {
		# Month
		
		$t4 = 10;
		$input = "+".$datestr."-00T00:00:00Z";
		
	} else {
		# Year
		
		$input = "+".$datestr."-00-00T00:00:00Z";
	}

	
	$timeValue =  new DataValues\TimeValue( $input, $t1, $t2, $t3, $t4, $calendar );
	
	return $timeValue;
}

/* Function for adding statements */
function performAction( $wbFactory, $id, $row, $props, $wikiconfig ){
	
	$saver = $wbFactory->newRevisionSaver();
	
	$revision = $wbFactory->newRevisionGetter()->getFromId( $id );
	$item = $revision->getContent()->getData();
	
	$statementList = $item->getStatements();
	
	// $statementCreator = $wbFactory->newStatementCreator();
	
	$editdesc = $props["desc"];
	$valadd = false;
	$valdel = false;
	
	// TO ADD array
	$toadd = [];
	// TO DELETE array
	$todelete = [];

	// Two actions: add, delete
	
	if ( array_key_exists( "add", $props ) ) {
		$toadd = $props["add"];
	}
	if ( array_key_exists( "delete", $props ) ) {
		$todelete = $props["delete"];
	}
	
	$numadd = 0;
	$numdel = 0;
	
	foreach ( $toadd as $add ) {
		
		if ( performActionPerId( $wbFactory, $id, $row, $add, $statementList, $wikiconfig, "add" ) ) {
			$numadd = $numadd + 1;
		}
	}
	
	foreach ( $todelete as $delete ) {
		
		if ( performActionPerId( $wbFactory, $id, $row, $delete, $statementList, $wikiconfig, "delete" ) ) {
			$numdel = $numdel + 1;
		}

	}
	
	
	if ( $numadd > 0 || $numdel > 0 ) {
		
		$saver->save( $revision, new MwDM\EditInfo( $editdesc ) );
		echo "~ Commited:\t".$id."\t".$row[0]."\n";

	}
	
}

function performActionPerId( $wbFactory, $id, $row, $props, $statementList, $wikiconfig, $type ){
	
	$propId = null;
	$propValue = null;
	$qualifierPropId = null;
	$qualifierValue = null;
	$refPropId = null;
	$refValue = null;
	
	$qualifierSnaks = null;
	$referenceSnaks = null;
	$referenceArray = null;

	if ( array_key_exists( "prop", $props ) ){
		$propId = resolveRowValue( $props["prop"], $row );
	}
	if ( array_key_exists( "propValue", $props ) ){
		$propValue = resolveRowValue( $props["propValue"], $row );
	}	
	if ( array_key_exists( "qualifier", $props ) ){
		$qualifierPropId = resolveRowValue( $props["qualifier"], $row );
	}
	if ( array_key_exists( "qualifierValue", $props ) ){
		$qualifierValue = resolveRowValue( $props["qualifierValue"], $row );
	}	
	if ( array_key_exists( "ref", $props ) ){
		$refPropId = resolveRowValue( $props["ref"], $row );
	}
	if ( array_key_exists( "refValue", $props ) ){
		$refValue = resolveRowValue( $props["refValue"], $row );
	}
	
	if ( $qualifierPropId && $qualifierValue ) {
		// Qualifier
		$qualifierSnaks = array(
			// TODO: This preassumes qualifier is datetime
			new WbDM\Snak\PropertyValueSnak( new WbDM\Entity\PropertyId( $qualifierPropId ), transformDate( $qualifierValue ) ),
		);
					
	}
	
	if ( $refPropId && $refValue ) {
		// Reference URL
		$referenceSnaks = array(
			// TODO: This preassumes reference is URL or string 
			new WbDM\Snak\PropertyValueSnak( new WbDM\Entity\PropertyId( $refPropId ), new DataValues\StringValue( $refValue ) ),
		);
		
		$referenceArray = array( new WbDM\Reference( $referenceSnaks ) );
		
	}
	
	if ( $propId && $propValue ) {
				
		$propIdObject = new WbDM\Entity\PropertyId( $propId );
		$itemId = retrieveWikidataId( $propValue, $wikiconfig );
		$itemIdObject = new WbDM\Entity\ItemId( $itemId );
		$entityObject = new WbDM\Entity\EntityIdValue( $itemIdObject );
		
		$statementListProp = $statementList->getByPropertyId(  $propIdObject );
		if( $statementListProp->isEmpty() ) {
			$mainSnak =
				new WbDM\Snak\PropertyValueSnak(
					$propIdObject,
					$entityObject
				);
			
			if ( $type === "add" ) {
				$statementList->addNewStatement( $mainSnak, $qualifierSnaks, $referenceArray );
				echo "added statement $propId : $propValue\n";
				return true;				
			}
			if ( $type === "delete" ) {
				return false; // Since removing go ahead
			}
			
		} else {

			$statementGuidToRemove = [];
			
			foreach ( $statementListProp as $statement ) {

				// Get Main Snak
				$mainSnak = $statement->getMainSnak();
				$datavalue = $mainSnak->getDataValue();
				
				if ( $datavalue->getEntityId()->getNumericId() === $itemIdObject->getNumericId() ) {
					
					$act = true;
					$qualifiersExist = false;
					$referencesExist = false;
															
					// Check qualifiers
					$qualifiers = $statement->getQualifiers();
					// Check references
					$references = $statement->getReferences();
					
					
					if ( $qualifierPropId && $qualifierValue ) {
						
						// If no qualifiers, add to statement
						if ( count( $qualifiers ) < 1 ) {
							
							if ( $type === "add" ) {
								$act = true;
							}
							if ( $type === "delete" ) {
								$act = false;
							}
						} else {
							
							$qualaddcount = 0;
							$qualcount = 0;
							
							// otherwise, add extra reference 
							foreach ( $qualifiers as $qualifier ) {
								
								// Get snaks
								$snaks = $qualifier->getSnaks();
								
								foreach ( $snaks as $snak ) {
									
									// $propertyPrev = "P".$snak->getPropertyId()->getNumericId();
									$valuePrev = $snak->getDataValue()->getValue();
									
									if ( $qualifierValue != $valuePrev ) {
										$qualaddcount = $qualaddcount + 1;
									} else {
										if ( $type === "delete" ) {
											$act = true;
										}
									}
									
									$qualcount = $qualcount + 1;
									
								}
								
							}
							
							if ( $qualaddcount >= $qualcount ) {
								if ( $type === "add" ) {
									$act = true;
								}
							}
						}
						
					}

					if ( $refPropId && $refValue ) {
						
						// If no references, add to statement
						if ( count( $references ) < 1 ) {
							
							if ( $type === "add" ) {
								$act = true;
							}
							if ( $type === "delete" ) {
								$act = false;
							}
						} else {
							
							$refaddcount = 0;
							$refcount = 0;
							
							// otherwise, add extra reference 
							foreach ( $references as $reference ) {
								
								// Get snaks
								$snaks = $reference->getSnaks();
								
								foreach ( $snaks as $snak ) {
									
									// $propertyPrev = "P".$snak->getPropertyId()->getNumericId();
									$valuePrev = $snak->getDataValue()->getValue();
									
									if ( $refValue != $valuePrev ) {
										$refaddcount = $refaddcount + 1;
									} else {
										if ( $type === "delete" ) {
											$act = true;
										}
									}
									
									$refcount = $refcount + 1;
									
								}
								
							}
							
							if ( $refaddcount >= $refcount ) {
								if ( $type === "add" ) {
									$act = true;
								}
							}
						}
						
					}
					
					if ( $act ) {
						
						if ( $type === "add" ) {
							
							if ( $qualifierSnaks ) {
								$statement->setQualifiers( new WbDM\Snak\SnakList( $qualifierSnaks ) );
							}
							
							if ( $referenceSnaks ) {
								$statement->addNewReference( $referenceSnaks );
							
							}
							
							return true;
						}
						
						if ( $type === "delete" ) {
						
							array_push( $statementGuidToRemove, $statement->getGuid() );

						}
					}
										
				}
				
			}
			
			foreach ( $statementGuidToRemove as $guid ) {
				if ( $guid ) {
					$statementList->removeStatementsWithGuid( $guid );
					echo "deleted statement $propId : $propValue\n";
				}
			}
			
			if ( count( $statementGuidToRemove ) > 0 ) {
				return true;	
			}
			
			return false;
		
		}

		
	}
	
}

/** Further resolve row value from row or beyond **/

function resolveRowValue( $rowValue, $row ) {
	
	
	return $rowValue;
	
}
