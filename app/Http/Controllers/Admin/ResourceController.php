<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Registry;
use App\Admin\Resource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Generic CRUD for every resource in App\Admin\Registry.
 */
class ResourceController extends Controller
{
    public function index(Request $request, string $resourceKey): View
    {
        $resource = Registry::find($resourceKey);
        $search = trim((string) $request->query('q', ''));

        $records = $resource->query($resource->model::query())
            ->when($search !== '', function ($query) use ($resource, $search): void {
                $query->where(function ($query) use ($resource, $search): void {
                    foreach ($resource->searchable as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->paginate($resource->perPage)
            ->withQueryString();

        return view('admin.resource.index', compact('resource', 'records', 'search'));
    }

    public function create(string $resourceKey): View
    {
        $resource = Registry::find($resourceKey);
        $record = new $resource->model;

        return view('admin.resource.form', compact('resource', 'record'));
    }

    public function store(Request $request, string $resourceKey): RedirectResponse
    {
        $resource = Registry::find($resourceKey);
        $record = new $resource->model;

        $this->save($resource, $record, $request);

        return redirect()->route("admin.{$resource->key}.edit", $record)->with('status', "{$resource->singular} aangemaakt.");
    }

    public function edit(string $id, string $resourceKey): View
    {
        $resource = Registry::find($resourceKey);
        $record = $resource->model::query()->findOrFail($id);

        return view('admin.resource.form', compact('resource', 'record'));
    }

    public function update(Request $request, string $id, string $resourceKey): RedirectResponse
    {
        $resource = Registry::find($resourceKey);
        $record = $resource->model::query()->findOrFail($id);

        $this->save($resource, $record, $request);

        return redirect()->route("admin.{$resource->key}.edit", $record)->with('status', 'Wijzigingen opgeslagen.');
    }

    public function destroy(Request $request, string $id, string $resourceKey): RedirectResponse
    {
        $resource = Registry::find($resourceKey);
        $record = $resource->model::query()->findOrFail($id);

        abort_if($record->is($request->user()), 422, 'Je kunt je eigen account niet verwijderen.');

        $record->delete();

        return redirect()->route("admin.{$resource->key}.index")->with('status', "{$resource->singular} verwijderd.");
    }

    private function save(Resource $resource, Model $record, Request $request): void
    {
        $validated = $request->validate($resource->rules($record->exists ? $record : null));

        DB::transaction(function () use ($resource, $record, $validated, $request): void {
            $attributes = $resource->fill($record, $validated, $request);
            $record->fill($attributes);

            if ($record instanceof User) {
                $record->forceFill(['is_admin' => (bool) ($attributes['is_admin'] ?? false)]);
            }

            $record->save();
            $resource->afterSave($record, $request);
        });
    }
}
