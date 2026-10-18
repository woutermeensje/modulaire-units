# ModulaireHuisvesting.nl — Laravel

Vervanger van de WordPress-site modulairehuisvesting.nl. Alle URL's, pagina's, blogs, units,
producten, vacatures, teamleden, menu's, SEO-gegevens en afbeeldingen zijn overgenomen.

- **Website**: Blade-templates met de originele theme-styling (`resources/css/site`), font Work Sans.
- **Klantomgeving** (`/inloggen/`, `/registreren/`, `/account/`): styling 1-op-1 van de inlogomgeving (`resources/css/portal`).
- **Beheer/CMS** (`/admin/`): pagina's, blogs, units, kenmerken, producten, vacatures, team, media, inzendingen, menu's, redirects, gebruikers en instellingen. Alleen voor gebruikers met `is_admin`.

## Installatie

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan wp:import            # haalt alle content + afbeeldingen op van WP_IMPORT_SOURCE
php artisan site:make-admin jij@example.com --name="Je naam"
```

`wp:import` kan zo vaak als nodig opnieuw draaien (bijwerken op basis van WordPress-ID's).
Gebruik `--fresh` om de lokale cache (`storage/app/wp-import`) te negeren en alles opnieuw op te halen.

## Dynamische blokken in pagina-inhoud

| Shortcode | Wat |
| --- | --- |
| `[units_catalog title="Bouwunits" type="bouw-unit"]` | Units-overzicht met zoek- en typefilter |
| `[units_grid type="kantoorunit" limit="3"]` | Raster met unitkaarten |
| `[subpage_grid parent="kopen" columns="3"]` | Tegels van de subpagina's van een pagina |
| `[team columns="3"]` | Teamleden |
| `[vacatures]` | Openstaande vacatures |
| `[informatie_formulier]` | Informatie-aanvraagformulier |

## Livegang

1. Zet de app op de server (PHP 8.4+), vul `.env` (`APP_ENV=production`, `APP_DEBUG=false`,
   `APP_URL=https://modulairehuisvesting.nl`, database en `MAIL_*`).
2. Draai `php artisan wp:import --fresh` terwijl WordPress nog online is.
3. Maak een beheerder aan met `site:make-admin`.
4. Zet de DNS om. Oude URL's blijven gelijk (met trailing slash); `/wp-content/uploads/...`
   en de WooCommerce-pagina's worden doorgestuurd.
5. Meld `https://modulairehuisvesting.nl/sitemap.xml` aan in Google Search Console.

## Tests

```bash
php artisan test --compact
```
