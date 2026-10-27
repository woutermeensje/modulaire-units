<?php

namespace App\Admin;

use App\Admin\Resources\ClientResource;
use App\Admin\Resources\MenuItemResource;
use App\Admin\Resources\PageResource;
use App\Admin\Resources\PostResource;
use App\Admin\Resources\ProductResource;
use App\Admin\Resources\RedirectResource;
use App\Admin\Resources\TeamMemberResource;
use App\Admin\Resources\UnitResource;
use App\Admin\Resources\UnitTermResource;
use App\Admin\Resources\UsedListingResource;
use App\Admin\Resources\UserResource;
use App\Admin\Resources\VacancyResource;

class Registry
{
    /** @var list<class-string<resource>> */
    public const RESOURCES = [
        PageResource::class,
        PostResource::class,
        UnitResource::class,
        UnitTermResource::class,
        UsedListingResource::class,
        ProductResource::class,
        VacancyResource::class,
        TeamMemberResource::class,
        MenuItemResource::class,
        RedirectResource::class,
        ClientResource::class,
        UserResource::class,
    ];

    public static function find(string $key): Resource
    {
        foreach (self::RESOURCES as $class) {
            $resource = app($class);
            if ($resource->key === $key) {
                return $resource;
            }
        }

        abort(404);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (string $class): string => app($class)->key, self::RESOURCES);
    }
}
