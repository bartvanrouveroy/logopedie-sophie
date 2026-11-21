# Logopedie Sophie in WordPress

Dit is de echte WordPress-versie van de site: een eigen thema, de plugin Sophie Oefenportaal en een setupscript dat alle pagina's, menu's, artikels en de webshop klaarzet.

## Demo bekijken

Open de demo in WordPress Playground. Dat is een echte WordPress-site die in je browser draait, zonder hosting:

https://playground.wordpress.net/?blueprint-url=https://bartvanrouveroy.github.io/logopedie-sophie/wp/blueprint.json

- Het opstarten duurt de eerste keer een minuut, omdat WooCommerce geïnstalleerd wordt.
- Je bent meteen ingelogd als sitebeheerder. Het beheer vind je via het WordPress-logo linksboven, of op `/wp-admin`.
- Bij **Oefenportaal › Inloggen** staan voorbeeldaccounts. Klik op een naam om in te loggen als Sophie (beheerder), Lies (logopedist) of een ouder.
- Registreren met een code kan met **LS-2026**.
- De demo verdwijnt als je het tabblad sluit. Niets wordt bewaard.

## Wat zit erin

| Map | Wat |
|---|---|
| `theme/logopedie-sophie` | Blokthema in de stijl van de voorbeeldsite: kleuren, lettertypes (lokaal, geen Google Fonts), kop met menu en winkelmand, voet, sjablonen. |
| `plugin/sophie-oefenportaal` | Oefenportaal met rollen (beheerder, logopedist, patiënt), patiëntdossiers, oefenbibliotheek, uitnodigingscodes, Oefenhoek, logboek, chat en contactformulier die aanvragen mailen. |
| `setup` | `setup.php` zet een lege WordPress-site volledig klaar. |
| `../wp` | De zips en de blueprint die de demo gebruikt (gepubliceerd via GitHub Pages). |

### Rollen

| Rol | Mag |
|---|---|
| Patiënt of ouder | Eigen oefeningen zien en afvinken, Oefenhoek. Komt nooit in wp-admin. |
| Logopedist | Eigen patiënten, oefeningen openzetten, uitnodigingscodes, oefenbibliotheek, aanvragen lezen. |
| Beheerder | Alles van de logopedist, plus alle patiënten, collega's toevoegen, rollen wijzigen, accounts blokkeren, volledig logboek en instellingen. |

Sitebeheerders (de WordPress-rol administrator) krijgen automatisch alle portaalrechten.

## Installeren op Combell

1. Maak bij Combell een testomgeving (staging) of een tijdelijk adres, en installeer daar WordPress.
2. Installeer en activeer de plugin **WooCommerce** (Plugins › Nieuwe plugin).
3. Upload het thema: Weergave › Thema's › Nieuw › Thema uploaden › `logopedie-sophie.zip`. Activeer het.
4. Upload de plugin: Plugins › Nieuwe plugin › Plugin uploaden › `sophie-oefenportaal.zip`. Activeer ze.
5. Zet de inhoud klaar. Combell heeft WP-CLI via SSH:
   ```
   wp eval 'define( "LS_DEMO", false ); require "setup/setup.php";'
   ```
   Met `LS_DEMO` op `false` worden geen voorbeeldpatiënten, -accounts of demoknoppen aangemaakt. Lukt SSH niet, dan doe ik dit samen met je.
6. Extra plugins voor de echte site:
   - **Mollie Payments for WooCommerce**: betalen met Bancontact, Payconiq en kaart.
   - **Lulu Direct for WooCommerce**: drukt de AVI-boekjes en bundels op bestelling.
   - **WP Mail SMTP**: zorgt dat aanvragen uit de chat echt aankomen.
   - **Two Factor**: tweestapsverificatie voor Sophie en collega's.
   - **Complianz**: cookiemelding en privacyverklaring.
   - **UpdraftPlus** of de back-ups van Combell.
7. Zet daarna in WooCommerce › Instellingen de melding "Voorbeeldwebshop" uit, en vul de echte prijzen in.

## Teksten aanpassen

- Pagina's: Pagina's › klik op een pagina. Alles is gewone blokken: tekst, knoppen, kaarten.
- Menu: Weergave › Editor › Navigatie.
- Kleuren en lettertypes: Weergave › Editor › Stijlen.
- Producten: Producten.
- Oefeningen, patiënten, aanvragen: menu **Oefenportaal**.

Teksten tussen vierkante haken, zoals `[TARIEF]` of `[NUMMER]`, moeten nog ingevuld worden.
