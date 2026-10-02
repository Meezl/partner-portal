<?php

namespace App\Notifications;

use App\Enums\PaymentType;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms to the partner that what they uploaded arrived and is with finance.
 *
 * The existing PaymentSubmittedNotification tells finance there is something to
 * review; this is the partner's own receipt, so they are not left wondering
 * whether the upload worked.
 */
class PaymentDocumentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payingLater = $this->payment->payment_type === PaymentType::PurchaseOrder;
        $invoiceNumber = $this->payment->invoice->invoice_number ?? null;

        $mail = (new MailMessage)
            ->subject($payingLater
                ? 'We have received your purchase order'
                : 'We have received your proof of payment')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($payingLater
                ? 'Thank you — your Local Purchase Order (LPO/PO) has been received.'
                : 'Thank you — your proof of payment has been received.');

        if ($invoiceNumber) {
            $mail->line('Invoice: '.$invoiceNumber);
        }

        $mail->line('Amount: '.$this->payment->currency.' '.number_format($this->payment->amount, 2));

        if ($payingLater) {
            $mail->line('All payments must be completed before the conference.');
        }

        return $mail
            ->line('Your payment documentation will be reviewed by the AHAIC Finance Team. You will then receive confirmation once your payment is finalized.')
            ->action('View your invoices', url('/partner/invoices'));
    }

    public function toArray(object $notifiable): array
    {
        return ['payment_id' => $this->payment->id];
    }
}
