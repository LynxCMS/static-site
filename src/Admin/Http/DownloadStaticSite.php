<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Admin\Http;

use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The zip of the last static export.
 *
 * @internal
 */
final readonly class DownloadStaticSite
{
    public function __construct(private AdminAccess $access) {}

    public function __invoke(): BinaryFileResponse
    {
        $zip = Value::string(config('lynx-static.path')).'.zip';
        abort_unless($this->access->allows('static.export') && is_file($zip), 404);

        return response()->download($zip, 'static-site.zip');
    }
}
