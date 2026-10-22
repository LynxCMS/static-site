<?php

declare(strict_types=1);

return [
    // The folder the site is exported to. It is replaced on each export, so Lynx only empties a
    // folder it made itself (it holds a .lynx-static file) or an empty one.
    'path' => env('LYNX_STATIC_PATH', base_path('dist')),

    // The address the static site is served at. Links, canonical tags and the sitemap use it.
    // Empty uses app.url.
    'url' => env('LYNX_STATIC_URL'),

    // The environment pages render in. Production by default, so an export made on a local or
    // staging install is indexed (lynx-seo blocks robots elsewhere) and shows no debug output.
    // Null renders in the app's own environment.
    'environment' => env('LYNX_STATIC_ENVIRONMENT', 'production'),

    // Extra addresses to export besides the pages, written as the files they answer with. Those
    // that answer with something other than 200 are left out. A sitemap index's parts follow.
    'paths' => ['/sitemap.xml', '/robots.txt'],

    // Files and folders in public/ that are not copied, as paths relative to public/ (fnmatch
    // patterns). PHP entry points and the page cache's own files never work without PHP.
    'exclude' => ['index.php', 'index-hook.php', '.htaccess', 'hot', 'lynx-cache', 'web.config', '*.gitignore'],

    // Old addresses of renamed and moved pages: a _redirects file (Netlify, Cloudflare Pages) and
    // a small HTML page at each old address that sends visitors on (any host, GitHub Pages too).
    'redirects' => [
        'file' => true,
        'pages' => true,
    ],
];
