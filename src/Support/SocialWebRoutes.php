<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Support;

use Illuminate\Support\Facades\Route;

final class SocialWebRoutes
{
    /** @return array{guard: string, prefix: string, name: string, primary?: bool} */
    public static function currentMount(): array
    {
        $preset = '\\Simtabi\\Laranail\\AuthKit\\Preset\\Support\\AuthPreset';
        $route = request()->route();
        $currentName = $route?->getName();
        $currentUri = is_object($route) && method_exists($route, 'uri') ? $route->uri() : '';

        if (self::presetAvailable($preset)) {
            $bestMount = null;
            $bestScore = -1;

            foreach ($preset::mounts() as $mount) {
                $prefix = trim($mount['prefix'], '/');
                $nameMatches = is_string($currentName) && str_starts_with($currentName, $mount['name']);
                $uriMatches = $prefix === '' || $currentUri === $prefix || str_starts_with($currentUri, $prefix . '/');

                if ($nameMatches && $uriMatches) {
                    $score = strlen($mount['name']) + strlen($prefix);

                    if ($score > $bestScore) {
                        $bestMount = $mount;
                        $bestScore = $score;
                    }
                }
            }

            if ($bestMount !== null) {
                return $bestMount;
            }
        }

        return [
            'guard'   => config(key: 'laranail.authkit-social-login.web.guard', default: 'web'),
            'prefix'  => config(key: 'laranail.authkit-social-login.web.prefix', default: 'auth'),
            'name'    => config(key: 'laranail.authkit-social-login.web.route_name_prefix', default: 'laranail-social.'),
            'primary' => true,
        ];
    }

    public static function register(): void
    {
        if (! config(key: 'laranail.authkit-social-login.enabled', default: true)
            || ! config(key: 'laranail.authkit-social-login.web.enabled', default: true)) {
            return;
        }

        $preset = '\\Simtabi\\Laranail\\AuthKit\\Preset\\Support\\AuthPreset';
        $hasPreset = self::presetAvailable($preset);
        $mounts = $hasPreset
            ? $preset::mounts()
            : [[
                'guard'   => config(key: 'laranail.authkit-social-login.web.guard', default: 'web'),
                'prefix'  => config(key: 'laranail.authkit-social-login.web.prefix', default: 'auth'),
                'name'    => config(key: 'laranail.authkit-social-login.web.route_name_prefix', default: 'laranail-social.'),
                'primary' => true,
            ]];
        $middleware = $hasPreset
            ? $preset::webMiddleware()
            : config(key: 'laranail.authkit-social-login.web.middleware', default: ['web']);

        foreach ($mounts as $mount) {
            self::registerMount($mount, $middleware, $preset);
        }
    }

    public static function afterLoginRedirect(): string
    {
        $preset = '\\Simtabi\\Laranail\\AuthKit\\Preset\\Support\\AuthPreset';

        if (self::presetAvailable($preset)) {
            return $preset::afterLoginRedirect();
        }

        return (string) config(key: 'laranail.authkit-social-login.web.after_login', default: '/dashboard');
    }

    /** @param array{guard: string, prefix: string, name: string, primary?: bool} $mount
     * @param array<int, string> $middleware
     */
    private static function registerMount(array $mount, array $middleware, string $preset): void
    {
        $guard = $mount['guard'];
        $prefix = trim($mount['prefix'], '/');
        $name = $mount['name'];
        $csrfMiddleware = self::presetAvailable($preset)
            ? $preset::csrfMiddleware()
            : self::csrfMiddleware();

        Route::name($name)->group(function () use ($guard, $prefix, $middleware, $csrfMiddleware): void {
            Route::prefix($prefix)->middleware([...$middleware, 'guest:' . $guard])->group(function () use ($csrfMiddleware): void {
                Route::get('/social/{provider}', \Simtabi\Laranail\AuthKit\Social\Http\Controllers\WebSocialRedirectController::class)
                    ->name('social.redirect');

                Route::match(['GET', 'POST'], '/social/{provider}/callback', \Simtabi\Laranail\AuthKit\Social\Http\Controllers\WebSocialCallbackController::class)
                    ->withoutMiddleware($csrfMiddleware)
                    ->name('social.callback');
            });

            Route::prefix($prefix)->middleware([...$middleware, 'auth:' . $guard])->group(function (): void {
                Route::get('/user/social-accounts', [\Simtabi\Laranail\AuthKit\Social\Http\Controllers\SocialAccountsController::class, 'index'])
                    ->name('user-social-accounts.index');

                Route::delete('/user/social-accounts/{provider}', [\Simtabi\Laranail\AuthKit\Social\Http\Controllers\SocialAccountsController::class, 'destroy'])
                    ->name('user-social-accounts.destroy');
            });
        });
    }

    private static function csrfMiddleware(): string
    {
        foreach ([
            'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
            'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
            'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
        ] as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery';
    }

    private static function presetAvailable(string $preset): bool
    {
        return class_exists($preset) && function_exists('config') && config()->has('laranail.authkit-preset');
    }
}
