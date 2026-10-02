<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_logout_button_redirects_to_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // The header/sidebar "logout" buttons submit a POST form to /logout.
        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_opening_logout_directly_does_not_show_a_blank_page(): void
    {
        $this->get('/logout')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get('/logout')->assertRedirect(route('dashboard'));
    }
}
