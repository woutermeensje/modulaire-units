@php
    $footerColumns = \App\Models\MenuItem::tree('footer');
@endphp
<footer class="mh-footer" role="contentinfo">
    <div class="mh-footer__inner">
        <div class="mh-footer__column">
            <h2 class="mh-footer__heading">OVER ONS</h2>
            <p class="mh-footer__text">{{ \App\Models\Setting::get('footer_about') }}</p>
        </div>

        @foreach ($footerColumns->chunk((int) ceil(max($footerColumns->count(), 1) / 3)) as $columnGroup)
            <div class="mh-footer__column">
                @foreach ($columnGroup as $column)
                    <h2 class="mh-footer__heading">{{ $column->label }}</h2>
                    <ul class="mh-footer__links">
                        @foreach ($column->children as $link)
                            <li>
                                @if ($link->url)
                                    <a href="{{ $link->url }}">{{ $link->label }}</a>
                                @else
                                    <span>{{ $link->label }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="mh-footer__bottom">
        <span>&copy; {{ date('Y') }} {{ \App\Models\Setting::get('site_name') }}</span>
        <a href="{{ url('algemene-voorwaarden') }}/">Algemene voorwaarden</a>
    </div>
</footer>
