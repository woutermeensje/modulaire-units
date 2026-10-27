<?php

namespace Tests\Feature;

use App\Mail\SubmissionReceived;
use App\Models\Page;
use App\Models\Submission;
use App\Models\UsedListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UsedListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_used_page_lists_published_listings_with_an_offer_button(): void
    {
        Page::factory()->create(['slug' => 'gebruikt', 'title' => 'Gebruikt', 'content' => '<h2>Gebruikte Units</h2><p>Ons netwerk koopt ook in.</p>']);
        $listing = UsedListing::factory()->create(['title' => 'Kantoorunit 6 x 3 m', 'location' => 'Utrecht', 'price' => 12500]);
        UsedListing::factory()->pending()->create(['title' => 'Nog niet gecontroleerd']);

        $this->get('/gebruikt/')
            ->assertOk()
            ->assertSee('href="/units-aanbieden/"', false)
            ->assertSee('Units aanbieden')
            ->assertSee('href="'.$listing->url().'"', false)
            ->assertSee('Kantoorunit 6 x 3 m')
            ->assertSee('Utrecht')
            ->assertSee('€ 12.500')
            ->assertDontSee('Nog niet gecontroleerd')
            ->assertSee('Ons netwerk koopt ook in.');
    }

    public function test_offer_form_is_rendered(): void
    {
        $this->get('/units-aanbieden/')
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="images[]"', false)
            ->assertSee('gratis');
    }

    public function test_visitor_can_offer_a_used_unit_for_review(): void
    {
        Mail::fake();
        Storage::fake('public');

        $this->post(route('used-listings.store'), [
            'title' => 'Sanitairunit',
            'description' => "Goede staat.\nDirect beschikbaar.",
            'location' => 'Zwolle',
            'price' => 4500,
            'images' => [UploadedFile::fake()->image('voorkant.jpg'), UploadedFile::fake()->image('binnen.jpg')],
            'contact_name' => 'Anna Jansen',
            'contact_email' => 'anna@example.com',
        ])->assertRedirect('/units-aanbieden/')->assertSessionHas('form_success');

        $listing = UsedListing::query()->sole();
        $this->assertSame('pending', $listing->status);
        $this->assertSame('sanitairunit', $listing->slug);
        $this->assertCount(2, $listing->gallery());
        Storage::disk('public')->assertExists($listing->coverImage());
        $this->assertSame('gebruikt_aanbod', Submission::query()->sole()->type);
        Mail::assertSent(SubmissionReceived::class);

        $this->get($listing->url())->assertNotFound();
    }

    public function test_offer_form_validates_input_and_limits_images_to_ten(): void
    {
        $this->post(route('used-listings.store'), [
            'price' => 'gratis',
            'images' => array_map(fn (int $i) => UploadedFile::fake()->image("foto-{$i}.jpg"), range(1, 11)),
        ])->assertSessionHasErrors(['title', 'description', 'location', 'price', 'images', 'contact_name', 'contact_email']);

        $this->assertSame(0, UsedListing::query()->count());
    }

    public function test_honeypot_offers_are_ignored(): void
    {
        Storage::fake('public');

        $this->post(route('used-listings.store'), [
            'title' => 'Spam', 'description' => 'Spam', 'location' => 'Spam', 'price' => 1,
            'images' => [UploadedFile::fake()->image('spam.jpg')],
            'contact_name' => 'Bot', 'contact_email' => 'bot@example.com', 'website' => 'http://spam.example',
        ])->assertRedirect();

        $this->assertSame(0, UsedListing::query()->count());
    }

    public function test_listing_detail_shows_unit_and_records_responses(): void
    {
        Mail::fake();
        $listing = UsedListing::factory()->create(['title' => 'Woonunit', 'slug' => 'woonunit', 'description' => 'Ruime woonunit', 'price' => 30000, 'contact_email' => 'verkoper@example.com']);

        $this->get('/gebruikt/aanbod/woonunit/')
            ->assertOk()
            ->assertSee('<h1 class="product_title entry-title">Woonunit</h1>', false)
            ->assertSee('Ruime woonunit')
            ->assertSee('€ 30.000')
            ->assertDontSee('verkoper@example.com');

        $this->post(route('used-listings.respond', 'woonunit'), [
            'name' => 'Koper', 'email' => 'koper@example.com', 'message' => 'Is deze nog beschikbaar?',
        ])->assertRedirect($listing->url().'#reageren');

        $submission = Submission::query()->sole();
        $this->assertSame('gebruikt_reactie', $submission->type);
        $this->assertSame('verkoper@example.com', $submission->data['e-mail_aanbieder']);
    }

    public function test_admin_can_publish_a_pending_listing(): void
    {
        $listing = UsedListing::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.used-listings.update', $listing->id), [
            'title' => $listing->title,
            'slug' => $listing->slug,
            'description' => $listing->description,
            'location' => $listing->location,
            'price' => $listing->price,
            'contact_name' => $listing->contact_name,
            'contact_email' => $listing->contact_email,
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($listing->fresh()->isPublished());
        $this->get($listing->url())->assertOk();
    }
}
