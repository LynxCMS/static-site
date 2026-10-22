<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Closure;
use Illuminate\Filesystem\Filesystem;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageUrls;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds a static copy of the site in a folder (ADR-0069): every page a visitor can open, in every
 * language, the extra addresses in lynx-static.paths, a 404.html, the public files and redirects.
 * Everything renders as if the site were served at the static address.
 *
 * @internal
 */
final readonly class Exporter
{
    /** Pages loaded per query. */
    private const int CHUNK = 200;

    private const string LIVEWIRE_EXPIRES = 'Fri, 01 Jan 1990 00:00:00 GMT';

    /** An address no page uses, for the not found page. */
    private const string MISSING = '/lynx-static-not-found';

    public function __construct(
        private SiteVisitor $visitor,
        private PageUrls $urls,
        private PageVisibility $visibility,
        private Redirects $redirects,
        private PublicFiles $publicFiles,
        private LinkedAssets $assets,
        private Filesystem $files,
        private PublicAddress $address,
    ) {}

    public function build(string $folder, string $baseUrl): ExportReport
    {
        $report = new ExportReport;
        $baseUrl = rtrim($baseUrl, '/');

        $this->address->run($baseUrl, function () use ($folder, $baseUrl, $report): void {
            $taken = $this->pages($folder, $baseUrl, $report);
            $this->extras($folder, $baseUrl, $report);
            $this->notFound($folder, $baseUrl, $report);
            // After rendering: pages make the image sizes they show on first use, in public/.
            $report->files = $this->publicFiles->copyTo($folder);
            $report->files += $this->assets->write($folder, $baseUrl);
            $report->redirects = $this->redirects->write($folder, $baseUrl, $taken);
        });

        return $report;
    }

    /** How many pages an export would write, without rendering them. */
    public function count(): int
    {
        $count = 0;
        $this->eachPage(function (Page $page) use (&$count): void {
            $count += count($this->urls->of($page));
        });

        return $count;
    }

    /** @return array<string, true> local URLs written */
    private function pages(string $folder, string $baseUrl, ExportReport $report): array
    {
        $written = [];
        $this->eachPage(function (Page $page) use ($folder, $baseUrl, $report, &$written): void {
            $this->redirects->collect($page);

            foreach ($this->urls->of($page) as $url) {
                if ($this->writePage($folder, $baseUrl, $url, $report)) {
                    $written[$url] = true;
                }
            }
        });

        return $written;
    }

    private function writePage(string $folder, string $baseUrl, string $url, ExportReport $report): bool
    {
        $response = $this->visitor->visit($baseUrl.$url);
        $reason = $this->skipReason($response);

        if ($reason !== null) {
            $report->skipped[$url] = $reason;

            return false;
        }

        $html = SiteVisitor::body($response);
        $file = ExportPaths::fileFor($folder, $url);
        $this->files->ensureDirectoryExists(dirname($file));
        $this->files->put($file, $html);
        $report->pages++;
        $this->assets->collect($html, $baseUrl);
        $warnings = PageWarnings::of($html, $baseUrl);

        if ($warnings !== []) {
            $report->warnings[$url] = $warnings;
        }

        return true;
    }

    /** Only what any visitor would get: a 200 page, not something marked for one visitor only. */
    private function skipReason(Response $response): ?string
    {
        return match (true) {
            $response->isRedirection() => 'redirects to '.$response->headers->get('Location'),
            ! $response->isOk() => 'answers with '.$response->getStatusCode(),
            $response->headers->hasCacheControlDirective('no-store') && ! $this->isLivewireBackButtonGuard($response) => 'private to the visitor',
            default => null,
        };
    }

    /**
     * Livewire marks every page with a component no-store, so the back button shows the live
     * component: about the browser, not about who may see the page. Only its exact headers count.
     */
    private function isLivewireBackButtonGuard(Response $response): bool
    {
        return $response->headers->get('Expires') === self::LIVEWIRE_EXPIRES
            && $response->headers->get('Pragma') === 'no-cache'
            && $response->headers->hasCacheControlDirective('must-revalidate');
    }

    /** The addresses in lynx-static.paths; a sitemap index brings its parts. */
    private function extras(string $folder, string $baseUrl, ExportReport $report): void
    {
        $queue = Value::strings(config('lynx-static.paths', []));
        $seen = [];

        while (($path = array_shift($queue)) !== null) {
            if (isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;
            $response = $this->visitor->visit($baseUrl.'/'.ltrim($path, '/'));

            if ($response->isOk()) {
                $content = SiteVisitor::body($response);
                $this->files->ensureDirectoryExists(dirname(ExportPaths::assetFor($folder, $path)));
                $this->files->put(ExportPaths::assetFor($folder, $path), $content);
                $report->extras++;
                array_push($queue, ...$this->sitemapParts($content, $baseUrl));
            }
        }
    }

    /** @return list<string> paths of the parts a sitemap index lists on this site */
    private function sitemapParts(string $content, string $baseUrl): array
    {
        if (! str_contains($content, '<sitemapindex')) {
            return [];
        }

        preg_match_all('#<loc>'.preg_quote($baseUrl, '#').'(/[^<]+)</loc>#', $content, $matches);

        return $matches[1];
    }

    private function notFound(string $folder, string $baseUrl, ExportReport $report): void
    {
        $response = $this->visitor->visit($baseUrl.self::MISSING);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            $this->files->put($folder.'/404.html', SiteVisitor::body($response));
            $report->extras++;
        }
    }

    /** Published pages of templates with URLs, found as a visitor finds them. */
    private function eachPage(Closure $each): void
    {
        $templates = lynx()->templates->all()->filter(fn (Template $template): bool => $template->hasUrl())->pluck('name')->all();

        if ($templates === []) {
            return;
        }

        $selector = 'template='.implode('|', Value::strings($templates)).', include=hidden, sort=id';

        for ($start = 0; ; $start += self::CHUNK) {
            $pages = $this->visibility->asVisitor(fn () => lynx()->pages->find("{$selector}, start={$start}, limit=".self::CHUNK));

            foreach ($pages as $page) {
                $each($page);
            }

            if ($pages->count() < self::CHUNK) {
                return;
            }
        }
    }
}
