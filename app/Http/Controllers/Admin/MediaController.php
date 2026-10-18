<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.media.index', [
            'media' => Media::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('path', 'like', "%{$search}%")->orWhere('alt', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")))
                ->latest()
                ->paginate(48)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function store(Request $request, MediaUploader $uploader): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,svg,pdf'],
        ]);

        foreach ($request->file('files') as $file) {
            $uploader->store($file);
        }

        return back()->with('status', count($request->file('files')).' bestand(en) geüpload.');
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $media->update($request->validate(['alt' => ['nullable', 'string', 'max:255']]));

        return back()->with('status', 'Alt-tekst opgeslagen.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $media->delete();

        return back()->with('status', 'Bestand verwijderd.');
    }
}
