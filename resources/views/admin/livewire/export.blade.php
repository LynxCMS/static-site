<div class="grid max-w-3xl gap-6">
    <section aria-labelledby="static-where" class="bg-card grid gap-2 rounded-xl border p-5">
        <h2 id="static-where" class="font-medium">Where</h2>
        <p class="text-muted-foreground text-sm">Exports to <span class="font-mono">{{ $path }}</span>, for a site served at <span class="font-mono">{{ $url }}</span>. Set both in <span class="font-mono">config/lynx-static.php</span>.</p>
        <p class="text-muted-foreground text-sm">
            @if ($exportedAt) Last export: {{ $exportedAt }}. @else Not exported yet. @endif
            @if ($hasZip) <a href="{{ route('lynx.admin.static.download') }}" class="text-primary underline">Download the zip</a> @endif
        </p>
    </section>

    <section aria-labelledby="static-export" class="bg-card grid gap-4 rounded-xl border p-5">
        <h2 id="static-export" class="font-medium">Export</h2>
        <p class="text-muted-foreground text-sm">Every published page{{ lynx()->languages->isMultilingual() ? ' in every language' : '' }}, the public files, a 404 page, the sitemap and redirects for old addresses. Forms and Livewire need PHP and will not work on the static site.</p>
        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.button type="button" wire:click="export" wire:loading.attr="disabled">Export</x-lynx-admin::ui.button>
            @if ($canZip)
                <x-lynx-admin::ui.button type="button" variant="outline" wire:click="export(true)" wire:loading.attr="disabled">Export as zip</x-lynx-admin::ui.button>
            @endif
            <span wire:loading class="text-muted-foreground text-sm">Exporting…</span>
            @if ($status !== '') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
        @if ($warnings !== [])
            <div class="grid gap-1 text-sm">
                <p class="font-medium">Pages that need PHP for part of what they do</p>
                <ul class="grid gap-1">
                    @foreach ($warnings as $page => $reasons)
                        <li><span class="font-mono">{{ $page }}</span>: <span class="text-muted-foreground">{{ implode(', ', $reasons) }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>
