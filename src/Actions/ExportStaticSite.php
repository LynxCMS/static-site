<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;
use LynxCms\StaticSite\Export\Exporter;
use LynxCms\StaticSite\Export\ExportReport;
use LynxCms\StaticSite\Export\OutputFolder;
use LynxCms\StaticSite\StaticSiteBuilt;
use Throwable;
use ZipArchive;

/**
 * Exports the site as static files to lynx-static.path (ADR-0069), to host without PHP. The folder
 * and address come from config, never from input, so callers such as MCP cannot write elsewhere.
 * The export is built beside the folder and replaces it only once the action commits.
 */
final class ExportStaticSite extends Action
{
    public function __construct(private readonly Exporter $exporter, private readonly OutputFolder $folder) {}

    public static function name(): string
    {
        return 'static.export';
    }

    public static function description(): string
    {
        return 'Export the site as static HTML files to host without PHP: every published page in every language, the public files, a 404 page, the sitemap and redirects for old addresses. Replaces the previous export; zip also writes it as a zip file. A dry run counts the pages.';
    }

    public static function permission(): string
    {
        return 'static.export';
    }

    public static function rules(): array
    {
        return ['zip' => ['sometimes', 'boolean']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['zip' => $schema->boolean()->description('Also write the export as a zip file beside its folder.')];
    }

    public function handle(Input $input, ActionContext $context): ExportReport
    {
        $path = Value::string(config('lynx-static.path'));
        $url = Value::nullableString(config('lynx-static.url')) ?? Value::string(config('app.url'));
        $zip = Value::bool($input->get('zip'));

        if ($zip && ! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['zip' => 'Writing a zip needs the PHP zip extension.']);
        }

        if ($context->dryRun) {
            $report = new ExportReport;
            $report->pages = $this->exporter->count();
            $context->changes->updated('static_site', null, "Static site would export {$report->pages} pages to {$path}");

            return $report;
        }

        $staging = $this->folder->staging($path);

        try {
            $report = $this->exporter->build($staging, $url);
        } catch (Throwable $error) {
            $this->folder->discard($staging);

            throw $error;
        }

        $context->changes->updated('static_site', null, "Static site exported: {$report->pages} pages to {$path}", $report->toArray());
        $context->event(new StaticSiteBuilt($staging, $path, $zip));

        return $report;
    }
}
