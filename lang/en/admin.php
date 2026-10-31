<?php

declare(strict_types=1);

return [
    'title' => 'Static export',
    'description' => 'The whole site as HTML files, to host without PHP.',
    'where' => 'Where',
    'where_help' => 'Exports to :path, for a site served at :url. Set both in :config.',
    'last' => 'Last export: :when.',
    'never' => 'Not exported yet.',
    'download' => 'Download the zip',
    'export_heading' => 'Export',
    'scope' => 'Every published page, the public files, a 404 page, the sitemap and redirects for old addresses. Forms and Livewire need PHP and will not work on the static site.',
    'scope_languages' => 'Every published page in every language, the public files, a 404 page, the sitemap and redirects for old addresses. Forms and Livewire need PHP and will not work on the static site.',
    'export' => 'Export',
    'export_zip' => 'Export as zip',
    'exporting' => 'Exporting…',
    'warnings' => 'Pages that need PHP for part of what they do',
    'exported' => 'Exported :pages pages, :files public files and :redirects redirects.',
];
