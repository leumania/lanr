<x-layouts::app :title="__('Trámite ' . $tramite->tracking)">
    <livewire:tramites.request-detail :tramite="$tramite" />
</x-layouts::app>