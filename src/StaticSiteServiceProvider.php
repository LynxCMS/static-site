<?php

declare(strict_types=1);

namespace LynxCms\StaticSite;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\StaticSite\Actions\ExportStaticSite;
use LynxCms\StaticSite\Admin\AdminIntegration;
use LynxCms\StaticSite\Console\StaticExportCommand;
use LynxCms\StaticSite\Export\PublishExport;

/**
 * Registers the static site export (ADR-0069): the static.export action (and so its MCP and AI
 * tools), lynx:static, the static.export permission and the admin screen.
 *
 * @api
 */
final class StaticSiteServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-static.php', 'lynx-static');
    }

    public function boot(): void
    {
        $this->app->make(ActionRegistry::class)->register(ExportStaticSite::class);
        $this->app->make(PermissionRegistry::class)->register('static.export', 'Export the site as static files', requires: ['pages.view']);
        Event::listen(StaticSiteBuilt::class, [PublishExport::class, 'handle']);
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-static');

        if ($this->app->bound(Navigation::class)) {
            AdminIntegration::register();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([StaticExportCommand::class]);
            $this->publishes([self::ROOT.'/config/lynx-static.php' => config_path('lynx-static.php')], 'lynx-static-config');
        }
    }
}
