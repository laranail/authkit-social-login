<?php

declare(strict_types=1);

use Simtabi\Laranail\AuthKit\Social\Providers\SocialServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\AssertsPublishedConfigOverrides;

uses(AssertsPublishedConfigOverrides::class);

it('loads an application published override at the namespaced config key', function (): void {
    $original = $this->app->configPath();
    $temporary = sys_get_temp_dir() . '/authkit-social-login-config-' . bin2hex(random_bytes(8));
    $this->app->useConfigPath($temporary);

    try {
        $this->assertPublishedConfigOverride(
            SocialServiceProvider::class,
            'laranail.authkit-social-login',
            ['guard' => 'published-guard'],
            'laranail.authkit-social-login.guard',
            'published-guard',
        );
    } finally {
        $this->app->useConfigPath($original);
        @rmdir($temporary);
    }
});
