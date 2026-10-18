{{-- Paginering voor de vacaturelijsten; de standaardweergave van Laravel leunt op Tailwind-klassen die hier niet in de CSS zitten. --}}
@if ($paginator->hasPages())
    <nav class="paginering" role="navigation" aria-label="Paginering">
        @if ($paginator->onFirstPage())
            <span class="paginering__stap is-disabled" aria-disabled="true"><i class="ph ph-caret-left" aria-hidden="true"></i> Vorige</span>
        @else
            <a class="paginering__stap" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="ph ph-caret-left" aria-hidden="true"></i> Vorige</a>
        @endif

        <ul class="paginering__paginas">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="paginering__puntjes">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="paginering__pagina is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="paginering__pagina" href="{{ $url }}" aria-label="Pagina {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        @if ($paginator->hasMorePages())
            <a class="paginering__stap" href="{{ $paginator->nextPageUrl() }}" rel="next">Volgende <i class="ph ph-caret-right" aria-hidden="true"></i></a>
        @else
            <span class="paginering__stap is-disabled" aria-disabled="true">Volgende <i class="ph ph-caret-right" aria-hidden="true"></i></span>
        @endif
    </nav>
@endif
