<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;

function apiSocialiteUser(array $raw = ['email_verified' => true]): SocialiteUser
{
    $user = new SocialiteUser;
    $user->setRaw($raw);
    $user->map(['id' => 'api-1', 'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'avatar' => null]);
    $user->token = 'provider-token';

    return $user;
}

it('ships with API social sign-in off', function (): void {
    // The endpoint widens the authentication surface, and an application serving only a browser has
    // no use for it. Asserted against the shipped config rather than the route table, because the
    // test environment turns it on to have something to exercise.
    $shipped = require __DIR__ . '/../../config/authkit-social-login.php';

    expect($shipped['api']['enabled'])->toBeFalse();
});

it('hands the client a URL to open', function (): void {
    Socialite::fake(driver: SocialProvider::GOOGLE->driver());

    $this->getJson('/api/auth/social/google/redirect')
        ->assertOk()
        ->assertJsonStructure(['status', 'data' => ['url']]);
});

it('signs in from an authorization code and returns a token', function (): void {
    Socialite::fake(driver: SocialProvider::GOOGLE->driver(), user: apiSocialiteUser());

    $response = $this->postJson('/api/auth/social/google/callback', ['code' => 'provider-code']);

    $response->assertOk()->assertJsonStructure(['status', 'data' => ['token']]);
    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    $this->assertDatabaseHas('socials', ['provider' => 'google', 'provider_id' => 'api-1']);
});

it('requires a code', function (): void {

    $this->postJson('/api/auth/social/google/callback', [])->assertStatus(422);
});

it('applies the same verification rule as the browser flow', function (): void {
    // The API must not be a second, weaker way in: an unverified address cannot sign in here for
    // exactly the reason it cannot sign in through a browser.
    User::factory()->create(['email' => 'ada@example.com']);
    Socialite::fake(
        driver: SocialProvider::GOOGLE->driver(),
        user: apiSocialiteUser(['email_verified' => false]),
    );

    $this->postJson('/api/auth/social/google/callback', ['code' => 'provider-code'])
        ->assertStatus(422);
});

it('does not say why sign-in failed', function (): void {
    // Distinguishing "unverified" from "belongs to someone else" tells an unauthenticated caller
    // which addresses have accounts.
    Socialite::fake(
        driver: SocialProvider::GOOGLE->driver(),
        user: apiSocialiteUser(['email_verified' => false]),
    );

    $body = $this->postJson('/api/auth/social/google/callback', ['code' => 'c'])->json();

    expect($body['message'] ?? '')->not->toContain('verified')
        ->and($body['message'] ?? '')->not->toContain('ada@example.com');
});

it('404s an unknown provider rather than throwing', function (): void {

    $this->postJson('/api/auth/social/myspace/callback', ['code' => 'c'])->assertNotFound();
});
