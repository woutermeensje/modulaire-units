# Units – wat er in een offerte moet staan

Specificatie voor de offertetool in de adminomgeving (`/account/offerte-samenstellen`). Bedoeld voor Claude en Codex: welke informatie een admin per offerte moet kunnen meesturen naar een klant, van vloer tot isolatie en van afmeting tot voorwaarden.

**Bron:** een technische offerte van Moravia Containers voor een kantine van vijf gekoppelde modules (8 pagina's plus plattegrond, november 2026). Die offerte is in vertrouwen gedeeld. Daarom staan hier wel de categorieën en het detailniveau, maar **geen prijzen, offertenummer, klant of locatie** van dat project. De standaard 6x3-unit van Moravia staat apart in [moravia.md](moravia.md).

## Afspraken vooraf

1. **Voorbeeldwaarden zijn geen standaard.** Alle waarden onder "Voorbeeld" komen uit dat ene referentieproject. Gebruik ze nooit als default, seeddata of template. Echte waarden vul je per leverancier, unittype en project in.
2. **Verzin nooit technische prestaties.** Ontbreekt een U-waarde, brandklasse, belasting of vermogen, dan staat het veld op *onbekend* of *nog te bevestigen bij leverancier*. Liever leeg dan fout.
3. **Houd de publieke configurator eenvoudig.** Alles in dit document komt in de offertetool (achter `$quoteExtras`), niet in `/unit-samensteller/`, tenzij Wouter iets anders vraagt.
4. **`App\Support\UnitConfiguration::normalize()` blijft de bron van waarheid** voor de samenstelling (maat, ramen, deuren, kleuren). De offertetool bouwt daarop voort en maakt geen tweede versie van die gegevens.
5. **Vaste regels, geen keuzes:** het stalen frame heeft de kleur van de wanden, kozijnen zijn altijd wit (RAL 9010) en elektrische verwarming zit standaard in elke unit. Velden hieronder als "kozijnkleur" of "framekleur" zijn dus alleen weergave in de PDF, geen invoer.
6. **Kleinste versie eerst.** Bouw niet alles tegelijk. Zie [Prioriteit](#prioriteit-wat-eerst) onderaan.
7. **Interne gegevens nooit naar de klant.** Inkoopprijzen, leverancierskortingen, marges, contactgegevens van de leverancier en interne notities komen nooit in de klant-PDF, de mail of een publieke response.

## Status per regel

Elke specificatie of prijsregel in de offerte krijgt één van deze statussen:

| Status | Betekenis in de PDF |
|---|---|
| Inbegrepen | Zit in de prijs |
| Optioneel | Kan erbij, met meerprijs; telt niet mee in het totaal |
| Niet inbegrepen | Expliciet uitgesloten; klant regelt het zelf of het wordt apart geoffreerd |
| Nog te bepalen | Nog niet bekend of nog navragen bij leverancier |
| Op nacalculatie | Kosten volgen achteraf op basis van werkelijk gebruik |

Waar nodig heeft een regel ook: waarde, eenheid, aantal, omschrijving en meerprijs.

---

## De 18 onderdelen van een offerte

### 1. Projectgegevens

- Offertenummer, versie/revisie, offertedatum, geldig tot
- Klant: naam, adres, KvK en btw-nummer (indien relevant), contactpersoon
- Projectnaam of referentie
- Afleveradres / locatie
- Toepassing: kantoor, kantine, sanitair, opslag, wonen, overig
- Koop of huur
- Gewenste leverdatum, startdatum en (bij huur) looptijd
- Begeleidende tekst
- Interne notities (nooit afdrukken)

### 2. Afmetingen en modules

- Type/model en leverancier per module
- Aantal modules en aantal gekoppelde gebouwen
- Buitenmaat per module: lengte × breedte × hoogte in mm
- Binnenhoogte en, indien bekend, vrije binnenmaten
- Totale gebouwmaat
- Oppervlakte: houd **bruto/nominaal** en **netto gebruiksoppervlak** apart
- Opstelling: los, naast elkaar, gestapeld, maatwerk; aantal verdiepingen
- Plattegrond als bijlage (bestaat al: wordt in de browser gemaakt en geüpload)

> Voorbeeld: 5 modules van 6.058 × 2.438 × 2.860 mm, binnenhoogte 2.500 mm, naast elkaar, samen ca. 12,25 × 6,06 m en 73,4 m² nominaal.

### 3. Constructie (staalframe)

- Constructietype (gelast/geschroefd) en staaldikte
- Stapelbaarheid en de statische voorwaarden daarvoor
- Heftruckopeningen en hijsvoorzieningen: wel of niet
- Koppelmogelijkheden
- Statische berekening: inbegrepen of niet

> Voorbeeld: gelaste staalconstructie 3–4 mm, stapelbaar volgens statische berekening, geen heftruckopeningen, statische berekening niet inbegrepen.

### 4. Buitenafwerking en corrosiebescherming

- Kleur frame en gevel (frame = wandkleur, vaste regel)
- Gevelbeplating: materiaal, profiel, plaatdikte
- Anticorrosie: primer, toplaag, totale laagdikte, corrosieklasse
- Opgegeven levensduur coating
- Optioneel: andere gevelmaterialen, branding/logo

> Voorbeeld: gegalvaniseerde profielplaat 0,55 mm, RAL 7016, corrosieklasse C3M, 120 µm totaal (70 µm primer + 50 µm toplaag), coating 7–15 jaar.

### 5. Isolatie

Per onderdeel apart vastleggen: **dak, buitenwand, koppelwand, vloer**.

| Veld | Eenheid |
|---|---|
| Materiaal en brandklasse materiaal | bijv. minerale wol, A1 |
| Dikte | mm |
| Lambda (warmtegeleiding) | W/mK |
| U-waarde (alleen als aangetoond) | W/m²K |
| Dampremmende laag | materiaal + dikte |
| Isolatieberekening/bewijs | inbegrepen of niet |

> Voorbeeld: dak 100 mm (U < 0,48), buitenwand 100 mm (U < 0,40), koppelwand 60 mm (U niet vermeld), vloer 100 mm (U < 0,63). Alles minerale wol A1, λ 0,035 W/mK, PE-folie 0,1 mm. Isolatiebewijs niet inbegrepen.

Waarom belangrijk: klanten met energie-eisen of een vergunningsaanvraag hebben de U-waarden nodig. Hoe lager de U-waarde, hoe beter de isolatie.

### 6. Dak en hemelwaterafvoer

- Dakbedekking: materiaal, plaatdikte, geventileerd of niet
- Dakbelasting in kN/m² (sneeuwbelasting op locatie laten toetsen)
- Dakgoot, aantal en diameter hemelwaterafvoeren
- Wie de afvoer aansluit op het riool
- Optioneel: zonnepanelen, dakdoorvoeren, dakveiligheid

> Voorbeeld: gegalvaniseerde trapeziumplaat 0,75 mm, geventileerd, dakbelasting 1,5 kN/m², geïntegreerde bakgoot, 4 PVC-afvoeren Ø 63 mm per module in de hoekkolommen. Aansluiting op riool door klant.

### 7. Binnenwanden en plafond

- Plafond: materiaal, dikte, kleur
- Wandopbouw: regelwerk, dikte, isolatie, binnenafwerking, naadafwerking
- Scheidingswanden: aantal, positie, lengte, hoogte
- Brandwerendheid en geluidwering: apart benoemen. Een brandklasse van het materiaal is niet hetzelfde als een brandwerende wand.
- Tijdelijke transportwanden bij open gekoppelde zijden

> Voorbeeld: plafond en wanden witte melamine spaanplaat 12 mm, houten regelwerk 100 mm (buitenwand) en 60 mm (koppelwand), naden met kunststof profielen, binnenwanden 2.500 mm hoog met 60 mm isolatie. Brandbescherming niet inbegrepen.

### 8. Vloer

Opbouw van boven naar beneden:

| Laag | Voorbeeld |
|---|---|
| Afwerkvloer (merk, type, kleur, dikte) | PVC 1,4 mm, grijs marmer |
| Dragende vloerplaat (materiaal, dikte, brandklasse) | cementgebonden spaanplaat 22 mm, A2-s1,d0 |
| Isolatie | minerale wol 100 mm met PE-dampremming |
| Onderzijde | gegalvaniseerde staalplaat 0,55 mm |

Verder:
- Vloerbelasting in kN/m² (2,5 kN/m² is ongeveer 255 kg/m² gelijkmatig verdeeld)
- Plinten en aansluiting tussen gekoppelde modules (koppelbakken of aluminium strips)
- Optioneel: natte ruimte, zware belasting, extra slijtvast, antislip

### 9. Ramen

Per raamtype of positie:
- Aantal, breedte × hoogte
- Kozijn: materiaal (kleur altijd wit, vaste regel)
- Beglazing en Ug-waarde
- Openingswijze en draairichting
- Veiligheidsglas indien vereist
- Rolluik, screen of andere zonwering
- Positie op de plattegrond (komt uit de configurator)
- Meerprijs per extra raam

> Voorbeeld: 5 kunststof draai-kiepramen 1.800 × 1.200 mm, tweevleugelig, dubbelglas Ug 1,1 W/m²K, 5 witte kunststof buitenrolluiken.

### 10. Deuren

Per deurtype (buiten en binnen):
- Aantal, buitenmaat en **vrije doorgang**
- Materiaal deurblad en kozijn
- Kleur binnen en buiten
- Draairichting en dorpel
- Slot, beslag, aantal sleutels
- Toegankelijkheid (rolstoel), brand-, inbraak- of geluidseisen indien van toepassing
- Opstapje (staat nu al als quote-only optie)

> Voorbeeld buitendeur: gegalvaniseerd staal, 875 × 2.125 mm, vrije doorgang 811 × 2.093 mm, stalen kozijn, binnen RAL 9003, buiten gelijk aan unit, aluminium beslag, cilinderslot met 3 sleutels.
> Voorbeeld binnendeur: zelfde maat, CPL-afwerking wit, kern van papieren honingraat, stalen kozijn.

### 11. Elektra en verlichting

- Norm (Nederlandse norm) en keuring/keuringsrapport
- Hoofdvoeding: spanning, fasen, ampère, aansluitvermogen, stekkertype (bijv. CEE 32A)
- Verdeelkast: groepen, automaten, aardlekbeveiliging
- Overspanningsbeveiliging en vlamboogbeveiliging (AFDD): wel of niet
- Kabeltype (bijv. halogeenvrij) en koppelkabels tussen modules
- Aantallen: standaard stopcontacten, IP44-stopcontacten (spatwaterdicht), speciale stopcontacten, schakelaars, netwerkaansluitingen (UTP)
- Verlichting per type: aantal, vermogen (W), lichtopbrengst (lumen), kleurtemperatuur (K)
- Noodverlichting, aanwezigheidsmelder, buitenverlichting
- Installatietekening met posities (later, zie Prioriteit)

> Voorbeeld (5 modules): 5 verdeelkasten met aardlek, 5 × CEE 32A, 4 koppelkabels, 7 schakelaars, 10 kindveilige stopcontacten, 2 IP44-stopcontacten op 1.100 mm, 2 stopcontacten voor koffieautomaten, 10 LED-armaturen 25 W / 3.150 lm / 4.000 K, 5 vierkante LED-plafondarmaturen 23 W / 2.000 lm / 4.000 K. Halogeenvrije kabels. AFDD en overspanningsbeveiliging niet inbegrepen.

### 12. Verwarming, ventilatie en airco

- **Verwarming** (standaard, vaste regel): aantal kachels, vermogen per kachel in kW, totaal, thermostaat, vorstbeveiliging
- **Ventilatie:** roosters (aantal) of mechanische ventilatie (debiet, regeling)
- **Airco:** inbegrepen, optioneel of niet; type (split/monoblock), koel- en verwarmingsvermogen in kW, merk/type of gelijkwaardig, plek buitenunit, condensafvoer, montage

> Voorbeeld: 5 elektrische wandkachels van 2 kW met thermostaat en vorstbeveiliging (10 kW totaal), 10 ventilatieroosters, geen mechanische ventilatie. Airco optioneel: split-inverter, 2,0 kW koelen / 2,5 kW verwarmen, inclusief aansluiting en montageconsole.

### 13. Sanitair, water en keuken

- Wateraansluiting: via vloer of wand, hoofdafsluiter, drukreduceerventiel, manometer
- Leidingen: materiaal en isolatie toevoer, materiaal afvoer, beluchting
- Toiletten, wastafels, douches: aantal en type
- Kitchenette: afmetingen, spoelbak, kasten, werkblad, kraan, apparatuur
- Warm water: type boiler, inhoud in liters, vermogen
- Aansluitingen voor koffieautomaat of andere apparatuur
- Wie water, riool en nutsvoorzieningen buiten de unit aansluit

> Voorbeeld: aansluiting via vloer of wand, geïsoleerde aluminium-composietleidingen, HT-kunststof afvoer, beluchtingsventiel. Kitchenette 1.800 × 600 × 900 mm, RVS-spoelbak, witte onder- en bovenkasten, wit werkblad, elektrische boiler 5 liter, 2 koudwaterpunten voor koffie. Geen toilet.

### 14. Koppeling en accessoires

Alleen bij meerdere gekoppelde modules:
- Koppelbouten, rubberafdichtingen, afdekplaten op het dak
- Vloerverbinding tussen modules
- Onderlegplaten
- Tijdelijke transportwanden (en wie ze weghaalt)

Bij elke unit, expliciet inbegrepen of niet:
- Trap, bordes, hellingbaan, luifel, valbeveiliging, meubilair

> Voorbeeld: koppelmateriaal, rubberafdichtingen, onderlegplaten, dakafdekplaten en transportwanden inbegrepen. **Montage zelf niet inbegrepen.**

### 15. Fundering en terrein

Klanten weten vaak niet wat er vóór plaatsing nodig is. Dit onderdeel is daarom extra belangrijk.

- Funderingsoplossing: stelconplaten, betonpoeren, funderingsbalken of maatwerk
- Wie zorgt voor bodemonderzoek, funderingsontwerp en aanleg
- Vereiste vlakheid en draagkracht
- Vrije, geventileerde ruimte onder de unit en bescherming tegen dieren (gaas of geperforeerde plaat)
- Regenwaterafvoer en terreinriolering
- Stroom op locatie (spanning, ampère, maximale afstand)
- Water- en rioolaansluiting
- Bereikbaarheid voor vrachtwagen en kraan
- Bouwvergunning: wie regelt het

> Voorbeeld (eisen van Moravia): fundering gereed vóór montage, 150–300 mm vrije ruimte onder de units die niet volledig dicht mag, stroom 400 V / 230 V / 16 A binnen 50 meter, kraan, steigers en vergunning door klant. Moravia schrijft geen specifieke fundering voor, alleen "geschikt en voorbereid".

**Navragen:** welke fundering bij welke ondergrond past, is bouwkundig werk. Laat de offerte dat niet zelf "beslissen", maar laat de admin kiezen en markeer het als *nog te bepalen* als het niet bekend is.

### 16. Transport en plaatsing

Per post een status (inbegrepen / niet inbegrepen / optioneel / op nacalculatie / nog te offreren):
- Transport: adres, aantal ritten, bijzonder transport, begeleidingsvoertuigen
- Brandstoftoeslag (variabel, bij Moravia 2–15%)
- Kraan, hijsplan, lossen
- Montage en koppelen van modules
- Steigers, hoogwerker
- Aansluiten, testen en opleveren
- Meerwerk bij wachttijd, vertraging of een onvoorbereide locatie

> Voorbeeld: transportprijs indicatief en pas vlak voor het laden definitief; kraan, montage en begeleidingsvoertuigen niet inbegrepen.

### 17. Prijs en opties

Per prijsregel: omschrijving, aantal, eenheid, prijs per eenheid, btw, totaal, status.

- Prijs per module
- Technische opties als losse regels (bijv. airco, aanwezigheidsmelder, extra stopcontact, extra raam met rolluik)
- Korting (staffel of project)
- Transport, kraan, montage, fundering en aansluitwerk als **aparte** regels
- Subtotaal, korting, toeslagen, btw, totaal excl. en incl. btw
- Optionele regels apart tonen, buiten het totaal
- Per offerte aangeven: prijzen vast, indicatief of op nacalculatie

**Bij huur extra:** huurprijs per week of maand, minimale huurtermijn, start- en einddatum, borg, eenmalige plaatsingskosten, demontage, retourtransport, schade, verlenging en opleverstaat.

Bedragen nooit dubbel opslaan: totalen worden berekend uit de regels.

### 18. Voorwaarden en bijlagen

- Geldigheid van de offerte
- Levertijd (productie, levering, montage), of "nog vast te stellen"
- Deadline voor akkoord en voor wijzigingen (wijzigingen daarna kunnen meerkosten en vertraging geven)
- Garantie en uitsluitingen
- Betaling: aanbetaling, termijnen, betaaltermijn
- Eigendom en risico-overgang
- Algemene voorwaarden
- Vergunning en regelgeving: wie toetst aan het Bbl (Besluit bouwwerken leefomgeving), brandveiligheid en energie-eisen
- Statische en thermische berekeningen: inbegrepen of niet
- Afwijkingen ten opzichte van de vraag van de klant expliciet benoemen
- Gebruik en onderhoud: ventileren, vorstbescherming, periodiek onderhoud
- Akkoordveld: naam, datum, akkoord

Bijlagen (elk met naam, soort, revisie en wel/niet zichtbaar voor de klant):
- Plattegrond met maten, ramen, deuren en indeling (bestaat al)
- 3D-impressies (bestaan al)
- Installatietekening: verlichting, stopcontacten, schakelaars, verwarming, waterpunten
- Productbladen, certificaten, berekeningen
- Funderings- of opstelpuntenplan

> Voorbeeld: geldig 1 maand, garantie 2 jaar, levertijd nog vast te stellen, technische documentatie elektronisch, statische berekening en isolatiebewijs niet inbegrepen.

---

## Wat Moravia mist en wij wel willen benoemen

Voor een Nederlandse klant maken we deze punten explicieter dan de leverancier:

- **Vergunning en regelgeving:** wie toetst aan Bbl, brandveiligheid en energie-eisen
- **Fundering:** een concrete oplossing, plus wie verantwoordelijk is voor bodemonderzoek en berekening
- **Installatietekening:** exacte posities van alle elektra- en waterpunten
- **Aansluitkosten:** wat zit in de unit en wat is werk buiten de unit
- **Transport en kraan:** apart geprijsd, inclusief wachttijd en bereikbaarheid
- **Montage en oplevering:** wat wij doen en wat de klant zelf regelt
- **Onderhoud en gebruik:** ventileren, verwarmen, vorst, periodiek onderhoud
- **Huurvoorwaarden:** termijn, borg, schade, demontage, retourtransport en opleverstaat

## Opbouw van de klant-PDF

Een goede offerte bestaat uit drie delen: **wat krijg je** (techniek), **wat kost het** (commercieel) en **wat moet er op locatie gebeuren** (uitvoering).

1. Voorblad: logo, klant, project, nummer, datum, impressie
2. Samenvatting: toepassing, aantal, maten, belangrijkste opties, totaalprijs
3. Technische specificaties (onderdeel 2 t/m 13); lege of niet-relevante onderdelen weglaten
4. Koppeling, fundering, transport en plaatsing, met wie wat doet (14 t/m 16)
5. Prijsopbouw: inbegrepen, optioneel en niet inbegrepen gescheiden (17)
6. Voorwaarden en akkoord (18)
7. Bijlagen: plattegrond, impressies, tekeningen

## Wie doet wat (verantwoordelijkheden)

Een tabel in de PDF die misverstanden voorkomt. Per taak: klant, ModulaireHuisvesting.nl of leverancier, en inbegrepen ja/nee.

| Taak | Typisch bij Moravia |
|---|---|
| Fundering | Klant |
| Bouwvergunning | Klant |
| Stroom, water en riool op locatie | Klant |
| Aansluiting hemelwaterafvoer | Klant |
| Transport | Leverancier (meerprijs) |
| Kraan | Klant |
| Steigers / hoogwerker | Klant |
| Montage en koppelen | Niet inbegrepen |

Of dit bij ModulaireHuisvesting.nl anders moet (bijvoorbeeld kraan en plaatsing als dienst aanbieden), is een keuze voor Wouter.

---

## Prioriteit: wat eerst

De offertetool bestaat al (titel, klant, begeleidende tekst, `$quoteExtras`, PDF met specificatielijst, plattegrond en 3D, mail naar klant, nl/en). Bouw hierop voort in kleine stappen:

**Stap 1 – Standaardspecificatie per unittype.** Eén vaste lijst met de technische specificaties (onderdeel 3 t/m 13) per unittype/leverancier, die automatisch in de PDF komt. Admin kan per offerte waarden aanpassen. Dit levert direct de meeste professionaliteit op.

**Stap 2 – Prijsregels met status.** Losse regels met aantal, prijs, btw en status (inbegrepen / optioneel / niet inbegrepen), met optionele regels buiten het totaal. Transport, kraan en montage als aparte regels.

**Stap 3 – Wie doet wat en voorwaarden.** De verantwoordelijkhedentabel, geldigheid, levertijd, garantie, betaling. Standaardtekst die per offerte aan te passen is.

**Stap 4 – Huur.** Huurvelden en huurvoorwaarden (looptijd, borg, retour). Belangrijk voor het doel van huurinkomsten, maar pas zinvol zodra er huurvragen binnenkomen.

**Status 18-11-2026:** stap 1 t/m 4 zijn gebouwd in een eenvoudige vorm. Per onderdeel een tekstvak (één regel per specificatie, zie `QuoteSpecification::DETAILS`), prijsregels met status (`QuotePrice`), koop/huur met huurperiode in weken, projectgegevens, voorwaarden, "nog te bevestigen", interne notities en "Kopieer" om een eerdere offerte als basis te gebruiken. De exacte buitenmaat staat in de offertetool en de PDF (`UnitConfiguration::exactLabel()` en `HEIGHTS`).

**Later, als er vraag naar is:** statussen van de offerte (concept, verzonden, geaccepteerd), revisies met snapshot, installatietekening, akkoord online, meerdere gekoppelde gebouwen, bijlagenbeheer.

## Acceptatiecriteria

- Een admin kan zonder codewijziging een volledige offerte samenstellen en aanpassen.
- Alle 18 onderdelen zijn beschikbaar; alleen ingevulde en relevante onderdelen staan in de PDF.
- Elke specificatie kan een waarde, eenheid en status hebben.
- Optionele regels tellen niet mee in het totaal; niets wordt dubbel geteld.
- Btw en totalen kloppen (met tests).
- Inbegrepen, optioneel en niet inbegrepen zijn in de PDF in één oogopslag te onderscheiden.
- Koop en huur hebben elk hun eigen velden en voorwaarden.
- Voor verzenden wordt gecontroleerd: klant, unit(s), maten, prijs, btw, geldigheid en voorwaarden.
- Een verzonden offerte blijft hetzelfde, ook als de standaardspecificatie later verandert.
- De klant-PDF bevat geen inkoopprijzen, marges, leverancierscontacten of interne notities.
- Er staan geen leveranciersspecifieke voorbeeldwaarden als default in code of seeders.
