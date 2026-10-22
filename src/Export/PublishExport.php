<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Illuminate\Filesystem\Filesystem;
use LynxCms\StaticSite\StaticSiteBuilt;
use RuntimeException;
use ZipArchive;

/**
 * Puts a built export in place of the last one and, when asked, writes it as {path}.zip.
 *
 * @internal
 */
final readonly class PublishExport
{
    public function __construct(private OutputFolder $folder, private Filesystem $files) {}

    public function handle(StaticSiteBuilt $event): void
    {
        $this->folder->publish($event->staging, $event->path);

        if ($event->zip) {
            $this->zip($event->path, $event->path.'.zip');
        }
    }

    private function zip(string $folder, string $file): void
    {
        $zip = new ZipArchive;
        $temporary = $file.'.part';

        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("The static export could not write [{$temporary}].");
        }

        foreach ($this->files->allFiles($folder, hidden: true) as $item) {
            $relative = str_replace('\\', '/', $item->getRelativePathname());

            if ($relative !== OutputFolder::MARKER) {
                $zip->addFile($item->getPathname(), $relative);
            }
        }

        $zip->close();
        $this->files->move($temporary, $file);
    }
}
