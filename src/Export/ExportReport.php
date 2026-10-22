<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

/**
 * What an export wrote: pages, other addresses, copied files and redirects, with the pages left
 * out and the pages that need PHP to work fully.
 *
 * @api
 */
final class ExportReport
{
    public int $pages = 0;

    /** Addresses besides pages, such as the sitemap and the not found page. */
    public int $extras = 0;

    public int $files = 0;

    public int $redirects = 0;

    /** @var array<string, string> local URL => why it was left out */
    public array $skipped = [];

    /** @var array<string, list<string>> local URL => what will not work without PHP */
    public array $warnings = [];

    /** @return array{pages: int, extras: int, files: int, redirects: int, skipped: array<string, string>, warnings: array<string, list<string>>} */
    public function toArray(): array
    {
        return [
            'pages' => $this->pages,
            'extras' => $this->extras,
            'files' => $this->files,
            'redirects' => $this->redirects,
            'skipped' => $this->skipped,
            'warnings' => $this->warnings,
        ];
    }
}
