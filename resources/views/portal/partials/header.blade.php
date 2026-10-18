{{-- Kop van een portalpagina, gelijk aan de inlogomgeving. --}}
<header class="portal-header card">
    <div class="portal-header__row">
        <h1 class="portal-header__title">{{ $title }}</h1>
        {{ $actions ?? '' }}
    </div>
    <nav class="portal-breadcrumbs" aria-label="Kruimelpad">
        <ol class="portal-breadcrumbs__list">
            @foreach ($crumbs ?? [] as $label => $url)
                <li class="portal-breadcrumbs__item">
                    @if ($url && ! $loop->last)
                        <a class="portal-breadcrumbs__link" href="{{ $url }}">{{ $label }}</a>
                    @else
                        <span class="portal-breadcrumbs__current">{{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
    @if (! empty($subtitle))
        <p class="portal-header__subtitle">{{ $subtitle }}</p>
    @endif
</header>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="ui-list ui-list--compact">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
