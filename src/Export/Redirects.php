<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Illuminate\Filesystem\Filesystem;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageUrl;
use LynxCms\Core\Pages\PathResolver;

/**
 * Old addresses of renamed and moved pages (ADR-0011) for a site without PHP: a _redirects file
 * that Netlify and Cloudflare Pages answer with a 301, and a small page at each old address that
 * sends visitors on and names the new address as canonical, for hosts that read no such file.
 *
 * @internal
 */
final class Redirects
{
    /** @var array<string, string> old local URL => new local URL */
    private array $moves = [];

    public function __construct(private readonly PathResolver $paths, private readonly LanguageRegistry $languages, private readonly Filesystem $files) {}

    public function collect(Page $page): void
    {
        foreach ($this->paths->history((int) $page->id) as [$languageId, $path]) {
            $language = $this->languages->find($languageId);

            if ($language !== null && $language->is_active && $page->isActiveIn($language)) {
                $this->moves[PageUrl::of($language->url_prefix, $path)] ??= $page->localUrl($language);
            }
        }
    }

    /**
     * Writes what lynx-static.redirects turns on; addresses a page uses now keep that page.
     *
     * @param  array<string, true>  $taken  local URLs written as pages
     * @return int redirects written
     */
    public function write(string $folder, string $baseUrl, array $taken): int
    {
        $moves = array_diff_key($this->moves, $taken);
        $this->moves = [];

        if ($moves === []) {
            return 0;
        }

        if ((bool) config('lynx-static.redirects.file', true)) {
            $lines = array_map(fn (string $from, string $to): string => "{$from} {$to} 301", array_keys($moves), $moves);
            $this->files->put($folder.'/_redirects', implode("\n", $lines)."\n");
        }

        if ((bool) config('lynx-static.redirects.pages', true)) {
            foreach ($moves as $from => $to) {
                $this->files->ensureDirectoryExists(dirname(ExportPaths::fileFor($folder, $from)));
                $this->files->put(ExportPaths::fileFor($folder, $from), $this->page(rtrim($baseUrl, '/').$to));
            }
        }

        return count($moves);
    }

    private function page(string $url): string
    {
        $url = e($url);

        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="utf-8"><title>Moved</title>
        <meta http-equiv="refresh" content="0; url={$url}">
        <link rel="canonical" href="{$url}">
        <meta name="robots" content="noindex">
        </head><body><a href="{$url}">{$url}</a></body></html>

        HTML;
    }
}
