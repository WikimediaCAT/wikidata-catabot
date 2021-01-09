import pywikibot
import time

def main():

    ent = open("entrada","r")

    wdsite = pywikibot.Site("wikidata","wikidata")

    for pagina in ent:
        pagina = pagina.rstrip()

        item = pywikibot.ItemPage(wdsite, pagina)
        try:
           item_dict = item.get()
        # Passa amb alguns items, com Q146030. Possiblement un bug. Ho saltem.
        except pywikibot.exceptions.UnknownSite:
           print("rebut unknown site a %s !!!!!!!!" % pagina)
           continue
        except pywikibot.exceptions.NoUsername:
           print("rebut NoUsername a %s !!!!!!!!" % pagina)
           continue
           #time.sleep(10)
           #item = pywikibot.ItemPage(wdsite, pagina)
           #item_dict = item.get()
        except Exception as error:
           print("Problema fent item.get()")
           raise(error)
        #print(type(item))
        #print(dir(item))

        if "ca" in item.labels:
            nom_art = item.labels["ca"]
            print("%s: %s" % (nom_art,pagina))
        else:
            print("Item %s sense etiqueta en català" % pagina)
        try:
            item_imatges = item.claims["P18"]
        except:
            print("No hi ha imatges, saltem %s:%s" % (pagina,nom_art))
            continue
        try:
            # resseguim totes les imatges de la Q
            for imatge in item_imatges:
                llegenda = ""
                try:
                   # la llegenda és la propietat 2096, n'hi poden haver
                   # en diverses llengües
                   llista_llegendes=imatge.qualifiers["P2096"]
                   for llegendes in llista_llegendes:
                      llegendai = llegendes.getTarget()
                      if llegendai.language == "ca":
                          llegenda = llegendai.text
                          # ja tenim la llegenda en català, no cal mirar-ne més
                          break
                      else:
                          #print("Hi ha una llegenda en ",llegendai.language)
                          pass
                except:
                   continue     # si no hi ha llegendes, no cal continuar
                   pass
                   #print("no hi ha llegenda a",nom_art)
                representa=""
                try:
                   representaq = imatge.qualifiers["P180"]
                   if len(representaq) > 1:
                       print("Ull, hi ha molts representes!!!!!")
                       print(representaq)
                   for representes in representaq:
                       # Normalment aquest bucle només hi passa una vegada.
                       # Només en dos casos, la foto "representa" dues
                       # coses diferents.
                       # Cap d'aquests dos casos és significatiu, i per tant
                       # no ens hi matem més.
                       representa = representes.getTarget()
                except:
                   # aquesta imatge no té representa, no cal continuar
                   continue
                   pass

                #print(llegenda)
                #print(representa)
                if representa != "" and llegenda != "":
                   #print("Representa és ", representa)
                   item2_dict = representa.get()
                   if "ca" in representa.labels:
                       representa_label = representa.labels["ca"]
                       #print(representa_label)
                   if representa_label.lower().strip() == llegenda.lower().strip():
                       print("!!!!!!S'ha d'esborrar la llegenda de ", nom_art,representa)
                       #print("Esborrem")
                       #print(type(llegendes))
                       #print(llegendes)
                       #
                       #imatge.removeQualifier(llegendes)
                       #
                       #print(llegenda)
                       #print(representa)
                       #print(representa_label)
               #llegenda = item_imatge.claims["P2096"]
               #print(llegenda)
        except Exception as error:
            raise(error)



if __name__ == '__main__':
    try:
        main()
    finally:
        pywikibot.stopme()

