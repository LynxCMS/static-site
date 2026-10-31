<x-lynx-admin::layout :title="__('lynx-static::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-static::admin.title')" :description="__('lynx-static::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-static.export />
</x-lynx-admin::layout>
