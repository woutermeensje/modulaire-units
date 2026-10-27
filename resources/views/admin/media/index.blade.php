@extends('layouts.portal')

@section('title', 'Media')

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => 'Media', 'crumbs' => ['Beheer' => route('admin.dashboard'), 'Media' => null], 'subtitle' => 'Alle afbeeldingen en bestanden. Kopieer de URL om een afbeelding in een pagina te gebruiken.'])

        <section class="card dash-section">
            <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="portal-filters">
                @csrf
                <input class="portal-input" type="file" name="files[]" multiple accept="image/*,application/pdf" required>
                <button class="btn btn-primary" type="submit"><i class="ph ph-upload-simple"></i> Uploaden</button>
            </form>
            <form method="GET" class="portal-filters" style="margin-top: 12px;">
                <input class="portal-input" type="search" name="q" value="{{ $search }}" placeholder="Zoek op bestandsnaam of alt-tekst">
                <button class="btn btn-ghost" type="submit">Zoeken</button>
            </form>
        </section>

        <section class="card dash-section">
            <div class="portal-gallery-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
                @forelse ($media as $item)
                    <div class="portal-media-item">
                        @if ($item->isImage())
                            <a href="{{ $item->url() }}" target="_blank" rel="noopener"><img src="{{ $item->url('thumbnail') }}" alt="{{ $item->alt }}" loading="lazy"></a>
                        @else
                            <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="portal-media-empty"><i class="ph ph-file"></i></a>
                        @endif
                        <div class="portal-media-item__name" title="{{ basename($item->path) }}">{{ basename($item->path) }}</div>
                        <div class="portal-media-item__meta">
                            {{ $item->width && $item->height ? $item->width.'×'.$item->height.' px · ' : '' }}{{ $item->size ? \Illuminate\Support\Number::fileSize($item->size, precision: 1) : '' }}
                        </div>
                        <div class="portal-media-item__actions">
                            <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="btn btn-ghost" title="Bekijken"><i class="ph ph-eye"></i> Bekijken</a>
                            <a href="{{ route('admin.media.download', $item) }}" class="btn btn-ghost" title="Downloaden"><i class="ph ph-download-simple"></i> Downloaden</a>
                            <form method="POST" action="{{ route('admin.media.destroy', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-ghost" title="Verwijderen" data-confirm="Bestand definitief verwijderen? Controleer eerst of het nergens meer gebruikt wordt."><i class="ph ph-trash"></i> Verwijderen</button>
                            </form>
                        </div>
                        <input class="portal-input" type="text" value="{{ $item->url() }}" readonly onclick="this.select()" style="font-size: 12px;">
                        <form method="POST" action="{{ route('admin.media.update', $item) }}" class="portal-filters">
                            @csrf
                            @method('PUT')
                            <input class="portal-input" type="text" name="alt" value="{{ $item->alt }}" placeholder="Alt-tekst" style="font-size: 12px;">
                            <button class="btn btn-ghost" type="submit">Opslaan</button>
                        </form>
                    </div>
                @empty
                    <p>Nog geen media.</p>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $media->links() }}</div>
        </section>
    </div>
</section>
@endsection
