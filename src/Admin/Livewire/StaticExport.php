<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Support\Value;
use LynxCms\StaticSite\Actions\ExportStaticSite;
use LynxCms\StaticSite\Export\ExportReport;
use LynxCms\StaticSite\Export\OutputFolder;
use ZipArchive;

/**
 * The static export screen: where the site is exported and served, the last export, and buttons
 * that run static.export with or without a zip.
 *
 * @internal
 */
final class StaticExport extends Component
{
    /** What the last export did, shown under the buttons. */
    public string $status = '';

    /** @var array<string, list<string>> local URL => what needs PHP, from the last export */
    public array $warnings = [];

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('static.export'), 403);
    }

    public function export(bool $zip = false): void
    {
        try {
            $report = lynx()->run(ExportStaticSite::class, ['zip' => $zip])->valueAs(ExportReport::class);
        } catch (ValidationException $error) {
            $this->status = implode(' ', Value::strings($error->validator->errors()->all()));

            return;
        }

        $this->status = Value::string(__('lynx-static::admin.exported', ['pages' => $report->pages, 'files' => $report->files, 'redirects' => $report->redirects]));
        $this->warnings = $report->warnings;
    }

    public function render(): View
    {
        $path = Value::string(config('lynx-static.path'));
        $marker = $path.'/'.OutputFolder::MARKER;

        return view('lynx-static::admin.livewire.export', [
            'path' => $path,
            'url' => Value::nullableString(config('lynx-static.url')) ?? Value::string(config('app.url')),
            'exportedAt' => is_file($marker) ? date('Y-m-d H:i', (int) filemtime($marker)) : null,
            'hasZip' => is_file($path.'.zip'),
            'canZip' => class_exists(ZipArchive::class),
        ]);
    }
}
