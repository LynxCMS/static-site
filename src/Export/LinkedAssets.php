<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Illuminate\Filesystem\Filesystem;

/**
 * Scripts, styles and icons pages load from this site that are not files in public/, such as
 * Livewire's script, which a route sends. Each is fetched once through the site and written at
 * its path; a static host ignores the query string a versioned URL adds.
 *
 * @internal
 */
final class LinkedAssets
{
    private const string REFERENCE = '/(?:\bsrc|<link\b[^>]*?\bhref)\s*=\s*["\']([^"\']+)["\']/i';

    /** @var array<string, true> local paths */
    private array $paths = [];

    public function __construct(private readonly SiteVisitor $visitor, private readonly Filesystem $files) {}

    public function collect(string $html, string $baseUrl): void
    {
        preg_match_all(self::REFERENCE, $html, $matches);

        foreach ($matches[1] as $reference) {
            $path = $this->localPath(html_entity_decode($reference), $baseUrl);

            if ($path !== null) {
                $this->paths[$path] = true;
            }
        }
    }

    /** @return int files written */
    public function write(string $folder, string $baseUrl): int
    {
        $written = 0;

        foreach (array_keys($this->paths) as $path) {
            $file = ExportPaths::assetFor($folder, $path);

            if ($this->files->exists($file) || $file === $folder.'/') {
                continue;
            }

            $response = $this->visitor->visit($baseUrl.$path);

            if ($response->isOk() && ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
                $this->files->ensureDirectoryExists(dirname($file));
                $this->files->put($file, SiteVisitor::body($response));
                $written++;
            }
        }

        $this->paths = [];

        return $written;
    }

    /** The path of a reference to this site, without its query string; null for other sites. */
    private function localPath(string $reference, string $baseUrl): ?string
    {
        if (str_starts_with($reference, $baseUrl.'/')) {
            $reference = substr($reference, strlen($baseUrl));
        }

        if (! str_starts_with($reference, '/') || str_starts_with($reference, '//')) {
            return null;
        }

        $path = (string) parse_url($reference, PHP_URL_PATH);

        return $path === '' || $path === '/' ? null : $path;
    }
}
