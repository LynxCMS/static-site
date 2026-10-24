<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\Publication;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Support\Value;

/**
 * Which pages' media folders (lynxcms/media keeps a page's files in {root}/{page id}/ on its
 * disk) an export may copy: only those of pages a guest could open, published, out of the trash
 * and allowed by the visibility rules. Media of drafts' pages, unpublished and members-only pages
 * stays out of a copy meant for public hosting.
 *
 * @internal
 */
final class MediaFolders
{
    /** @var array<int, bool> page id => may copy */
    private array $decided = [];

    private ?string $prefix = null;

    public function __construct(
        private readonly PageLoader $pages,
        private readonly Publication $publication,
        private readonly Trash $trash,
        private readonly PageVisibility $visibility,
    ) {}

    /** Whether a file at this path relative to public/ may be copied. */
    public function allows(string $relative): bool
    {
        $prefix = $this->prefix();

        if ($prefix === '' || ! str_starts_with($relative, $prefix.'/')) {
            return true;
        }

        $folder = strstr(substr($relative, strlen($prefix) + 1), '/', true);

        return $folder !== false && ctype_digit($folder) && $this->opens((int) $folder);
    }

    private function opens(int $pageId): bool
    {
        if (! array_key_exists($pageId, $this->decided)) {
            $page = $this->pages->find($pageId);
            $this->decided[$pageId] = $page instanceof Page
                && ! $this->trash->holds($pageId)
                && $this->publication->isPublished($page)
                && $this->visibility->asVisitor(fn (): bool => $this->visibility->allows($page));
        }

        return $this->decided[$pageId];
    }

    /** The media root's path under public/ ("storage/lynx"), or '' when media lives elsewhere. */
    private function prefix(): string
    {
        if ($this->prefix !== null) {
            return $this->prefix;
        }

        $disk = Value::string(config('lynx-media.disk'));
        $path = $disk === '' ? '' : trim((string) parse_url(Storage::disk($disk)->url(Value::string(config('lynx-media.root'), 'lynx')), PHP_URL_PATH), '/');

        return $this->prefix = $path !== '' && is_dir(public_path($path)) ? $path : '';
    }
}
