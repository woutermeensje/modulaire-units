<?php

namespace App\Http\Controllers;

use App\Http\Requests\InformationRequest;
use App\Services\SubmissionRecorder;
use Illuminate\Http\RedirectResponse;

class FormController extends Controller
{
    public function information(InformationRequest $request, SubmissionRecorder $recorder): RedirectResponse
    {
        if (! $recorder->isSpam($request)) {
            $recorder->record('informatie', [
                'name' => $request->string('first_name').' '.$request->string('last_name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'message' => $request->input('message'),
                'data' => ['type_verzoek' => $request->input('request_type')],
                'source_url' => $request->input('source_url'),
            ], $request);
        }

        return redirect()->to(url()->previous().'#informatie-aanvragen')
            ->with('form_success', 'Bedankt voor je bericht! We nemen zo snel mogelijk contact met je op.');
    }
}
