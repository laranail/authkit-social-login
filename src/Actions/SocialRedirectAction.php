<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Actions;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConfig;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Simtabi\Laranail\AuthKit\Social\Support\SocialRedirectResult;
use Simtabi\Laranail\AuthKit\Social\Support\ResolvesIdentityProvider;
use Simtabi\Laranail\AuthKit\Social\Contracts\SocialRedirectActionInterface;

class SocialRedirectAction implements SocialRedirectActionInterface
{
    use ResolvesIdentityProvider;

    public function __construct(
        private SocialiteFactory $socialite,
    ) {}

    public function execute(Request $request): SocialRedirectResult
    {
        $provider = $this->resolveProvider(request: $request);

        $driver = $this->socialite->driver($provider->driver());

        // Scopes and optional parameters were configurable and ignored: nothing read them, so the
        // only scopes in effect were the driver's defaults. `with` is what carries Google's `hd`
        // domain restriction and `prompt=select_account`, neither of which was reachable before.
        $settings = SocialConfig::get("{$provider->slug()}", []);

        if (is_array($settings)) {
            if (! empty($settings['scopes']) && is_array($settings['scopes']) && method_exists($driver, 'scopes')) {
                $driver->scopes($settings['scopes']);
            }

            if (! empty($settings['with']) && is_array($settings['with']) && method_exists($driver, 'with')) {
                $driver->with($settings['with']);
            }
        }

        return new SocialRedirectResult(
            url: $driver->redirect()->getTargetUrl(),
        );
    }
}
