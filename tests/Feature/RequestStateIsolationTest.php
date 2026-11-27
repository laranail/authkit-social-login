<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Social\Support\SocialWebRoutes;
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoStateLeak;
use Simtabi\Laranail\AuthKit\Social\Actions\ResolveSocialIdentity;

uses(AssertsNoStateLeak::class);

it('does not reuse a resolved social identity on the next request', function (): void {
    $resolver = app(ResolveSocialIdentity::class);

    $this->assertNoStateLeaksBetweenRequests(function (string $marker) use ($resolver): string {
        $email = $marker . '@example.test';
        User::factory()->create(['email' => $email]);
        $socialUser = (new SocialiteUser)->setRaw(['email_verified' => true])->map([
            'id' => $marker, 'name' => $marker, 'email' => $email,
        ]);
        $socialUser->token = TestCredentials::secret('provider');
        $user = $resolver->execute(SocialProvider::GOOGLE, $socialUser, 'web');

        expect($user)->not->toBeNull();

        return $user->email;
    });
});

it('does not retain a standalone mount between requests', function (): void {
    $this->assertNoStateLeaksBetweenRequests(function (string $marker): string {
        config()->set('laranail.authkit-social-login.web.guard', $marker);

        return SocialWebRoutes::currentMount()['guard'];
    });
});
