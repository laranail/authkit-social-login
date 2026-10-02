<div class="py-12">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-lg bg-white px-6 py-8 sm:px-8">
            <header>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Social Accounts</h1>
                <p class="mt-2 text-sm text-gray-600">Review the social providers available for sign-in and the accounts linked to your profile.</p>
            </header>

            @if (session('status') === 'social-account-unlinked')
                <div role="status" class="mt-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    The account was disconnected.
                </div>
            @endif

            @error('provider')
                <div role="alert" class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $message }}</div>
            @enderror

            @if ($supportedProviders->isNotEmpty())
                <section class="mt-10">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Supported providers</h2>
                            <p class="mt-1 text-sm text-gray-600">Providers configured by this application.</p>
                        </div>
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $supportedProviders->count() }} {{ \Illuminate\Support\Str::plural('provider', $supportedProviders->count()) }}</span>
                    </div>

                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($supportedProviders as $provider)
                            <li class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-4 py-4">
                                <span class="flex min-w-0 items-center gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-gray-50">
                                        @includeWhen(view()->exists($provider['icon']), $provider['icon'])
                                    </span>
                                    <span class="font-medium text-gray-800">{{ $provider['label'] }}</span>
                                </span>
                                @if ($provider['connected'])
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">Connected</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">Not connected</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="mt-10">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Linked social accounts</h2>
                    <p class="mt-1 text-sm text-gray-600">These accounts can be used to sign in.</p>
                </div>

                @if ($accounts->isEmpty())
                    <div class="mt-4 rounded-md border border-dashed border-gray-300 px-5 py-8 text-center">
                        <p class="font-medium text-gray-800">No social accounts linked</p>
                        <p class="mt-1 text-sm text-gray-600">Linked social accounts will appear here when available.</p>
                    </div>
                @else
                    <ul class="mt-4 divide-y divide-gray-200 rounded-md border border-gray-200">
                        @foreach ($accounts as $account)
                            <li class="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-gray-50">
                                            @includeWhen(view()->exists($account['icon']), $account['icon'])
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900">{{ $account['label'] }}</p>
                                            @if ($account['email'])
                                                <p class="mt-1 truncate text-sm text-gray-600">{{ $account['email'] }}</p>
                                            @else
                                                <p class="mt-1 text-sm text-gray-500">No email address provided</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if ($account['can_unlink'])
                                    <form method="POST" action="{{ route(\Simtabi\Laranail\AuthKit\Social\Support\SocialWebRoutes::currentMount()['name'] . 'user-social-accounts.destroy', ['provider' => $account['slug']]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                                            Disconnect
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex w-fit items-center rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-600" title="Add another sign-in method first">
                                        Only sign-in method
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-gray-500">Your only sign-in method cannot be disconnected. Add another sign-in method first.</p>
                @endif
            </section>
        </div>
    </div>
</div>
