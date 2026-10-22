<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;
use LynxCms\StaticSite\Actions\ExportStaticSite;
use LynxCms\StaticSite\Export\ExportReport;
use Override;

/**
 * lynx:static: exports the site as static files, with what was written and what needs PHP.
 */
final class StaticExportCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:static
        {--path= : Folder to export to (default lynx-static.path)}
        {--url= : Address the static site is served at (default lynx-static.url, then app.url)}
        {--zip : Also write the export as a zip file beside the folder}';

    #[Override]
    protected $description = 'Export the site as static files to host without PHP';

    public function handle(): int
    {
        foreach (['path' => 'lynx-static.path', 'url' => 'lynx-static.url'] as $option => $key) {
            $value = Value::nullableString($this->option($option));

            if ($value !== null && $value !== '') {
                config([$key => $value]);
            }
        }

        try {
            $report = lynx()->run(ExportStaticSite::class, ['zip' => (bool) $this->option('zip')])->valueAs(ExportReport::class);
        } catch (ValidationException $error) {
            $this->error(implode(' ', Value::strings($error->validator->errors()->all())));

            return self::FAILURE;
        }

        $this->report($report);

        return self::SUCCESS;
    }

    private function report(ExportReport $report): void
    {
        $this->info("Exported {$report->pages} pages, {$report->extras} other files, {$report->files} public files and {$report->redirects} redirects to ".Value::string(config('lynx-static.path')).'.');

        foreach ($report->skipped as $url => $reason) {
            $this->line("  Left out {$url}: {$reason}");
        }

        foreach ($report->warnings as $url => $warnings) {
            $this->warn("  {$url}: ".implode(', ', $warnings));
        }
    }
}
