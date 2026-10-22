<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Admin;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Support\Value;
use LynxCms\StaticSite\Admin\Livewire\StaticExport;

/**
 * Adds the static export screen to the admin: export, and download the last export as a zip.
 * Called only when lynxcms/admin is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    private const int NAVIGATION_ORDER = 60;

    public static function register(): void
    {
        Livewire::component('lynx-static.export', StaticExport::class);
        $canExport = fn (): bool => app(AdminAccess::class)->allows('static.export');
        app(Navigation::class)->add('Static export', 'lynx.admin.static', order: self::NAVIGATION_ORDER, visible: $canExport, icon: 'package', group: Navigation::SETTINGS);

        Admin::routes(function () use ($canExport): void {
            Route::get('static', fn () => $canExport() ? view('lynx-static::admin.index') : abort(403))->name('static');
            Route::get('static/download', function () use ($canExport) {
                $zip = Value::string(config('lynx-static.path')).'.zip';
                abort_unless($canExport() && is_file($zip), 404);

                return response()->download($zip, 'static-site.zip');
            })->name('static.download');
        });
    }
}
