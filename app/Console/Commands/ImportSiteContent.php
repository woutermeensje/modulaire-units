<?php

namespace App\Console\Commands;

use App\Services\SiteContentSnapshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('site:import
    {file=database/site-content.json : Bestand met geëxporteerde website-inhoud}
    {--if-empty : Sla de import over als er al website-inhoud bestaat}')]
#[Description('Laad de lokale website-inhoud in een lege database, zonder verbinding met WordPress')]
class ImportSiteContent extends Command
{
    public function handle(SiteContentSnapshot $content): int
    {
        if ($this->option('if-empty') && $content->hasContent()) {
            $this->components->info('Website-inhoud is al aanwezig; import overgeslagen.');

            return self::SUCCESS;
        }

        $path = $this->argument('file');

        if (! File::isFile($path)) {
            $this->components->error("Inhoudsexport ontbreekt: {$path}. Maak deze lokaal met site:export en deploy het bestand mee.");

            return self::FAILURE;
        }

        try {
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $counts = $content->import($snapshot);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $table => $count) {
            $this->components->twoColumnDetail($table, (string) $count);
        }

        $this->components->info('Website-inhoud geïmporteerd. Afbeeldingsbestanden moeten apart naar de publieke opslag worden overgezet.');

        return self::SUCCESS;
    }
}
