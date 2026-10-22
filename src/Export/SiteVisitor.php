<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Livewire\LivewireManager;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Opens an address through the HTTP kernel as a visitor would, so the export holds exactly what
 * the site sends: same routes, middleware, visibility rules and templates.
 *
 * @internal
 */
final readonly class SiteVisitor
{
    /** The page cache's bypass cookie: export visits must neither read nor fill that cache. */
    private const string BYPASS_COOKIE = 'lynx_bypass';

    public function __construct(private Kernel $kernel, private CookieJar $cookies, private PageVisibility $visibility) {}

    public function visit(string $url): Response
    {
        // Visits share one process: cookies queued by one visit would go out with the next, and
        // Livewire keeps per request flags (scripts printed) until reset, as Octane does.
        $this->cookies->flushQueuedCookies();

        if (class_exists(LivewireManager::class) && app()->bound(LivewireManager::class)) {
            app(LivewireManager::class)->flushState();
        }

        $request = Request::create($url, cookies: [self::BYPASS_COOKIE => '1']);

        return $this->visibility->asVisitor(function () use ($request): Response {
            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);

            return $response;
        });
    }

    /** The body as a visitor receives it, also for files and streams, whose content is sent, not held. */
    public static function body(Response $response): string
    {
        if ($response instanceof BinaryFileResponse) {
            return (string) file_get_contents($response->getFile()->getPathname());
        }

        if ($response instanceof StreamedResponse) {
            ob_start();
            $response->sendContent();

            return (string) ob_get_clean();
        }

        return (string) $response->getContent();
    }
}
