<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Invoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Confirmed - AHAIC Partner Portal')
            ->greeting('Hello!')
            ->line('Your payment has been confirmed for the AHAIC Partner Portal.')
            ->line('Invoice Number: '.$this->invoice->invoice_number)
            ->line('Amount: '.$this->invoice->currency.' '.number_format($this->invoice->amount, 2))
            ->line('Confirmation Date: '.now()->format('F j, Y'))
            ->line('You can now proceed with the onboarding process to complete your partner setup.')
            ->action('Start Onboarding', url('/partner/onboarding'))
            ->line('Thank you for partnering with AHAIC!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->amount,
        ];
    }
}
