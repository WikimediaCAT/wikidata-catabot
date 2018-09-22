# wikidata-catabot

Bots per a utilitzar a Wikidata

* importCSV.php -> Importa a Wikidata a partir d'un document CSV i uns paràmatres (propietats, qualificadors, etc.) predefinits en un JSON de configuració.
* batchAction.php -> Script per a realitzar accions automàtiques d'afegir o eliminar propietats/qualificadors/referències a partir d'una llista d'identificadors.
* addFromORCID.php -> Script per afegir informació a Wikidata a partir de llistats d'ORCID.
* preProcessCSV.php -> Script per preprocessar fitxers CSV consultant certes columnes a Wikidata. És recomanable processar els CSV amb aquest script abans d'utilitzar els altres per tal d'evitar generar duplicats innecessaris a Wikidata.

## TODO

* Refactoració. Compartir codi en funcions/classes.
* Gestionar updates en el batch, sobretot quan no és una entitat.

### ORCID

* Revisar si existeixen o no ja els ORCID a Wikidata.
	* Si no hi són, crear-los.
* Recuperar informació d'ORCID via https://packagist.org/packages/hubzero/orcid-php





