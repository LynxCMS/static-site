<div class="grid max-w-3xl gap-6">
    <section aria-labelledby="static-where" class="bg-card grid gap-2 rounded-xl border p-5">
        <h2 id="static-where" class="font-medium">{{ __('lynx-static::admin.where') }}</h2>
        <p class="text-muted-foreground text-sm">@php($mono = fn (string $text): string => '<span class="font-mono">'.e($text).'</span>')
            {!! __('lynx-static::admin.where_help', ['path' => $mono($path), 'url' => $mono($url), 'config' => $mono('config/lynx-static.php')]) !!}</p>
        <p class="text-muted-foreground text-sm">
            {{ $exportedAt ? __('lynx-static::admin.last', ['when' => $exportedAt]) : __('lynx-static::admin.never') }}
            @if ($hasZip) <a href="{{ route('lynx.admin.static.download') }}" class="text-primary underline">{{ __('lynx-static::admin.download') }}</a> @endif
        </p>
    </section>

    <section aria-labelledby="static-export" class="bg-card grid gap-4 rounded-xl border p-5">
        <h2 id="static-export" class="font-medium">{{ __('lynx-static::admin.export_heading') }}</h2>
        <p class="text-muted-foreground text-sm">{{ __(lynx()->languages->isMultilingual() ? 'lynx-static::admin.scope_languages' : 'lynx-static::admin.scope') }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.button type="button" wire:click="export" wire:loading.attr="disabled">{{ __('lynx-static::admin.export') }}</x-lynx-admin::ui.button>
            @if ($canZip)
                <x-lynx-admin::ui.button type="button" variant="outline" wire:click="export(true)" wire:loading.attr="disabled">{{ __('lynx-static::admin.export_zip') }}</x-lynx-admin::ui.button>
            @endif
            <span wire:loading class="text-muted-foreground text-sm">{{ __('lynx-static::admin.exporting') }}</span>
            @if ($status !== '') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
        @if ($warnings !== [])
            <div class="grid gap-1 text-sm">
                <p class="font-medium">{{ __('lynx-static::admin.warnings') }}</p>
                <ul class="grid gap-1">
                    @foreach ($warnings as $page => $reasons)
                        <li><span class="font-mono">{{ $page }}</span>: <span class="text-muted-foreground">{{ implode(', ', $reasons) }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>
