<?php

namespace App\Livewire\Layout;

use App\Models\Notificacion;
use Livewire\Component;

class NotificationBell extends Component
{
    public function markAllAsRead(): void
    {
        Notificacion::query()->where('usuario_id', auth()->id())->where('leida', false)->update(['leida' => true]);
    }

    public function render()
    {
        return view('livewire.layout.notification-bell', [
            'notificaciones' => Notificacion::query()->where('usuario_id', auth()->id())->latest()->take(8)->get(),
            'pendientes' => Notificacion::query()->where('usuario_id', auth()->id())->where('leida', false)->count(),
        ]);
    }
}