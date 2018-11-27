# -*- coding: utf-8 -*-
import pywikibot
import codecs
import re
import csv

class WDint:
    """ Interfície amb wikidata                     """
    """ wdsite: Site de wikidata                    """
    """ repowd: repositori                          """
    """ item:   item seleccionat                    """

    def get_item(self):
       return self.item

    def wd_actualitzar_pob(self,propietat,poblacio,any_cens):
      # creem el Claim de població o fogatge, el que toqui
      pob_clm = pywikibot.Claim(self.repowd,propietat)
      pob_clm.setTarget(pywikibot.WbQuantity(amount=poblacio,site=self.repowd))

      # Ara, la URL de la referència
      urldelaref = pywikibot.Claim(self.repowd,'P854')
      urldelaref.setTarget("http://ced.uab.cat/catalonia/evolucio/Poblacio%20de%20fet.zip")
      lrefs=[urldelaref]

      # Ara, la data, amb precisió d'any
      datainfo = pywikibot.WbTime(year=any_cens,precision='year')
      qualif = pywikibot.Claim(self.repowd,'P585')
      qualif.setTarget(datainfo)

      # Un altre qualificador, mètode de determinació ('P459')
      qualif2 = pywikibot.Claim(self.repowd,'P459')
      # el mètode és el padró municipal d'habitants, o Q745221
      qualif2.setTarget(pywikibot.ItemPage(self.repowd,"Q745221"))

      self.item.addClaim(pob_clm)
      pob_clm.addQualifier(qualif)
      pob_clm.addQualifier(qualif2)
      pob_clm.addSources(lrefs)

    def wd_anys_amb_poblacio(self):
      toret = set()
      # això ho retornarem al final
      for p in ['P1082','P1538']:       # fem el mateix per les dues propietats
        if p in self.item.claims:
          llista = self.item.claims[p]
          for censos in llista:
             tg = censos.getTarget()
             # la població seria tg.amount, però no ens interessa, 
             # només el seu qualificador, si és data

             if 'P585' in censos.qualifiers:      # P585 és la data
                 ldata = censos.qualifiers['P585']    # de fet dóna una llista
                 ndates = 0
                 for dates in ldata:              # recorrem la llista
                   ndates = ndates + 1          # n'hi hauria d'haver només una
                   data_info = dates.getTarget()

                   # ens quedem amb l'any en forma de string
                   lng_any = data_info.year
                   toret.add(lng_any)    # anem omplint el conjunt
                 if ndates != 1:
                   print "Atenció, dades de població sense any o amb massa anys a ",self.item.labels['ca']
      return toret

    def wd_comprovar_municipi(self):
    # Aprofitant que passem, comprovem que sigui
    # municipi de Catalunya i no d'Espanya
      #print self.item.labels['ca']
      if 'P31' in self.item.claims:
        llista = self.item.claims['P31']
        municat = False
        muniesp = False
        for quees in llista:
           tg = quees.getTarget()
           if tg.id == 'Q33146843':     # Municipi de Catalunya
              municat = True
           if tg.id == 'Q3055118':      # Entitat singular de Població
              municat = True            # cas de Barruera, ho admetem
           if tg.id == 'Q2074737':      # Municipi d'Espanya
              muniesp = True

      if not municat or muniesp:
        print u"Atenció, "+self.item.labels['ca']+" ("+self.item.id+u") té anomalia a la P31"

    # Constructors, un a partir de la Q i un a partir de la pàgina
    # Ja fan l'item.get perquè així la resta ja poden accedir al contingut
    # de l'item
    def wd_item_des_de_q(self, q):
       # proves amb sandbox
       q = 'Q4115189'
       try:
           self.item = pywikibot.ItemPage(self.wdsite,q)
       except:
           print "Q incorrecta rebuda a wd_q_des_de_q: "+q
       self.item_dict = self.item.get()
       return self.item

    def wd_item_des_de_pag(self,pagina):
       try:
           self.item = pywikibot.ItemPage.fromPage(pagina,self.wdsite)
       except pywikibot.NoPage:
           print u"Article ",article," sense wikidata"
           return
       self.item_dict = self.item.get()
       return self.item

    def __init__(self):
       self.wdsite = pywikibot.Site("wikidata", "wikidata")
       self.repowd = self.wdsite.data_repository()
       self.item = None

def main():
    # Inicialitzacions
    wd = WDint()        # instància per cridar la classe wikidata

    dicc_excel = {}
    # Llegim la llista de municipis i la posem en un diccionari per obtenir la Q
    with codecs.open("./municipis.in","r") as fin:
        lector = csv.reader(fin)
        for fila in lector:
            codi_excel = fila[0]
            laq = fila[1]
            dicc_excel[codi_excel] = laq

    # Aquest tros de codi comprova que els municipis surtin com a
    # Municipi de Catalunya o entitat singular de població
    # Recordem que municipi de Catalunya és instància de municipi d'Espanya
    # o sigui que no hi hauria d'haver polèmiques
    #for muni in dicc_excel.values():
        #item = wd.wd_item_des_de_q(muni)
        #wd.wd_comprovar_municipi()

    # Anem al procés de dades en si
    dades = codecs.open("dades/dades.csv","r")
    lector = csv.reader(dades)
    muni_ant = ""
    for fila in lector:
       # Saltem les files que no tinguin informació, capçaleres i tal
       # Ho detectem perquè el tercer camp té amplada 5
       if len(fila[2])!= 5:
           continue
       municipi = fila[2]
       any_cens = int(fila[3])
       poblacio = fila[4]
    
       if municipi != muni_ant:     # Canvi de municipi, cal llegir la Q
          try:
            q = dicc_excel[municipi]
          except KeyError:
            # Això no hauria de passar mai
            print "No trobem la Q del municipi "+municipi+" de l'Excel"
            return
          item_muni = wd.wd_item_des_de_q(q)
          anys_omplerts = wd.wd_anys_amb_poblacio()
          print anys_omplerts
       # Ara ja tenim seleccionat a Wikidata el municipi i sabem quins
       # anys ja tenen informació. Omplim la resta

       # Si l'any que llegim ja és a Wikidata, saltem la dada
       if any_cens in anys_omplerts:
          print "L'any ",any_cens,u"ja és a wikidata"
          continue

        # Per anys < 1700, el que tenim són fogatges (P1538 = nombre de llars)
        # Per la resta, tenim població (P1082)
       if any_cens < 1700:
         propietat = 'P1538'
       else:
         propietat = 'P1082'

       wd.wd_actualitzar_pob(propietat,poblacio,any_cens)

if __name__ == '__main__':
    try:
        main()
    finally:
        pass
