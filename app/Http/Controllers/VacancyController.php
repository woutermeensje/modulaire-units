<?php

namespace App\Http\Controllers;

use App\Http\Requests\VacancyApplicationRequest;
use App\Models\Vacancy;
use App\Services\Content\ShortcodeRenderer;
use App\Services\SubmissionRecorder;
use App\Support\Seo;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VacancyController extends Controller
{
    public function show(string $slug): View
    {
        $vacancy = Vacancy::query()->where('slug', $slug)->firstOrFail();

        abort_unless($vacancy->isPublished() || Auth::check(), 404);

        return view('vacancies.show', [
            'vacancy' => $vacancy,
            'content' => app(ShortcodeRenderer::class)->render($vacancy->content),
            'seo' => Seo::for($vacancy, $vacancy->content),
        ]);
    }

    public function apply(VacancyApplicationRequest $request, string $slug, SubmissionRecorder $recorder): RedirectResponse
    {
        $vacancy = Vacancy::query()->published()->where('slug', $slug)->firstOrFail();

        if (! $recorder->isSpam($request)) {
            $file = $request->file('cv');
            $path = $file->store('sollicitaties', 'local');

            $recorder->record('sollicitatie', [
                'name' => $request->string('first_name').' '.$request->string('last_name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'message' => $request->input('message'),
                'data' => [
                    'vacature' => $vacancy->title,
                    'cv_path' => $path,
                    'cv_name' => $file->getClientOriginalName(),
                ],
                'source_url' => SiteUrl::absolute($vacancy->url()),
            ], $request);
        }

        return redirect()->to($vacancy->url().'#solliciteren')
            ->with('form_success', 'Bedankt voor je sollicitatie! We nemen zo snel mogelijk contact met je op.');
    }
}
