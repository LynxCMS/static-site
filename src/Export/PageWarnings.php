<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

/**
 * What in a page's HTML needs PHP to work, so the export report can say which pages lose it.
 *
 * @internal
 */
final class PageWarnings
{
    /** @var array<string, string> pattern => warning */
    private const array CHECKS = [
        '/<form\b[^>]*\bmethod\s*=\s*["\']?post\b/i' => 'a form that posts to the site',
        '/\bwire:(snapshot|id)\s*=/' => 'a Livewire component',
        '/<lynx-island\b/' => 'an island, filled in by PHP after the page loads',
    ];

    /** @return list<string> */
    public static function of(string $html, string $baseUrl): array
    {
        $warnings = [];

        foreach (self::CHECKS as $pattern => $warning) {
            if (preg_match($pattern, $html) === 1) {
                $warnings[] = $warning;
            }
        }

        if (self::linksWithQuery($html, $baseUrl)) {
            $warnings[] = 'links with a query string, which a static site answers with the page without it';
        }

        return $warnings;
    }

    /** Links to this site with a query string, such as ?page=2 on a paginated listing. */
    private static function linksWithQuery(string $html, string $baseUrl): bool
    {
        $site = preg_quote(rtrim($baseUrl, '/'), '#');

        return preg_match('#\bhref\s*=\s*["\'](?:'.$site.')?/[^"\'\#]*\?[^"\']#i', $html) === 1;
    }
}
