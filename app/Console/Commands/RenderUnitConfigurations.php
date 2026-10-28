<?php

namespace App\Console\Commands;

use App\Models\Unit;
use App\Services\UnitConfigurationImages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('units:render-configurations')]
#[Description('Genereer de afbeeldingen van alle units met een configuratie opnieuw, bijvoorbeeld na een wijziging in de tekenstijl')]
class RenderUnitConfigurations extends Command
{
    public function handle(UnitConfigurationImages $images): int
    {
        $units = Unit::query()->whereNotNull('configuration')->orWhereNotNull('configuration_images')->get();

        foreach ($units as $unit) {
            $images->sync($unit);
            $this->components->twoColumnDetail($unit->title, 'bijgewerkt');
        }

        $this->components->info("{$units->count()} unit(s) bijgewerkt.");

        return self::SUCCESS;
    }
}
