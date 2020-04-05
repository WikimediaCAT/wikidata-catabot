<?php

/** Helping functions **/

function resolveValue( $rowValue, $cache, $wikiconfig, $wikidataconfig ) {

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
		
			$wdid = retrieveWikidataId( $rowValue, $wikiconfig, $wikidataconfig );
			
			if ( $wdid ) {
				
				$cache[ $rowValue ] = $wdid;
				$rowValue = $wdid;
				
			}
		
		}
	}
	
	return trim( $rowValue );
	
}

function resolveDate( $rowValue, $schema, $schemaout ) {
	
	if ( ! $schema ) {
		$schema = "d-m-Y";
	}
	
	if ( ! $schemaout ) {
		$schemaout="Y-m-d";
	}
	
	$date = DateTime::createFromFormat( $schema, $rowValue );

	if ( $date ) {
		return date_format( $date, $schemaout );
	} else {
		return $rowValue;
	}
}



function retrieveWikidataId( $title, $wikiconfig, $wikidataconfig ){

	$wdid = null;
	
	# If Q value
	if ( preg_match( "/^Q\d+/", $title ) ) {
		
		$wdid = $title;
		
	} else {
	
		sleep( 5 );

		$title = str_replace( " ", "_", $title );
		
		// This is for getting all associated Wikidata ID
		// $url = $wikiconfig["url"]."?action=query&prop=wbentityusage&titles=".$title."&format=json";
		
		// Below for main WikiData ID
		// TODO: Adding retry
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
		
		// If not matches search in Wikidata
		if ( ! $wdid ) {
			
			sleep( 5 );

			if ( $wikidataconfig["langs"] ) {
				
				$langs = $wikidataconfig["langs"];
				
				foreach ( $langs as $lang ) {
					
					// TODO: Adding retry
					$url = $wikidataconfig["url"]."?action=wbsearchentities&search=".$title."&format=json&language=".$lang;
					
					// Process url
					$json = file_get_contents( $url );
				
					// Proceess JSON
					$obj = json_decode( $json, true );
				
					if ( $obj ) {
						
						if ( array_key_exists( "search", $obj ) ) {
						
							$searchResults = $obj["search"];
							if ( count( $searchResults ) > 0 ) {
								
								foreach ( $searchResults as $searchResult ) {
									
									if ( array_key_exists( "match", $searchResult ) ) {
										
										$typeMatch = $searchResult["match"]["type"];
										$langMatch = $searchResult["match"]["language"];

										if ( $typeMatch === "label" && $langMatch === $lang ) {
											$wdid = $searchResult["id"];
											
											break;
										}
									}
									
								}
								
							}
						
						}
						
					}
					
				}
			}
			
		}
	}
	
	return $wdid;
}