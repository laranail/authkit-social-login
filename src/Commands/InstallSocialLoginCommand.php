<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConfig;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

class InstallSocialLoginCommand extends Command
{
    // Symfony's command-name validator rejects the empty segment in `laranail::`, so the family's
    // namespaced naming shape is unreachable without this trait. The preset's installer needs the
    // same escape hatch; see SupportsNamespacedNames for why dispatch still resolves it.
    use SupportsNamespacedNames;

    protected $signature = 'laranail::authkit-social-login.install
        {--social=* : Social providers to enable (google, apple, x, linkedin, paypal)}
        {--publish-routes : Publish the social web routes for application ownership}
        {--force : Overwrite the published config and routes}';

    protected $description = 'Install and configure laranail/authkit-social-login';

    public function handle(): int
    {
        $providers = $this->providers();

        $this->call('vendor:publish', [
            '--tag'   => 'laranail::authkit-social-login-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->writeProviders($providers);
        $this->publishMigration();

        if ($providers !== []) {
            $this->writeEnvironment($providers);
            $this->info('Social login configured for: ' . implode(', ', $providers) . '.');
        }

        if ($this->option('publish-routes')) {
            $this->call('vendor:publish', [
                '--tag'   => 'laranail::authkit-social-login-routes',
                '--force' => (bool) $this->option('force'),
            ]);
            $this->line('Set AUTHKIT_SOCIAL_ROUTES_MODE=published and load routes/laranail-authkit-social-login-web.php from the application route bootstrap.');
        }

        $this->line('Review config/laranail/authkit-social-login.php and run php artisan migrate.');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function providers(): array
    {
        $requested = $this->option('social');

        if ($requested === [] && $this->input->isInteractive()) {
            $labels = SocialProvider::labels();
            $default = [$labels[SocialProvider::GOOGLE->value] ?? 'Google'];
            $selected = $this->choice(
                question: 'Which social login providers would you like to enable?',
                choices: array_values($labels),
                default: $default,
                multiple: true,
            );
            $requested = array_map(
                static fn (string $label): string => (string) (array_search($label, $labels, true) ?: ''),
                is_array($selected) ? $selected : [$selected],
            );
        }

        return array_values(array_unique(array_filter(
            array: $requested,
            callback: static fn (mixed $slug): bool => is_string($slug) && SocialProvider::tryFrom($slug) !== null,
        )));
    }

    /** @param array<int, string> $providers */
    private function writeProviders(array $providers): void
    {
        // The config now publishes to config/laranail/authkit-social-login.php. An application that
        // published before 2026-10 has the deprecated bare config/authkit-social-login.php instead, and
        // that file is the one still being read, so it is the one to edit when the new one is absent.
        $path = config_path('laranail/authkit-social-login.php');
        $legacy = config_path('authkit-social-login.php');

        if (! is_file($path) && is_file($legacy)) {
            $path = $legacy;
        }

        if (! is_file($path) || $providers === []) {
            return;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return;
        }

        $values = "['" . implode("', '", $providers) . "']";
        $contents = preg_replace("/'providers'\s*=>\s*\[[^\]]*\]/", "'providers' => {$values}", $contents, 1) ?? $contents;
        file_put_contents($path, $contents);
    }

    private function publishMigration(): void
    {
        $migrations = glob(database_path('migrations/*create_socials_table.php')) ?: [];

        if ($migrations !== []) {
            return;
        }

        $this->call('vendor:publish', ['--tag' => 'laranail::authkit-social-login-migrations']);
    }

    /** @param array<int, string> $providers */
    private function writeEnvironment(array $providers): void
    {
        $prefix = (string) SocialConfig::get('web.prefix', 'auth');

        if (class_exists(\Simtabi\Laranail\AuthKit\Preset\Support\AuthPreset::class)) {
            $mounts = \Simtabi\Laranail\AuthKit\Preset\Support\AuthPreset::mounts();
            $prefix = $mounts[0]['prefix'] ?? $prefix;
        }

        $variables = [];

        foreach ($providers as $provider) {
            $upper = Str::upper($provider);
            $variables["AUTHKIT_{$upper}_CLIENT_ID"] = '';
            $variables["AUTHKIT_{$upper}_CLIENT_SECRET"] = '';
            $variables["AUTHKIT_{$upper}_REDIRECT"] = url('/' . trim($prefix, '/') . "/social/{$provider}/callback");
        }

        foreach ([base_path('.env'), base_path('.env.example')] as $path) {
            $this->appendMissingEnvironmentVariables($path, $variables);
        }
    }

    /** @param array<string, string> $variables */
    private function appendMissingEnvironmentVariables(string $path, array $variables): void
    {
        if (! is_file($path)) {
            return;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return;
        }

        $missing = [];

        foreach ($variables as $key => $value) {
            if (preg_match('/^\s*(?:export\s+)?' . preg_quote($key, '/') . '\s*=/m', $contents) !== 1) {
                $missing[] = "{$key}={$value}";
            }
        }

        if ($missing !== []) {
            file_put_contents($path, rtrim($contents) . "\n\n" . implode("\n", $missing) . "\n");
        }
    }
}
