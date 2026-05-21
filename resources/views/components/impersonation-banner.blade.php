@php
    use AIArmada\FilamentAuthz\Actions\ImpersonateAction;
    use Filament\Facades\Filament;

    $impersonatorGuard = ImpersonateAction::getImpersonatorGuard();
    $currentPanelGuard = Filament::getAuthGuard();
    $shouldShowBanner = ImpersonateAction::isImpersonating()
        && $currentPanelGuard
        && $impersonatorGuard === $currentPanelGuard;

    $user = Filament::auth()->user();
    $displayName = $user?->name ?? 'User';
@endphp

@if ($shouldShowBanner)
<style>
    :root {
        --impersonate-banner-height: 42px;
    }
    html {
        margin-top: var(--impersonate-banner-height);
    }
    .fi-topbar-ctn {
        top: var(--impersonate-banner-height) !important;
    }
    div.fi-layout > div > aside.fi-sidebar {
        margin-top: var(--impersonate-banner-height);
        height: calc(100vh - var(--impersonate-banner-height));
    }
    #impersonate-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        padding: 0.625rem 1rem;
        height: var(--impersonate-banner-height);
        background-color: rgb(245 158 11);
        color: white;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    }
    .dark #impersonate-banner {
        background-color: rgb(217 119 6);
    }
    #impersonate-banner .banner-content {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    #impersonate-banner .banner-text {
        font-size: 0.875rem;
        font-weight: 500;
    }
    #impersonate-banner .banner-link {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        border-radius: 0.5rem;
        background-color: rgb(255 255 255 / 0.2);
        padding: 0.25rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: white;
        text-decoration: none;
        transition: background-color 0.15s;
    }
    #impersonate-banner .banner-link:hover {
        background-color: rgb(255 255 255 / 0.3);
    }
</style>
<div id="impersonate-banner">
    <div class="banner-content">
        <x-heroicon-o-exclamation-triangle style="width: 1.25rem; height: 1.25rem;" />
        <span class="banner-text">
            {{ __('filament-authz::filament-authz.impersonate.banner_message', ['name' => $displayName]) }}
        </span>
    </div>
    <form method="POST" action="{{ route('filament-authz.impersonate.leave') }}">
        @csrf

        <button type="submit" class="banner-link">
            <x-heroicon-o-arrow-left-on-rectangle style="width: 1rem; height: 1rem;" />
            {{ __('filament-authz::filament-authz.impersonate.leave') }}
        </button>
    </form>
</div>
@endif
