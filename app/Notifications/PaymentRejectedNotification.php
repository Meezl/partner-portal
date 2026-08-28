<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A rejected payment leaves the partner blocked, so they have to be told —
 * otherwise they simply wait for a confirmation that will never arrive.
 */
class PaymentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Payment $payment,
        protected ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->payment->invoice;

        $mail = (new MailMessage)
            ->subject('Action needed: we could not verify your payment')
            ->greeting('Hello')
            ->line('We were unable to verify the payment you submitted, so it has not been accepted.');

        if ($invoice) {
            $mail->line('Invoice: '.$invoice->invoice_number)
                ->line('Amount: '.$invoice->currency.' '.number_format((float) $this->payment->amount, 2));
        }

        if ($this->reason) {
            $mail->line('Reason: '.$this->reason);
        }

        return $mail
            ->action('Submit payment details again', url('/partner/payment'))
            ->line('If you believe this is a mistake, reply to this email and our finance team will help.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'reason' => $this->reason,
        ];
    }
}
