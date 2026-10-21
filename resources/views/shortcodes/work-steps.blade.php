{{-- [werkwijze]: de stappen van eerste contact tot het ophalen van de unit(s). --}}
@php
    $email = \App\Models\Setting::get('email');
    $phone = \App\Models\Setting::get('phone');
    $contact = 'Stuur <a href="mailto:'.e($email).'">een e-mail</a> of bel <a href="tel:'.preg_replace('/[^0-9+]/', '', (string) $phone).'">'.e($phone).'</a>';

    /** @var list<array{title: string, text: string}> $steps */
    $steps = [
        ['title' => 'U zoekt tijdelijke huisvesting', 'text' => $contact.' en vertel ons waar u naar op zoek bent.'],
        ['title' => 'U vraagt een offerte aan', 'text' => 'Geef precies aan waar en naar hoeveel units u op zoek bent. '.$contact.'.'],
        ['title' => 'Wij sturen u een offerte', 'text' => 'U ontvangt diezelfde dag nog een offerte op maat, met alle informatie, specificaties en kosten afgestemd op uw wensen.'],
        ['title' => 'Wij maken de order en stemmen af', 'text' => 'Na uw akkoord maken wij de order definitief en stemmen we de laatste details met u af, zoals de planning.'],
        ['title' => 'Wij plaatsen de unit(s)', 'text' => 'Onze professionals zorgen ervoor dat de modulaire unit(s) veilig en efficiënt op uw locatie worden geplaatst.'],
        ['title' => 'Service tijdens de huur', 'text' => 'Vragen, aanpassingen of wijzigingen tijdens de huurperiode? '.$contact.', dan zoeken wij snel een passende oplossing.'],
        ['title' => 'U beëindigt de huur', 'text' => 'Heeft u de unit(s) niet meer nodig? Laat het ons weten en wij spreken met u af wanneer de unit(s) worden opgehaald.'],
        ['title' => 'Wij halen de unit(s) op', 'text' => 'Op de afgesproken dag zorgen onze professionals voor de demontage en het transport.'],
    ];
@endphp

<section class="mh-steps" aria-labelledby="mh-steps-title">
    <div class="mh-steps__inner">
        <header class="mh-steps__head">
            <h2 id="mh-steps-title" class="mh-steps__title">Onze werkwijze</h2>
            <p class="mh-steps__lead">Van de eerste zoektocht tot en met het ophalen van de unit(s): zo werken wij.</p>
        </header>

        <ol class="mh-steps__list">
            @foreach ($steps as $step)
                <li class="mh-steps__item">
                    <span class="mh-steps__number" aria-hidden="true">{{ $loop->iteration }}</span>
                    <h3 class="mh-steps__item-title">{{ $step['title'] }}</h3>
                    <p class="mh-steps__text">{!! $step['text'] !!}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
