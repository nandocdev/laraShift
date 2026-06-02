@if(Session::has('impersonated_by'))
    <div class="w-full bg-amber-500 text-white px-4 py-2 flex items-center justify-between text-sm font-bold shadow-lg">
        <div class="flex items-center gap-2">
            <flux:icon icon="shield-exclamation" variant="solid" />
            <span>{{ __('IMPERSONATION ACTIVE') }} — {{ __('Your actions are being audited by platform administration.') }}</span>
        </div>
        <form method="POST" action="{{ route('tenant.support.logout') }}">
            @csrf
            <button type="submit" class="underline hover:text-zinc-200 transition-colors uppercase tracking-widest">
                {{ __('End Session') }}
            </button>
        </form>
    </div>
@endif
