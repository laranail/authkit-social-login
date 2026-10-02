@php
    $providers = \Simtabi\Laranail\AuthKit\Social\Support\SocialProviders::buttons();
    $redirectRoute = \Simtabi\Laranail\AuthKit\Social\Support\SocialProviders::redirectRouteName();
@endphp

@if (count($providers) > 0)
    <div class="grid grid-cols-{{ min(count($providers), 3) }} gap-3 mb-6">
        @foreach ($providers as $provider)
            <a
                href="{{ route($redirectRoute, ['provider' => $provider['slug']]) }}"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors {{ $provider['class'] }}"
                aria-label="Continue with {{ $provider['label'] }}"
            >
                @includeWhen(view()->exists($provider['icon']), $provider['icon'])
                <span class="hidden sm:inline">{{ $provider['label'] }}</span>
            </a>
        @endforeach
    </div>
@endif
