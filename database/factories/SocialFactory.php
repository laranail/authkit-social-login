<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Database\Factories;

use Illuminate\Support\Str;
use Workbench\App\Models\User;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Illuminate\Database\Eloquent\Factories\Factory;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;

/**
 * @extends Factory<\Simtabi\Laranail\AuthKit\Social\Models\Social>
 */
class SocialFactory extends Factory
{
    protected $model = Social::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'socialable_type' => get_class($user),
            'socialable_id'   => $user->id,
            'provider'        => fake()->randomElement(array: SocialProvider::cases())->value,
            'provider_id'     => fake()->numberBetween(int1: 1_000_000_000),
            'name'            => fake()->name(),
            'nickname'        => fake()->name(),
            'email'           => fake()->email(),
            'avatar_url'      => fake()->imageUrl(),
            'token'           => Str::random(length: 240),
            'refresh_token'   => Str::random(length: 240),
            'expires_at'      => now()->addSeconds(3600),
        ];
    }
}
