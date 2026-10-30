<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteSubmitRequest;
use App\Services\QuoteCart;
use App\Services\SubmissionRecorder;
use App\Support\ComposedUnit;
use App\Support\Seo;
use App\Support\SiteUrl;
use App\Support\UnitConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function __construct(private QuoteCart $cart) {}

    public function show(): View
    {
        $seo = Seo::make('Mijn offerte', null, route('quote.show', [], false));
        $seo->noindex = true;

        return view('quote.show', [
            'items' => $this->cart->items(),
            'seo' => $seo,
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(QuoteCart::TYPES))],
            'id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $class = QuoteCart::TYPES[$validated['type']];
        $model = $class::query()->published()->findOrFail($validated['id']);

        $this->cart->add($validated['type'], $model->getKey(), (int) ($validated['quantity'] ?? 1));

        return redirect()->to(url()->previous($model->url()))->with('quote_added', true);
    }

    /**
     * Add a composition from the unit composer. Answers with the new number of items when asked for JSON.
     */
    public function addComposition(Request $request): RedirectResponse|JsonResponse
    {
        $rules = UnitConfiguration::rules('configuration');
        $rules['configuration.size'] = ['required', Rule::in(array_keys(UnitConfiguration::SIZES))];
        $validated = $request->validate($rules);

        $this->cart->addComposition($validated['configuration']);

        if ($request->expectsJson()) {
            return response()->json(['count' => $this->cart->count(), 'quote_url' => route('quote.show').'/']);
        }

        return redirect()->route('quote.show');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantities' => ['array'],
            'quantities.*' => ['integer', 'min:0', 'max:999'],
            'remove' => ['nullable', 'string'],
        ]);

        foreach ($validated['quantities'] ?? [] as $key => $quantity) {
            [$type, $id] = explode(':', (string) $key) + [null, null];
            $this->cart->update((string) $type, (string) $id, (int) $quantity);
        }

        if (! empty($validated['remove'])) {
            [$type, $id] = explode(':', $validated['remove']) + [null, null];
            $this->cart->remove((string) $type, (string) $id);
        }

        return redirect()->route('quote.show');
    }

    public function submit(QuoteSubmitRequest $request, SubmissionRecorder $recorder): RedirectResponse
    {
        $items = $this->cart->items();

        if ($items->isEmpty()) {
            return redirect()->route('quote.show');
        }

        if (! $recorder->isSpam($request)) {
            $recorder->record('offerte', [
                'name' => $request->string('first_name').' '.$request->string('last_name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'message' => $request->input('message'),
                'items' => $items->map(fn (array $item): array => $item['model'] instanceof ComposedUnit
                    ? [
                        'type' => $item['type'],
                        'id' => $item['model']->key(),
                        'title' => $item['model']->title().' ('.$item['model']->summary().')',
                        'url' => SiteUrl::absolute($item['model']->url()),
                        'quantity' => $item['quantity'],
                    ]
                    : [
                        'type' => $item['type'],
                        'id' => $item['model']->getKey(),
                        'title' => $item['model']->title,
                        'url' => SiteUrl::absolute($item['model']->url()),
                        'quantity' => $item['quantity'],
                    ])->all(),
                'source_url' => SiteUrl::absolute(route('quote.show', [], false).'/'),
            ], $request);
        }

        $this->cart->clear();

        return redirect()->route('quote.show')
            ->with('quote_success', 'We hebben je offerteaanvraag ontvangen en nemen zo snel mogelijk contact met je op.');
    }
}
