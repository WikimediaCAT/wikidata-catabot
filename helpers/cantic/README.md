# Omplir VIAF de CANTIC

https://query.wikidata.org/#SELECT%20%3Fpersona%20%3FpersonaLabel%20%3Fcantic%20WHERE%20%7B%0A%0A%20%20%20%20%3Fpersona%20wdt%3AP31%20wd%3AQ5%20.%20%23%20person%0A%20%20%20%20%3Fpersona%20wdt%3AP21%20%3Fgenere%20.%0A%20%20%20%20%3Fpersona%20wdt%3AP1273%20%3Fcantic%20.%0A%20%20%20%20FILTER%20NOT%20EXISTS%20%7B%20%3Fpersona%20wdt%3AP214%20%3Fviaf%20%20%7D%0A%20%20%20%20%0A%0A%20%20%20%20SERVICE%20wikibase%3Alabel%20%7B%0A%20%20%20%20%20%20%20bd%3AserviceParam%20wikibase%3Alanguage%20%22ca%22%0A%20%20%20%20%7D%0A%7D

perl -F, -lane 'chomp($_); if ( $. == 1 ) { print $_.",viaf"; } else { $viaf=`php getViafFromCantic.php $F[2]`; print $_.",".$viaf;sleep(1); } ' ../../../../cantic.csv > cantic-viaf.csv

perl -ne 's/http\:\/\/www.wikidata.org\/entity\///g; print;' cantic-viaf.csv > cantic-viaf.clean.csv; mv cantic-viaf.clean.csv cantic-viaf.csv

php batchAction.php ../batch-cantic2viaf.json helpers/cantic/cantic-viaf.csv cantic2viaf

