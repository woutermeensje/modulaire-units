<?php

namespace Tests\Feature;

use App\Admin\Registry;
use App\Models\Media;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCmsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_every_resource_index_and_create_screen_renders(): void
    {
        foreach (Registry::keys() as $key) {
            $this->actingAs($this->admin)->get(route("admin.{$key}.index"))->assertOk();
            $this->actingAs($this->admin)->get(route("admin.{$key}.create"))->assertOk();
        }

        foreach (['admin.dashboard', 'admin.submissions.index', 'admin.media.index', 'admin.settings.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
    }

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.pages.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()->assertSee('Inloggen beheer');
    }

    public function test_admin_logs_in_and_out_via_the_admin_login(): void
    {
        $this->post(route('admin.login.store'), ['email' => $this->admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);

        $this->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_customers_cannot_log_in_via_the_admin_login(): void
    {
        $customer = User::factory()->create();

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_receives_a_reset_link_to_the_admin_and_can_set_a_new_password(): void
    {
        Notification::fake();
        $customer = User::factory()->create();

        $this->get(route('admin.password.request'))->assertOk();
        $this->post(route('admin.password.email'), ['email' => $customer->email])->assertSessionHas('status');
        $this->post(route('admin.password.email'), ['email' => $this->admin->email])->assertSessionHas('status');

        Notification::assertNothingSentTo($customer);
        Notification::assertSentTo($this->admin, AdminResetPassword::class, function (AdminResetPassword $notification): bool {
            $url = $notification->toMail($this->admin)->actionUrl;
            $this->assertStringContainsString('/admin/wachtwoord-reset/'.$notification->token, $url);

            $this->get($url)->assertOk();
            $this->post(route('admin.password.update'), [
                'token' => $notification->token,
                'email' => $this->admin->email,
                'password' => 'nieuw-geheim-123',
                'password_confirmation' => 'nieuw-geheim-123',
            ])->assertRedirect(route('admin.login'));

            return true;
        });

        $this->assertTrue(Hash::check('nieuw-geheim-123', $this->admin->fresh()->password));
    }

    public function test_clients_overview_lists_registered_customers_only(): void
    {
        $customer = User::factory()->create(['name' => 'Klant Jansen', 'company_name' => 'Jansen Bouw']);

        $this->actingAs($this->admin)->get('/admin/opdrachtgevers')
            ->assertOk()
            ->assertSee('Klant Jansen')
            ->assertSee('Jansen Bouw')
            ->assertDontSee($this->admin->email);

        $this->actingAs($customer)->get('/admin/opdrachtgevers')->assertForbidden();
    }

    public function test_admin_can_view_download_and_delete_media(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('admin.media.store'), [
            'files' => [UploadedFile::fake()->image('Bouwplaats foto.jpg', 1200, 800)],
        ])->assertSessionHasNoErrors();

        $media = Media::query()->sole();
        Storage::disk('public')->assertExists($media->path);

        $this->actingAs($this->admin)->get(route('admin.media.index'))
            ->assertOk()
            ->assertSee('bouwplaats-foto.jpg')
            ->assertSee(route('admin.media.download', $media), false);

        $this->actingAs($this->admin)->get(route('admin.media.download', $media))
            ->assertOk()
            ->assertDownload('bouwplaats-foto.jpg');

        $this->actingAs($this->admin)->delete(route('admin.media.destroy', $media))->assertRedirect();

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->path);
        Storage::disk('public')->assertMissing($media->sizes['thumbnail']);
    }

    public function test_admin_can_create_a_child_page(): void
    {
        $parent = Page::factory()->create(['slug' => 'huren']);

        $this->actingAs($this->admin)->post(route('admin.pages.store'), [
            'title' => 'Containers huren',
            'slug' => '',
            'parent_id' => $parent->id,
            'template' => 'default',
            'content' => '<p>Nieuwe pagina</p>',
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pages', ['path' => 'huren/containers-huren']);
        $this->get('/huren/containers-huren/')->assertOk()->assertSee('Nieuwe pagina', false);
    }

    public function test_admin_can_create_a_unit_with_terms_and_photo(): void
    {
        Storage::fake('public');
        $term = UnitTerm::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Sanitaire unit 10ft',
            'status' => 'published',
            'terms' => [$term->id],
            'price_buy' => 'Vanaf € 4.950,-',
            'featured_image_upload' => UploadedFile::fake()->image('unit.jpg', 1600, 1000),
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'sanitaire-unit-10ft')->sole();
        $this->assertTrue($unit->terms->contains($term));
        Storage::disk('public')->assertExists($unit->featured_image);
        $this->get($unit->url())->assertOk()->assertSee('Vanaf € 4.950,-');
    }

    public function test_unit_terms_are_one_select_per_group_and_may_be_left_empty(): void
    {
        $type = UnitTerm::factory()->create(['taxonomy' => UnitTerm::TYPE, 'name' => 'Kantoor unit']);
        $offer = UnitTerm::factory()->create(['taxonomy' => UnitTerm::OFFER, 'name' => 'Huur']);

        $this->actingAs($this->admin)->get(route('admin.units.create'))
            ->assertOk()
            ->assertSee('<select class="portal-input" id="terms_'.UnitTerm::TYPE.'" name="terms[]" data-search-select>', false);

        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Kantoor zonder conditie',
            'status' => 'published',
            'terms' => [$type->id, '', $offer->id],
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'kantoor-zonder-conditie')->sole();
        $this->assertEqualsCanonicalizing([$type->id, $offer->id], $unit->terms->pluck('id')->all());

        $this->actingAs($this->admin)->get(route('admin.units.edit', $unit))
            ->assertOk()
            ->assertSee('<option value="'.$type->id.'" selected>Kantoor unit</option>', false);
    }

    public function test_changing_a_parent_slug_updates_child_paths(): void
    {
        $parent = Page::factory()->create(['slug' => 'kopen']);
        $child = Page::factory()->create(['slug' => 'bouwunits', 'parent_id' => $parent->id]);

        $this->actingAs($this->admin)->put(route('admin.pages.update', $parent), [
            'title' => $parent->title,
            'slug' => 'koop',
            'template' => 'default',
            'status' => 'published',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('koop/bouwunits', $child->fresh()->path);
    }

    public function test_settings_can_be_updated(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['phone' => '010 123 45 67'])->assertSessionHasNoErrors();

        $this->assertSame('010 123 45 67', Setting::get('phone'));
    }
}
