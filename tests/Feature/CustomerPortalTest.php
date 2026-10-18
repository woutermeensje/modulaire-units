<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_lands_on_the_dashboard(): void
    {
        $this->get('/registreren')->assertOk()->assertSee('Account aanmaken');

        $this->post(route('register.store'), [
            'first_name' => 'Anna',
            'last_name' => 'de Boer',
            'company_name' => 'Bouwbedrijf De Boer',
            'email' => 'anna@example.com',
            'password' => 'geheim1234',
            'password_confirmation' => 'geheim1234',
            'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'anna@example.com')->sole();
        $this->assertFalse($user->isAdmin());
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertOk()->assertSee('Welkom, Anna!')->assertSee('sales-card', false);
    }

    public function test_customer_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'klant@example.com']);

        $this->post(route('login.store'), ['email' => 'klant@example.com', 'password' => 'fout'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'klant@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_customer_can_update_account_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Nieuwe Naam',
            'email' => $user->email,
            'phone' => '0612345678',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nieuwe Naam', $user->fresh()->name);
    }

    public function test_customers_cannot_open_the_cms(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    }
}
