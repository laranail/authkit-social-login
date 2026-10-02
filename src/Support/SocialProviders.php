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
        if (! config(key: 'authkit-social-login.enabled', default: true)
            || ! config(key: 'authkit-social-login.web.enabled', default: true)) {
            return [];
        }

        $configured = config(key: 'authkit-social-login.providers', default: []);

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

            if ($provider === null || ! config(key: "authkit-social-login.{$slug}.client_id")) {
                continue;
            }

            $ui = config(key: "authkit-social-login.ui.{$slug}", default: []);
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
