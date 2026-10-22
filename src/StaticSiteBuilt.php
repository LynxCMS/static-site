<?php

declare(strict_types=1);

namespace LynxCms\StaticSite;

/**
 * A static export finished building in its staging folder. Queued by static.export and dispatched
 * only once the action commits, so a halted or failed export never replaces the last good one.
 *
 * @api
 */
final readonly class StaticSiteBuilt
{
    public function __construct(public string $staging, public string $path, public bool $zip) {}
}
