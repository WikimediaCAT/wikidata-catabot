#/usr/bin/env bash

curl -H "Accept: text/csv" "https://query.wikidata.org/sparql?query=SELECT%20%20%3Fdata%20%3Fcasos%20%3Fmorts%20%3Frecuperacions%20%20%7B%0A%20%20wd%3AQ88177037%20wdt%3AP527%20%3Fstatecases.%0A%20%20%3Fstatecases%20wdt%3AP276%20wd%3AQ20105331%20.%0A%20%20%3Fstatecases%20p%3AP1603%20%3Fcasestmt%20.%0A%20%20%3Fcasestmt%20ps%3AP1603%20%3Fcasos%20%3B%20pq%3AP585%20%3Fdata.%0A%20%20%3Fstatecases%20p%3AP1120%20%3Fdeathst%20.%0A%20%20%3Fdeathst%20ps%3AP1120%20%3Fmorts%20%3B%20pq%3AP585%20%3Fdata.%0A%20%20OPTIONAL%20%7B%0A%20%20%3Fstatecases%20p%3AP8010%20%3Frecuperat%20.%0A%20%20%3Frecuperat%20ps%3AP8010%20%3Frecuperacions%3B%20pq%3AP585%20%3Fdata.%0A%20%20%7D%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22ca%2Cca%2Cen%22.%20%7D%0A%7D%0Aorder%20by%20DESC(%3Fdata)%0A" | perl -lane 's/T00:00:00Z//g; print;' > /tmp/brotIgualada.csv 
FILESIZE=$(stat -c%s /tmp/brotIgualada.csv)

if [ "$FILESIZE" -gt 10 ]; then

	echo "$FILESIZE"
fi

