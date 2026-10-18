<?php

namespace Tests\Feature;

use App\Admin\Registry;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
