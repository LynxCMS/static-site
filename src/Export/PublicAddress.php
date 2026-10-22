<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Closure;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Routing\UrlGenerator;
use LynxCms\Core\Support\Value;

/**
 * Renders as the public site for the length of an export: at the static address (url(), asset(),
 * canonical tags, and files on disks served from the app's address, such as media on "public"),
 * with the array session driver, and by default as production without debug, so an export made on
 * a local or staging install is not marked noindex and its error pages show no debug output.
 *
 * @internal
 */
final readonly class PublicAddress
{
    public function __construct(private UrlGenerator $generator, private FilesystemManager $disks) {}

    public function run(string $baseUrl, Closure $work): void
    {
        $environment = Value::nullableString(config('lynx-static.environment'));
        $previousEnvironment = app()->environment();
        $previous = ['app.url' => config('app.url'), 'app.debug' => config('app.debug'), 'session.driver' => config('session.driver')] + $this->diskUrls();
        // Visits must not leave a session behind each, in the database or on disk.
        config(['app.url' => $baseUrl, 'session.driver' => 'array'] + $this->movedDiskUrls($baseUrl) + ($environment === null ? [] : ['app.debug' => false]));
        $this->forgetDisks();
        $this->generator->forceRootUrl($baseUrl);
        $this->generator->forceScheme((string) parse_url($baseUrl, PHP_URL_SCHEME));

        if ($environment !== null) {
            app()->instance('env', $environment);
        }

        try {
            $work();
        } finally {
            app()->instance('env', $previousEnvironment);
            config($previous);
            $this->forgetDisks();
            $this->generator->forceRootUrl(null);
            $this->generator->forceScheme(null);
        }
    }

    /** @return array<string, string> config key => URL of every disk with one */
    private function diskUrls(): array
    {
        $urls = [];

        foreach (Value::map(config('filesystems.disks', [])) as $name => $disk) {
            $url = Value::nullableString(Value::map($disk)['url'] ?? null);

            if ($url !== null) {
                $urls["filesystems.disks.{$name}.url"] = $url;
            }
        }

        return $urls;
    }

    /** @return array<string, string> disks served from the app's address, moved to the static one */
    private function movedDiskUrls(string $baseUrl): array
    {
        $appUrl = rtrim(Value::string(config('app.url')), '/');
        $moved = [];

        foreach ($this->diskUrls() as $key => $url) {
            if ($appUrl !== '' && ($url === $appUrl || str_starts_with($url, $appUrl.'/'))) {
                $moved[$key] = $baseUrl.substr($url, strlen($appUrl));
            }
        }

        return $moved;
    }

    /** Disks already made keep their URL; forgetting them makes the next use read the config. */
    private function forgetDisks(): void
    {
        $this->disks->forgetDisk(array_keys(Value::map(config('filesystems.disks', []))));
    }
}
