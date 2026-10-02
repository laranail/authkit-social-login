@if (class_exists(\Simtabi\Laranail\AuthKit\Preset\Support\AuthPreset::class)
    && view()->exists('laranail/authkit-preset::components.dashboard-layout'))
    <x-dynamic-component :component="'laranail-authkit-preset::dashboard-layout'" title="Social Accounts">
        @include('laranail/authkit-social-login::components.social-accounts-content')
    </x-dynamic-component>
@else
    @include('laranail/authkit-social-login::components.social-accounts-content')
@endif
