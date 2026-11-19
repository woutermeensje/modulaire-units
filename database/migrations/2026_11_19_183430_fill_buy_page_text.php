<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * The /kopen/ page only had its heading ("Modulaire Units Kopen") and an old [units_catalog] tag that renders nothing.
 * It gets its own text. The content only changes while it still holds nothing but that heading, so a text written
 * in the CMS stays.
 */
return new class extends Migration
{
    private const PATH = 'kopen';

    private const OLD_TEXT = 'Modulaire Units Kopen';

    private const CONTENT = <<<'HTML'
        <h2>Modulaire Units Kopen</h2>
        <p>ModulaireHuisvesting.nl is gespecialiseerd in de verkoop en verhuur van modulaire units. Waarbij bij ons de nadruk voornamelijk ligt op de zakelijke markt en commercieel gebruik. Waarbij wij ook veel onderwijsinstellingen, ziekenhuizen, zorginstellingen en non-profits voorzien van tijdelijke huisvesting. In het geval van koop worden de units vaak geleverd met specifieke kenmerken en eisen die worden gesteld door onze opdrachtgevers. Dat kan flink uiteenlopen van eenvoudige airco units (overigens beschikken de meeste van onze units over airco's), werkbladen, opstapjes, trappen, verdikte isolatielaag, extra ramen, uitgifteloketten en extra aansluitingspunten voor elektriciteit.</p>
        <h2>Eigen units samenstellen</h2>
        <p>Ben jij nu op zoek naar een eigen unit of meerdere units? Bekijk dan even onze <a href="#unit-samensteller">modulaire unit configurator</a>. Een tool die wij hebben ontwikkeld waarmee jij eenvoudig modulaire units kan samenstellen. Zo kan je kiezen uit de standaard formaten die wij hebben: 3 bij 3 of 6 bij 3. Kan je de units aan elkaar schakelen en stapelen. En aan de binnenkant indelen. Verder kan je zelf keuze maken voor de locatie van deuren, ramen en uitgiftepunten.</p>
        <h2>Ons proces</h2>
        <p>Zoek jij modulaire units voor zakelijk of commercieel gebruik (ben je dus geen particulier)? Dan kan je eenvoudig starten door een <a href="/offerte-aanvragen/">offerte aan te vragen</a>. Waarbij het mogelijk is om jouw eigen unit samen te stellen. Vervolgens krijg je van één van onze collega's een voorstel met units, tarieven en overige eisen die jij stelt aan de unit. Na akkoord worden levering en transport op korte termijn ingepland. Vooraf is het belangrijk om nog even te kijken naar de plek van de unit, de ondergrond en de mogelijkheden voor onze transportkranen om de locatie te bereiken. In principe komen deze zaken voor rekening en verantwoordelijkheid van onze opdrachtgevers. Wel is het mogelijk dat wij jou helemaal ontzorgen in dit proces.</p>
        <h2>Even contact hebben?</h2>
        <p>Heb je liever eerst even contact per telefoon of e-mail? Neem dan gewoon even contact op via <a href="mailto:informatie@modulairehuisvesting.nl">informatie@modulairehuisvesting.nl</a>.</p>
        HTML;

    public function up(): void
    {
        $page = Page::query()->where('path', self::PATH)->first();

        if ($page === null || ! $this->holdsOnlyHeading((string) $page->content)) {
            return;
        }

        $page->content = self::CONTENT;
        $page->save();
    }

    public function down(): void
    {
        //
    }

    /**
     * True while the visible text of the content is just the old heading (shortcode tags left aside).
     */
    private function holdsOnlyHeading(string $content): bool
    {
        $text = (string) preg_replace('/\[[a-z_]+[^\]]*\]/i', '', $content);
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text))));

        return $text === self::OLD_TEXT;
    }
};
