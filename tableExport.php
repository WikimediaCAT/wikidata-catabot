<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
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

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

$props = $confjson;

$wpapi = new MwApi\MediawikiApi( $wikiconfig['url'] );

$wpapi->login( new MwApi\ApiUser( $wikiconfig['user'], $wikiconfig['password'] ) );

$reader = Reader::createFromPath( $csvfile );

$reader->setOffset(0);
$reader->setDelimiter($props["delimiter"]);
$reader->setEnclosure($props["enclosure"]);

$results = $reader->fetch();
$string = "";

if ( array_key_exists( "header", $props ) ) {
	$string.= $props["header"];
}

$rowi = 0;

foreach ( $results as $row ) {
	
	$si = "|-\n| ";
	$ss = " || ";
	
	if ( $rowi == 0 ) {
		$si = "{| class=\"wikitable sortable\"\n! ";
	}
	if ( count( $row ) > 0 ) {
		
		if ( array_key_exists( "types", $props ) && $rowi > 0 ) {
			$row = processRow( $row, $props["types"] );
		}
		
		$string.= $si.implode( $ss, $row )."\n";
	}
	
	$rowi++;
}

$string.="|}\n";

if ( array_key_exists( "footer", $props ) ) {
	$string.= $props["footer"];

}

$contentTxt = getPageContent( $wpapi, $props["page"] );

if ( $contentTxt ) {

	$contentTxt = replaceContent( $contentTxt, $string, $props );

	// echo $contentTxt, "\n";
	putPage( $wpapi, $contentTxt, $props["page"] ); 

}

$wpapi->logout();

function processRow( $row, $types ) {
	
	$newRow = [];
	
	$c = 0;
	
	foreach ( $row as $el ) {
	
		if ( array_key_exists( $c, $types ) ) {
			if ( $types[$c] == "int" ) {
				
				if ( $el !== "" ) {
					$el = intval( $el );
				}
			}
		}
		
		$c++;
		
		array_push( $newRow, $el ); 
	}
	
	return $newRow;
}

function getPageContent( $wpapi, $page ) {

	$content = null;
	
	$params["titles"] = $page;
	$params["prop"] = "revisions";
	$params["rvslots"] = "*";
	$params["rvprop"] = "content";
	$params["formatversion"] = 2;

	$postRequest = new Mwapi\SimpleRequest( 'query', $params  );
	$outcome = $wpapi->postRequest( $postRequest );
	
	if ( $outcome ) {
		
		if ( array_key_exists( "query", $outcome ) ) {
			
			if ( array_key_exists( "pages", $outcome["query"] ) ) {
				
				$pagesQuery = $outcome["query"]["pages"];
				
				if ( count( $pagesQuery ) > 0 ) {
					$pageQuery = $pagesQuery[0];
					
					if ( array_key_exists( "revisions", $pageQuery ) ) {
						
						$revisions = $pageQuery["revisions"];
						
						if ( count( $revisions ) > 0 ) {

							$revision = $revisions[0];
							
							if ( array_key_exists( "slots", $revision ) ) {
								
								if ( array_key_exists( "main", $revision["slots"] ) ) {
									
									if ( array_key_exists( "content", $revision["slots"]["main"] ) ) {
										
										$content = $revision["slots"]["main"]["content"];
										
									}

								}

							}
						}
					}
				}
				
			}
		}
		
	}
	
	return $content;

}

function replaceContent( $contentTxt, $string, $props ) {

	$arr = explode( "\n", $contentTxt );

	$out = 0;
	$pre = 0;
	$post = 0;
	$preArr = [];
	$postArr = [];
	
	foreach ( $arr as $line ) {
		

		if ( strpos ( $line, $props["starttag"] ) !== false ) {
			$out = 1;
			$pre = 1;
		}
		
		if ( $out == 0 && $pre == 0 ) {
			
			if ( $post == 0 ) {
				array_push( $preArr, $line );
			} else {
				array_push( $postArr, $line );
			}
		}
		
		if ( strpos ( $line, $props["endtag"] )  !== false ) {
			$out = 0;
			$post = 1;
			$pre = 0;
		}
	}
	
	return implode( "\n", $preArr ). "\n". $props["starttag"]. "\n" . $string . "\n" . $props["endtag"] . "\n" . implode( "\n", $postArr );


}

function putPage( $wpapi, $contentTxt, $page ) {
	
	$params = array( "meta" => "tokens" );
	$getToken = new Mwapi\SimpleRequest( 'query', $params  );
	$outcome = $wpapi->postRequest( $getToken );
	$summary = "Actualitza Catabot";
	
	if ( array_key_exists( "query", $outcome ) ) {
		if ( array_key_exists( "tokens", $outcome["query"] ) ) {
			if ( array_key_exists( "csrftoken", $outcome["query"]["tokens"] ) ) {
				
				$token = $outcome["query"]["tokens"]["csrftoken"];
				$params = array( "title" => $page, "summary" => $summary, "text" => $contentTxt, "token" => $token );
				$sendText = new Mwapi\SimpleRequest( 'edit', $params  );
				$outcome = $wpapi->postRequest( $sendText );			
			
			}				
		}
	}
	
}
