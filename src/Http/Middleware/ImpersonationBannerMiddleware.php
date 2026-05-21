<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Http\Middleware;

use AIArmada\FilamentAuthz\Services\ImpersonateManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ImpersonationBannerMiddleware
{
    public function __construct(
        protected ImpersonateManager $manager
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (! $this->shouldInjectBanner($response)) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false) {
            return $response;
        }

        $banner = $this->renderBanner();

        if ($banner === '') {
            return $response;
        }

        $content = preg_replace(
            '/<body([^>]*)>/i',
            '<body$1>' . $banner,
            $content,
            1
        );

        if ($content !== null) {
            $response->setContent($content);
        }

        return $response;
    }

    protected function shouldInjectBanner(SymfonyResponse $response): bool
    {
        if (! $this->manager->isImpersonating()) {
            return false;
        }

        if (! $response instanceof Response) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html') && $contentType !== '') {
            return false;
        }

        return true;
    }

    protected function renderBanner(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return '';
        }

        $userName = method_exists($user, 'getFilamentName')
            ? $user->getFilamentName()
            : ($user->name ?? $user->email ?? 'User');

        $leaveUrl = htmlspecialchars(route('filament-authz.impersonate.leave'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrfToken = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars((string) __('filament-authz::filament-authz.impersonate.banner_message', ['name' => $userName]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $leaveText = htmlspecialchars((string) __('filament-authz::filament-authz.impersonate.leave'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<div id="impersonation-banner" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 99999;
    background: linear-gradient(90deg, #dc2626, #b91c1c);
    color: white;
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    font-family: system-ui, -apple-system, sans-serif;
    font-size: 0.875rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
">
    <span style="display: flex; align-items: center; gap: 0.5rem;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        {$message}
    </span>
    <form method="POST" action="{$leaveUrl}" style="margin: 0;">
        <input type="hidden" name="_token" value="{$csrfToken}">
        <button type="submit" style="
            background: white;
            color: #dc2626;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            border: 0;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: background 0.15s;
            cursor: pointer;
        " onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='white'">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            {$leaveText}
        </button>
    </form>
</div>
<style>
    body { padding-top: 2.5rem !important; }
</style>
HTML;
    }
}
