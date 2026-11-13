<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Tests\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.turnstile.secret' => 'test-secret']);
    }

    private function fakeTurnstile(bool $success = true): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $success])]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'cf-turnstile-response' => 'token-ok',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $this->fakeTurnstile();

        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_a_valid_turnstile_token(): void
    {
        $this->fakeTurnstile(false);

        $response = $this->post('/register', $this->payload());

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_registration_without_turnstile_token_is_rejected(): void
    {
        $this->fakeTurnstile();

        $response = $this->post('/register', $this->payload(['cf-turnstile-response' => null]));

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_registration_fails_closed_without_turnstile_secret(): void
    {
        $this->fakeTurnstile();

        config(['services.turnstile.secret' => null]);

        $response = $this->post('/register', $this->payload());

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidNames(): array
    {
        return [
            'url' => ['https://www.autodis.lu/go-bvb.php'],
            'domain' => ['autodis.lu'],
            'digits' => ['Juan 3'],
            'symbols' => ['<b>Juan</b>'],
            'formula' => ['=HYPERLINK("x")'],
            'apostrophe' => ["O'Brien"],
            'double space' => ['Juan  Perez'],
            'too long' => [str_repeat('a', 61)],
            'too short' => ['A'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidNames')]
    public function test_registration_rejects_names_that_are_not_letters_and_spaces(string $name): void
    {
        $this->fakeTurnstile();

        $response = $this->post('/register', $this->payload(['name' => $name]));

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
    }

    public function test_registration_accepts_accented_letters_and_spaces(): void
    {
        $this->fakeTurnstile();

        $response = $this->post('/register', $this->payload(['name' => 'José Muñoz Ñúñez']));

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
