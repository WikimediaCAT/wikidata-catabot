# wikidata-catabot

Bots per a utilitzar a Wikidata

* importCSV.php -> Importa a Wikidata a partir d'un document CSV i uns paràmatres (propietats, qualificadors, etc.) predefinits en un JSON de configuració.
* batchAction.php -> Script per a realitzar accions automàtiques d'afegir o eliminar propietats/qualificadors/referències a partir d'una llista d'identificadors.

## TODO

* addFromORCID.php -> Script per afegir informació a Wikidata a partir de llistats d'ORCID.

### Passos

* Revisar si existeixen o no ja els ORCID a Wikidata.
* Si no hi són, crear-los.
* Recuperar informació d'ORCID via https://packagist.org/packages/hubzero/orcid-php
* Llistar IDs de Wikidata per poder utilitzar-los per aplicar batchAction.php si s'escau. 





