<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

/**
 * Where an address is written in the export: pages as {path}/index.html, so a host answers both
 * /about and /about/, and other addresses (sitemap.xml) as the file they name.
 *
 * @internal
 */
final class ExportPaths
{
    public static function fileFor(string $folder, string $localUrl): string
    {
        $path = self::path($localUrl);

        return $folder.'/'.($path === '' ? '' : $path.'/').'index.html';
    }

    public static function assetFor(string $folder, string $localUrl): string
    {
        return $folder.'/'.self::path($localUrl);
    }

    /** The decoded path without empty, "." or ".." segments, so nothing lands outside the folder. */
    private static function path(string $localUrl): string
    {
        $segments = explode('/', rawurldecode((string) parse_url($localUrl, PHP_URL_PATH)));

        return implode('/', array_filter($segments, fn (string $segment): bool => ! in_array($segment, ['', '.', '..'], true)));
    }
}
