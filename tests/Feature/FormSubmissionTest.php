<?php

namespace Tests\Feature;

use App\Mail\SubmissionReceived;
use App\Models\Page;
use App\Models\Submission;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_form_is_rendered_and_stored(): void
    {
        Mail::fake();
        Page::factory()->create(['slug' => 'offerte-aanvragen', 'content' => '[informatie_formulier]']);

        $this->get('/offerte-aanvragen/')->assertOk()->assertSee('Type verzoek');

        $this->from('/offerte-aanvragen/')->post(route('forms.information'), [
            'first_name' => 'Piet',
            'last_name' => 'de Vries',
            'email' => 'piet@example.com',
            'request_type' => 'Huur',
            'message' => 'Ik zoek 3 kantoorunits',
        ])->assertRedirectContains('#informatie-aanvragen');

        $this->assertSame('Huur', Submission::query()->sole()->data['type_verzoek']);
        Mail::assertSent(SubmissionReceived::class);
    }

    public function test_information_form_validates_input(): void
    {
        $this->post(route('forms.information'), ['email' => 'geen-email'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'request_type', 'message']);
    }

    public function test_honeypot_submissions_are_ignored(): void
    {
        $this->post(route('forms.information'), [
            'first_name' => 'Bot', 'last_name' => 'Bot', 'email' => 'bot@example.com',
            'request_type' => 'Koop', 'message' => 'spam', 'website' => 'http://spam.example',
        ])->assertRedirect();

        $this->assertSame(0, Submission::query()->count());
    }

    public function test_vacancy_application_stores_cv(): void
    {
        Mail::fake();
        Storage::fake('local');
        $vacancy = Vacancy::factory()->create(['slug' => 'monteur']);

        $this->post(route('vacancies.apply', 'monteur'), [
            'first_name' => 'Kees', 'last_name' => 'Bakker', 'email' => 'kees@example.com', 'phone' => '0612345678',
            'message' => 'Graag wil ik solliciteren', 'cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
        ])->assertRedirect($vacancy->url().'#solliciteren');

        $submission = Submission::query()->sole();
        $this->assertSame('sollicitatie', $submission->type);
        Storage::disk('local')->assertExists($submission->data['cv_path']);
    }
}
