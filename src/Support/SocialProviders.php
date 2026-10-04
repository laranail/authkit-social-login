<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Support;

use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface;

final class SocialProviders
{
    /** @return array<int, array{slug: string, label: string, icon: string, class: string, order: int}> */
    public static function buttons(): array
    {
        if (! SocialConfig::get('enabled', true)
            || ! SocialConfig::get('web.enabled', true)) {
            return [];
        }

        $configured = SocialConfig::get('providers', []);

        if (! is_array($configured)) {
            return [];
        }

        $registry = app(abstract: IdentityProviderRegistryInterface::class);
        $descriptors = [];

        foreach ($configured as $slug) {
            if (! is_string($slug)) {
                continue;
            }

            $provider = SocialProvider::tryFrom($slug) ?? $registry->get($slug);

            if ($provider === null || ! SocialConfig::get("{$slug}.client_id")) {
                continue;
            }

            $ui = SocialConfig::get("ui.{$slug}", []);
            $ui = is_array($ui) ? $ui : [];

            $descriptors[] = [
                'slug'  => $slug,
                'label' => (string) ($ui['label'] ?? $provider->label()),
                'icon'  => (string) ($ui['icon'] ?? 'laranail/authkit-social-login::icons.' . $slug),
                'class' => (string) ($ui['class'] ?? ''),
                'order' => (int) ($ui['order'] ?? 0),
            ];
        }

        usort($descriptors, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $descriptors;
    }

    public static function redirectRouteName(): string
    {
        return SocialWebRoutes::currentMount()['name'] . 'social.redirect';
    }
}
