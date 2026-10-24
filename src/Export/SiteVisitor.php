<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Livewire\LivewireManager;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Opens an address through the HTTP kernel as a visitor would, so the export holds exactly what
 * the site sends: same routes, middleware, visibility rules and templates. Each visit is a guest's,
 * even when an editor starts the export from the admin: the visit gets an empty session that is
 * never stored, and no signed-in user, and the editor's own are put back afterwards.
 *
 * @internal
 */
final readonly class SiteVisitor
{
    /** The page cache's bypass cookie: export visits must neither read nor fill that cache. */
    private const string BYPASS_COOKIE = 'lynx_bypass';

    public function __construct(
        private Kernel $kernel,
        private CookieJar $cookies,
        private PageVisibility $visibility,
        private AuthManager $auth,
        private SessionManager $sessions,
    ) {}

    public function visit(string $url): Response
    {
        // Visits share one process: cookies queued by one visit would go out with the next, and
        // Livewire keeps per request flags (scripts printed) until reset, as Octane does.
        $this->cookies->flushQueuedCookies();

        if (class_exists(LivewireManager::class) && app()->bound(LivewireManager::class)) {
            app(LivewireManager::class)->flushState();
        }

        $request = Request::create($url, cookies: [self::BYPASS_COOKIE => '1']);

        return $this->asGuest(fn (): Response => $this->visibility->asVisitor(function () use ($request): Response {
            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);

            return $response;
        }));
    }

    /**
     * Runs $work with no one signed in and an empty session that is never stored, then puts the
     * signed-in user and the session back: an export started from the admin shares the editor's
     * session store and auth guards, and must neither list nor render pages as the editor.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function asGuest(Closure $work): mixed
    {
        $user = $this->auth->guard()->user();
        $store = $this->sessions->driver();
        $saved = $store instanceof Store ? [$store->getId(), $store->all(), $store->getHandler()] : null;

        if ($store instanceof Store) {
            $store->flush();
            $store->setHandler(new NullSessionHandler);
        }

        $this->auth->forgetGuards();

        try {
            return $work();
        } finally {
            $this->auth->forgetGuards();

            if ($user !== null) {
                $this->auth->guard()->setUser($user);
            }

            if ($store instanceof Store && $saved !== null) {
                [$id, $attributes, $handler] = $saved;
                $store->setHandler($handler);
                $store->setId($id);
                $store->flush();
                $store->replace($attributes);
            }
        }
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
