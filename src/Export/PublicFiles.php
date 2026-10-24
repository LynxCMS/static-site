<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use FilesystemIterator;
use Illuminate\Filesystem\Filesystem;
use LynxCms\Core\Support\Value;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copies public/ into the export (built assets, media and its image variations, icons), following
 * links such as public/storage, and leaving out what only works with PHP (lynx-static.exclude)
 * and the media of pages a guest could not open (MediaFolders).
 *
 * @internal
 */
final readonly class PublicFiles
{
    public function __construct(private Filesystem $files, private MediaFolders $media) {}

    /** @return int files copied */
    public function copyTo(string $destination): int
    {
        $source = rtrim(public_path(), '/');

        if (! $this->files->isDirectory($source)) {
            return 0;
        }

        $copied = 0;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
        );

        foreach ($items as $item) {
            if (! $item instanceof SplFileInfo || ! $item->isFile()) {
                continue;
            }

            $relative = substr($item->getPathname(), strlen($source) + 1);

            if (! $this->isExcluded($relative) && $this->media->allows($relative)) {
                $this->files->ensureDirectoryExists(dirname($destination.'/'.$relative));
                $this->files->copy($item->getPathname(), $destination.'/'.$relative);
                $copied++;
            }
        }

        return $copied;
    }

    /** A pattern matches the path or any folder it is in, so "lynx-cache" leaves the folder out. */
    private function isExcluded(string $relative): bool
    {
        $patterns = Value::strings(config('lynx-static.exclude', []));
        $segments = explode('/', $relative);
        $counter = count($segments);

        for ($depth = 1; $depth <= $counter; $depth++) {
            $prefix = implode('/', array_slice($segments, 0, $depth));

            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
