<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Confirmación de tu pedido '.$this->order->number)
            ->greeting('¡Gracias por tu compra, '.$this->order->customer_name.'!')
            ->line('Hemos registrado tu pedido con número **'.$this->order->number.'**.')
            ->line('Estado actual: **'.ucfirst($this->order->status).'**')
            ->line('Detalles del pedido:');

        foreach ($this->order->items as $item) {
            $mail->line(
                "{$item->quantity} × {$item->product_name} — ".
                number_format((float) $item->total, 2, ',', '.').' €'
            );
        }

        $mail->line('Subtotal: '.number_format((float) $this->order->subtotal, 2, ',', '.').' €')
             ->line('Envío: '.((float) $this->order->shipping > 0
                 ? number_format((float) $this->order->shipping, 2, ',', '.').' €'
                 : 'Gratis'))
             ->line('Total: '.number_format((float) $this->order->total, 2, ',', '.').' €')
             ->action('Ver mis pedidos', url('/mis-pedidos'))
             ->line('Te avisaremos cuando el pedido sea enviado.')
             ->salutation('Saludos, el equipo de MODA');

        return $mail;
    }
}
