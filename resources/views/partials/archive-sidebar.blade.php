@php($arrow = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>')
<aside class="mh-blog__sidebar">
    <div class="mh-sidebar-newsletter">
        <div class="mh-sidebar-newsletter__body">
            <p class="mh-sidebar-newsletter__label">Verkoop uw units</p>
            <h3 class="mh-sidebar-newsletter__title">Modulaire units verkopen aan {{ \App\Models\Setting::get('site_name') }}</h3>
            <a href="/verkopen/" class="mh-sidebar-newsletter__btn">
                Bekijk mogelijkheden
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
            </a>
        </div>
    </div>
    <div class="mh-sidebar-links">
        <a href="/modulaire-units/" class="mh-sidebar-link">
            <span class="mh-sidebar-link__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.35-4.35"></path></svg>
            </span>
            <span class="mh-sidebar-link__text">
                <strong>Ik zoek units</strong>
                <span>Vind snel beschikbare modulaire units voor koop of huur</span>
            </span>
            {!! str_replace('<svg ', '<svg class="mh-sidebar-link__arrow" ', $arrow) !!}
        </a>
        <a href="/verkopen/" class="mh-sidebar-link">
            <span class="mh-sidebar-link__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"></path><path d="M17 8c0-1.933-2.239-3.5-5-3.5S7 6.067 7 8s2.239 3.5 5 3.5 5 1.567 5 3.5-2.239 3.5-5 3.5-5-1.567-5-3.5"></path></svg>
            </span>
            <span class="mh-sidebar-link__text">
                <strong>Units verkopen</strong>
                <span>Laat uw gebruikte modulaire units vrijblijvend door ons beoordelen</span>
            </span>
            {!! str_replace('<svg ', '<svg class="mh-sidebar-link__arrow" ', $arrow) !!}
        </a>
    </div>
</aside>
