<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public \App\Models\Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $partnerName = $this->payment->partner->company_name ?? 'A Partner';
        $invoiceNumber = $this->payment->invoice->invoice_number ?? 'N/A';

        return (new MailMessage)
            ->subject('New Proof of Payment Submitted')
            ->greeting('Hello Finance Team,')
            ->line("A new proof of payment has been submitted by {$partnerName}.")
            ->line("Invoice Number: {$invoiceNumber}")
            ->line("Amount: {$this->payment->currency} " . number_format($this->payment->amount, 2))
            ->action('Review Payment', url('/admin/finance/payments'))
            ->line('Please review the submission in the portal.');
    }

    public function toArray(object $notifiable): array
    {
        $partnerName = $this->payment->partner->company_name ?? 'A Partner';

        return [
            'payment_id' => $this->payment->id,
            'message' => "New proof of payment submitted by {$partnerName}.",
        ];
    }
}
