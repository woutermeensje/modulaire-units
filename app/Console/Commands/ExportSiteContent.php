<?php

namespace App\Console\Commands;

use App\Services\SiteContentSnapshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('site:export {file=database/site-content.json : Bestand voor de lokale website-inhoud}')]
#[Description('Exporteer de huidige website-inhoud zonder gebruikers, inzendingen of wachtwoorden')]
class ExportSiteContent extends Command
{
    public function handle(SiteContentSnapshot $content): int
    {
        $snapshot = $content->export();
        $path = $this->argument('file');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $this->components->info("Website-inhoud geëxporteerd naar {$path}.");

        foreach ($snapshot['tables'] as $table => $rows) {
            $this->components->twoColumnDetail($table, (string) count($rows));
        }

        return self::SUCCESS;
    }
}
