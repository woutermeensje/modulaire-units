<?php

use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Unit;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;

/**
 * SEO audit: fills in the missing meta descriptions, gives pages with a meaningless or duplicate title their own,
 * and points internal links to old WordPress URLs (404) at the current pages; the old URLs also redirect, for
 * external links. A value is only changed while it still holds the old value, so edits in the CMS are kept.
 */
return new class extends Migration
{
    /**
     * Model => [path or slug => [column => [old value (null: empty), new value]]].
     *
     * @var array<class-string<Model>, array<string, array<string, array{0: ?string, 1: string}>>>
     */
    private const META = [
        Page::class => [
            'algemene-voorwaarden' => [
                'meta_description' => [null, 'Lees de algemene voorwaarden van ModulaireHuisvesting.nl voor de zakelijke verkoop, levering en plaatsing van modulaire units.'],
            ],
            'algemene-voorwaarden/huur' => [
                'meta_title' => ['Huur - ModulaireHuisvesting.nl', 'Algemene huurvoorwaarden - ModulaireHuisvesting.nl'],
                'meta_description' => [null, 'De algemene huurvoorwaarden van ModulaireHuisvesting.nl voor het zakelijk huren van modulaire units, inclusief levering, plaatsing en ophalen.'],
            ],
            'modulaire-units' => [
                'meta_description' => [null, 'Bekijk alle modulaire units van ModulaireHuisvesting.nl: kantoorunits, woonunits, bouwunits en meer. Koop of huur, of stel je eigen unit samen.'],
            ],
            'werkwijze' => [
                'meta_description' => [null, 'Van eerste vraag tot plaatsing: zo werkt ModulaireHuisvesting.nl. Bekijk in een paar stappen hoe je een modulaire unit huurt of koopt.'],
            ],
            'offerte-aanvragen' => [
                'meta_description' => [null, 'Vraag vrijblijvend een offerte aan voor het kopen of huren van modulaire units en krijg snel persoonlijk advies van ModulaireHuisvesting.nl.'],
            ],
            'contact' => [
                'meta_description' => [null, 'Neem contact op met ModulaireHuisvesting.nl: bel 085 239 20 40 of mail informatie@modulairehuisvesting.nl voor advies over modulaire units.'],
            ],
            'huren/noodgebouw' => [
                'meta_title' => ['Noodgebouw - ModulaireHuisvesting.nl', 'Noodgebouw huren - ModulaireHuisvesting.nl'],
                'meta_description' => [null, 'Een noodgebouw huren bij een calamiteit, verbouwing of tijdelijke groei? Modulaire noodgebouwen staan snel klaar als volwaardige huisvesting.'],
            ],
            'huren/flex-units' => [
                'meta_title' => ['Flex units', 'Flex units huren - ModulaireHuisvesting.nl'],
                'meta_description' => [
                    'Met de schakelbare Flex-unit creëert u eenvoudig schaft-, kantoor-, sanitair- of vergaderruimte tot drie verdiepingen hoog.',
                    'Flex units huren? Met schakelbare flex-units heb je snel tijdelijke kantoor-, schaft-, sanitair- of vergaderruimte, tot drie verdiepingen hoog.',
                ],
            ],
            'kopen/flex-units' => [
                'meta_title' => ['Flex units | ModulaireHuisvesting.nl | Modulaire units', 'Flex units kopen - ModulaireHuisvesting.nl'],
                'meta_description' => [
                    'Met de schakelbare Flex-unit creëert u eenvoudig schaft-, kantoor-, sanitair- of vergaderruimte tot drie verdiepingen hoog.',
                    'Flex units kopen? Schakelbare flex-units vormen samen kantoor-, schaft-, sanitair- of vergaderruimte tot drie verdiepingen hoog. Vraag een offerte aan.',
                ],
            ],
        ],
        Post::class => [
            'tijdelijke-huisvesting-tijdens-een-verbouwing-nodig' => [
                'meta_description' => [null, 'Tijdelijke huisvesting nodig tijdens een verbouwing? Lees wanneer het nodig is, welke modulaire oplossingen er zijn en waar je op let.'],
            ],
            'waar-kun-je-tweedehands-kantoorunits-kopen' => [
                'meta_description' => [null, 'Waar koop je tweedehands kantoorunits en waar let je op? Tips over kwaliteit, prijs en levering, en waarom steeds meer bedrijven kiezen voor gebruikt.'],
            ],
        ],
        Unit::class => [
            'modulaire-woonunits-20ft' => [
                'meta_description' => [null, 'Modulaire woonunit van 20ft, volledig op maat geproduceerd in Europa en binnen circa 5 tot 8 weken geleverd. Vraag de prijs vrijblijvend aan.'],
            ],
        ],
        Vacancy::class => [
            'timmerman-zzp-mogelijk' => [
                'meta_description' => [null, 'Vacature timmerman (zzp mogelijk) in Rotterdam: pas in onze loods modulaire units aan, van ramen en deuren tot vloeren en plafonds.'],
            ],
            'monteur-zzp-mogelijk' => [
                'meta_description' => [null, 'Vacature monteur (zzp mogelijk): plaats met ons montageteam modulaire units door heel Nederland. Per direct inzetbaar? Solliciteer nu.'],
            ],
        ],
    ];

    /**
     * Old WordPress URL that is linked from the content => current page.
     *
     * @var array<string, string>
     */
    private const LINKS = [
        '/containers-huren/' => '/huren/containers/',
        '/koop/' => '/kopen/',
        '/huur/' => '/huren/',
        '/units/modulaire-units-schoolgebouw-400-m2/' => '/kopen/klaslokalen/',
        '/voorwaarden/' => '/algemene-voorwaarden/',
    ];

    public function up(): void
    {
        $this->applyMeta(fn (array $change): array => $change);

        foreach ([Page::class, Post::class] as $model) {
            $model::query()->where(function ($query): void {
                foreach (array_keys(self::LINKS) as $oldUrl) {
                    $query->orWhere('content', 'like', '%href="'.$oldUrl.'"%');
                }
            })->each(function (Model $item): void {
                $item->update(['content' => str_replace(
                    array_map(fn (string $url): string => 'href="'.$url.'"', array_keys(self::LINKS)),
                    array_map(fn (string $url): string => 'href="'.$url.'"', self::LINKS),
                    (string) $item->content,
                )]);
            });
        }

        foreach (self::LINKS as $oldUrl => $newUrl) {
            if (Page::query()->where('path', trim($newUrl, '/'))->exists() && ! Page::query()->where('path', trim($oldUrl, '/'))->exists()) {
                Redirect::query()->firstOrCreate(['from_path' => Redirect::normalize($oldUrl)], ['to_path' => $newUrl, 'status_code' => 301]);
            }
        }
    }

    /**
     * Restores the old meta data and removes the redirects; the repaired links stay, they pointed to 404 pages.
     */
    public function down(): void
    {
        $this->applyMeta(fn (array $change): array => [$change[1], $change[0]]);

        foreach (self::LINKS as $oldUrl => $newUrl) {
            Redirect::query()->where('from_path', Redirect::normalize($oldUrl))->where('to_path', $newUrl)->delete();
        }
    }

    /**
     * @param  Closure(array{0: ?string, 1: string}): array{0: ?string, 1: ?string}  $direction  turns a change into [from, to]
     */
    private function applyMeta(Closure $direction): void
    {
        foreach (self::META as $model => $items) {
            $keyColumn = $model === Page::class ? 'path' : 'slug';

            foreach ($items as $key => $columns) {
                $item = $model::query()->where($keyColumn, $key)->first();

                if ($item === null) {
                    continue;
                }

                foreach ($columns as $column => $change) {
                    [$from, $to] = $direction($change);

                    if ((string) $item->{$column} === (string) $from) {
                        $item->{$column} = $to;
                    }
                }

                $item->save();
            }
        }
    }
};
