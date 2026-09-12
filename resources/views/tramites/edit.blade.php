<x-layouts::app :title="__('Editar trámite ' . $tramite->tracking)">
    <livewire:tramites.edit-request :tramite="$tramite" />
</x-layouts::app>