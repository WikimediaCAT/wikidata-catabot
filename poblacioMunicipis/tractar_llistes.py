# -*- coding: utf-8 -*-

import codecs
import re
#import pywikibot
import json
import csv
from fuzzywuzzy import fuzz

def buscar_fuzzy(cadena, dicc):
    max_ratio = 0
    for poble in dicc.keys():
      ratio = fuzz.ratio(poble,cadena)
      if ratio > 70:    # si no passa de 70, ni ens ho mirem
        if ratio > max_ratio:
           max_ratio = ratio
           currentmax = poble
    if max_ratio > 0:
      print "Fuzzy match de ",currentmax,"amb",cadena
      return dicc[currentmax], currentmax
    return "",""

# Adaptem la sintaxi del full de càlcul a la del fitxer amb les Q's
def posar_articles(cadena):
   # si al nom hi surt St. o Sta., ho substituïm per Sant i Santa
   cadena = cadena.replace("Sta.","Santa")
   cadena = cadena.replace("St.","Sant")
   # de vegades acaben en espai, el traiem
   cadena = cadena.strip()
   # si el nom del municipi té una coma, s'ha de passar el que hi hagi després
   # de la coma al principi. Per exemple: "Arboç, l'" ha de ser "l'Arboç"
   pos=cadena.find(',')
   if pos>0:
      try:
        try:
          apostrof= (cadena[pos+3]=="'")
        except:
          apostrof = False
        if apostrof:   # si hi ha apòstrof, ho enganxem
            toret = cadena[pos+2:]+cadena[0:pos]
        else:                    # si l'article no té apòstrof, afegim espai
           toret = cadena[pos+2:]+" "+cadena[0:pos]
      except:
        print "El nom de municipi "+cadena+u"no té espai després de la coma"
      return toret.strip()
   else:
      return cadena.strip()

def saltafinslaq(cadena):
   pos=cadena.find("Q")
   return cadena[pos:]

def main():

  f = open("dades/municipis_cac.json","r")

  # Fitxer amb resultats, avisarà amb "fuzzy" si s'ha de revisar
  fout = codecs.open("dades/municipis_q.csv","w","utf-8")

  # Fitxer amb municipis que no s'han trobat
  fout2 = codecs.open("dades/municipis_manual.csv","w","utf-8")

  # Fem un diccionari a partir del JSON amb les Q's per poder-les localitzar
  # Si consultes dicc["Barcelona"] donarà Q1492
  # Ull amb el tema encode, que si no, no trobava els noms amb accent
  # Potser hauria hagut d'obrir amb codecs.open, però ara ja està fet
  munidict = json.load(f)

  dicc={}
  for i in range(1,len(munidict)):
      dicc[munidict[i]["itemLabel"].encode("utf-8")]=saltafinslaq(munidict[i]["item"])

  # Ara fem el processament del fitxer de municipis
  # Llegim dades csv
  with open("dades/municipis.csv","r") as f2:
    lector = csv.reader(f2,delimiter=',', quotechar='"')

    for fila in lector:
       # Adaptem l'string a buscar
       buscadict = posar_articles(fila[1])
       # per si de cas, posem la primera lletra en majúscula (cas el Brull)
       buscadict = buscadict[0].upper()+buscadict[1:]
       print buscadict
       fuzzy = False
       try:
          saberlaq = dicc[buscadict]
       except:
         try:
           # si no el trobem, busquem amb la primera lletra en minúscula
           saberlaq = dicc[buscadict[0].lower()+buscadict[1:]]
         except:
           # ara hem de fer virgueries, buscant match aproximat
           saberlaq,quinaq = buscar_fuzzy(buscadict,dicc)
           fuzzy = True
          # if len(saberlaq)>0:
          #    print "Trobat fuzzy", buscadict, saberlaq
       if len(saberlaq)>0 and not fuzzy:
          fout.write(fila[0]+","+saberlaq+'\n')
       elif len(saberlaq)>0:
          fout.write(fila[0]+","+saberlaq+', fuzzy '+buscadict.decode("utf-8")+', Q de '+quinaq.decode("utf-8")+'\n')
       else:
          # els que no hem pogut trobar, els passem a un fitxer separat
          fout2.write(fila[0]+","+buscadict.decode("utf-8")+'\n')

if __name__ == '__main__':
    try:
        main()
    finally:
        pass
