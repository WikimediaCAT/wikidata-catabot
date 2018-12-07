# -*- coding: utf-8 -*-
import pywikibot
import codecs
import re
import csv

def cmp_data(wbtim,anny,mes,dia):
   if wbtim.year > anny:
      return 1
   if wbtim.year < anny:
      return -1
   if wbtim.month > mes:
      return 1
   if wbtim.month < mes:
      return -1
   if wbtim.day < dia:
      return 1
   if wbtim.day < dia:
      return -1
   return 0

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
      urldelaref.setTarget("http://ced.uab.es/infraestructures/banc-de-dades-espanya-i-catalunya/evolucio-i-continuitat-dels-municipis-catalans-1497-2002/")

      # Ara, la data, amb precisió d'any
      if any_cens<= 1582:
          calendari='http://www.wikidata.org/entity/Q1985786'   # julià
      else:
          calendari='http://www.wikidata.org/entity/Q1985727'   # gregorià
      datainfo = pywikibot.WbTime(year=any_cens,precision='year',calendarmodel=calendari)
      qualif = pywikibot.Claim(self.repowd,'P585')
      qualif.setTarget(datainfo)

      # Un altre qualificador, mètode de determinació ('P459')
      qualif2 = pywikibot.Claim(self.repowd,'P459')
      if propietat == 'P1082':
        # el mètode és el padró municipal d'habitants, o Q745221 si és població
        qualif2.setTarget(pywikibot.ItemPage(self.repowd,"Q745221"))
      elif propietat == 'P1538':
        # el mètode és el fogatge, o Q2361901 si és nombre de llars
        qualif2.setTarget(pywikibot.ItemPage(self.repowd,"Q2361901"))
      else:
        print u'Propietat incorrecta a wd_actualitzar_pob',propietat

      # Ara la referència "Editorial" ('P123') a Centre d'Estudis Demogràfics
      ref2 = pywikibot.Claim(self.repowd,'P123')
      ref2.setTarget(pywikibot.ItemPage(self.repowd,"Q25892981"))   # CED

      # una altra referència: data de consulta (P813) = 25-11-19
      data_consulta = pywikibot.WbTime(year=2018,month=11,day=25,precision='day')
      data_item = pywikibot.Claim(self.repowd,'P813')
      # construim l'item
      data_item.setTarget(data_consulta)

      # Construïm la llista de referències
      lrefs=[data_item,ref2,urldelaref]

      self.item.addClaim(pob_clm)
      pob_clm.addQualifier(qualif)
      pob_clm.addQualifier(qualif2)
      pob_clm.addSources(lrefs)

    def wd_nom_municipi(self):
      try:
        toret = self.item.labels['ca']
      except:
        toret = u"La Label del municipi no existeix!!"
      return toret

    def wd_anys_amb_poblacio(self,propietat):
      toret = set()
      # això ho retornarem al final
      p = propietat
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
                   print "Atenció, dades de població sense any o amb massa anys a ".encode("utf-8"),self.item.labels['ca']
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
        print u"Atenció, ".encode("utf-8")+self.item.labels['ca']+" ("+self.item.id+u") té anomalia a la P31".encode("utf-8")

    def wd_posar_preferit(self,propietat):
       if propietat in self.item.claims:
         llista = self.item.claims[propietat]
         max_any = -10000
         max_mes = 1
         max_dia = 1
         n_anys = 0
         error_indeterminat = False
         algun_preferred = False
         preferits = []
         for clm in llista:
            n_anys = n_anys + 1
            wdestad = clm.getTarget()
            wdrank = clm.getRank()
            if wdrank == "preferred":
               algun_preferred = True
            try:
               wdqual = clm.qualifiers['P585']
            except KeyError:
               wdqual = []
               error_indeterminat = True
            for p in wdqual:
               tgttime = p.getTarget()
               if cmp_data(tgttime,max_any,max_mes,max_dia)>0:
                   max_any = tgttime.year
                   max_mes = tgttime.month
                   max_dia = tgttime.day
                   clmbo = clm

         if algun_preferred:
            pass
         elif error_indeterminat:
            pass
         elif n_anys < 2:
            pass
         else:
            resultat = clmbo.changeRank('preferred')
           
    # Constructors, un a partir de la Q i un a partir de la pàgina
    # Ja fan l'item.get perquè així la resta ja poden accedir al contingut
    # de l'item
    def wd_item_des_de_q(self, q):
       # descomentar si es fan proves amb sandbox
       # q = 'Q4115189'
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
    prop_ant = ""
    canvi_prop = False
    for fila in lector:
       # Saltem les files que no tinguin informació, capçaleres i tal
       # Ho detectem perquè el tercer camp té amplada 5
       if len(fila[2])!= 5:
           continue
       municipi = fila[2]
       any_cens = int(fila[3])
       poblacio = fila[4]
    
       if municipi != muni_ant:     # Canvi de municipi, cal llegir la Q
          muni_ant = municipi
          try:
            q = dicc_excel[municipi]
          except KeyError:
            # Això no hauria de passar mai
            print "No trobem la Q del municipi "+municipi+" de l'Excel"
            return
          item_muni = wd.wd_item_des_de_q(q)
          nom_muni = wd.wd_nom_municipi()
          print nom_muni.encode("utf-8")
          # fem el mateix per les dues propietats per no matxacar info
          anys_omplerts_pob = wd.wd_anys_amb_poblacio('P1082')
          anys_omplerts_fog = wd.wd_anys_amb_poblacio('P1538')
          #print anys_omplerts_pob, anys_omplerts_fog
       # Ara ja tenim seleccionat a Wikidata el municipi i sabem quins
       # anys ja tenen informació. Omplim la resta

       if poblacio == '0':
          print "La poblacio de l'any",any_cens,"a ",nom_muni,u" és 0".encode("utf-8")
          continue

        # Per anys < 1700, el que tenim són fogatges (P1538 = nombre de llars)
        # Per la resta, tenim població (P1082)
       if any_cens < 1700:
         propietat = 'P1538'
       else:
         propietat = 'P1082'
         if prop_ant != propietat:  # quan passem de fogatges a població
            canvi_prop = True
            # si no fem això, wd_posar_preferit peta. Probablement per un bug
            # de pywikibot que fa petar el claim.toJSON()
            # No caldria fer-ho, però així no peta
            item_muni = wd.wd_item_des_de_q(q)

       # Ara aprofitem que els anys ens vénen per ordre, per arreglar el
       # camp preferit als fogatges
       # Posarem el flag de preferit a l'any més alt dels fogatges. Més que
       # res, perquè hi ha un altre bot que posa aquest flag i de retruc
       # canvia l'any de julià a gregorià.
       # Ho podríem fer al final, però és que l'altre bot és molt ràpid
       if canvi_prop:
          canvi_prop = False
          wd.wd_posar_preferit('P1538')
       prop_ant = propietat

       # Si l'any que llegim ja és a Wikidata, saltem la dada
       if any_cens > 1700 and any_cens in anys_omplerts_pob:
          print "L'any ",any_cens,"a ",nom_muni,u"ja és a wikidata".encode("utf-8")
          continue
       if any_cens <= 1700 and any_cens in anys_omplerts_fog:
          print "L'any ",any_cens,"a ",nom_muni,u"ja és a wikidata".encode("utf-8")
          continue

       wd.wd_actualitzar_pob(propietat,poblacio,any_cens)


if __name__ == '__main__':
    try:
        main()
    finally:
        pass
