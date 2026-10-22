<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;

/**
 * The export folder. Exports are built in a staging folder beside it and swapped in whole, so the
 * folder never holds half an export, and a folder is only ever replaced when an export made it
 * (it holds the marker file) or it is empty.
 *
 * @internal
 */
final readonly class OutputFolder
{
    public const string MARKER = '.lynx-static';

    private const string STAGING = '.lynx-';

    public function __construct(private Filesystem $files) {}

    /** A new, empty staging folder for the target; staging folders left by failed exports go first. */
    public function staging(string $target): string
    {
        $this->assertReplaceable($target);

        foreach (Value::strings($this->files->glob($target.self::STAGING.'*', GLOB_ONLYDIR)) as $stale) {
            $this->files->deleteDirectory($stale);
        }

        $staging = $target.self::STAGING.Str::lower(Str::random(8));
        $this->files->ensureDirectoryExists($staging);
        $this->files->put($staging.'/'.self::MARKER, '');

        return $staging;
    }

    /** Puts the staging folder in the target's place, and removes the export it replaces. */
    public function publish(string $staging, string $target): void
    {
        $this->assertReplaceable($target);
        $old = $target.self::STAGING.'old-'.Str::lower(Str::random(8));

        if ($this->files->isDirectory($target)) {
            $this->files->move($target, $old);
        }

        $this->files->move($staging, $target);
        $this->files->deleteDirectory($old);
    }

    public function discard(string $staging): void
    {
        $this->files->deleteDirectory($staging);
    }

    private function assertReplaceable(string $target): void
    {
        $isOwn = ! $this->files->exists($target)
            || $this->files->exists($target.'/'.self::MARKER)
            || ($this->files->isDirectory($target) && $this->files->isEmptyDirectory($target));

        if (! $isOwn) {
            throw ValidationException::withMessages(['path' => "The folder [{$target}] was not made by a static export, so it is left as it is. Empty it or choose another folder."]);
        }
    }
}
