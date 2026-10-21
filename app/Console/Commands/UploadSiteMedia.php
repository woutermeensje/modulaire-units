<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('site:upload-media
    {--disk=s3 : Doelschijf voor de publieke afbeeldingen}
    {--source= : Lokale media-map (standaard storage/app/public/media)}
    {--overwrite : Upload ook bestanden die al met dezelfde grootte aanwezig zijn}')]
#[Description('Zet de lokale afbeeldingen over naar object storage, zonder WordPress te benaderen')]
class UploadSiteMedia extends Command
{
    public function handle(): int
    {
        $diskName = $this->option('disk');
        $source = $this->option('source') ?: storage_path('app/public/media');

        if (config("filesystems.disks.{$diskName}.driver") !== 's3') {
            $this->components->error('Kies een S3-schijf met de gegevens van je publieke bucket.');

            return self::FAILURE;
        }

        if (! File::isDirectory($source)) {
            $this->components->error("Lokale media-map ontbreekt: {$source}. Voer dit commando lokaal uit.");

            return self::FAILURE;
        }

        $uploaded = 0;
        $skipped = 0;

        try {
            $disk = Storage::disk($diskName);

            foreach (File::allFiles($source) as $file) {
                $path = 'media/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

                if (! $this->option('overwrite') && $disk->exists($path) && $disk->size($path) === $file->getSize()) {
                    $skipped++;

                    continue;
                }

                $stream = fopen($file->getPathname(), 'rb');

                if ($stream === false) {
                    throw new RuntimeException("Bestand openen mislukt: {$path}");
                }

                try {
                    if (! $disk->put($path, $stream)) {
                        throw new RuntimeException("Upload mislukt: {$path}");
                    }
                } finally {
                    fclose($stream);
                }

                $uploaded++;
                $this->line($path);
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());
            $this->components->warn('Voer hetzelfde commando opnieuw uit om de overdracht te hervatten.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Geüpload', (string) $uploaded);
        $this->components->twoColumnDetail('Al aanwezig', (string) $skipped);

        return self::SUCCESS;
    }
}
