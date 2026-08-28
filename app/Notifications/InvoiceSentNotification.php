<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceSentNotification extends Notification
{
    use Queueable;

    protected $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/partner/invoices');
        $message = (new MailMessage)
            ->subject('Your AHAIC 2027 Sponsorship Invoice '.$this->invoice->invoice_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your signed partnership agreement has been received and your invoice is now ready.')
            ->line('Invoice Number: '.$this->invoice->invoice_number)
            ->line('Customer Code: '.($this->invoice->customer_code ?: 'N/A'))
            ->line('Amount Due: '.$this->invoice->currency.' '.number_format($this->invoice->amount, 2))
            ->line('Due Date: '.$this->invoice->due_date->format('F j, Y'))
            ->line('Please follow the payment instructions on the attached invoice or in the portal.')
            ->action('View Invoice', $url)
            ->line('If you have any questions, please contact '.config('ahaic.finance_email', 'finance@ahaic.org').'.');

        foreach (config('ahaic.team_emails', []) as $email) {
            $message->cc($email);
        }

        if ($this->invoice->document_path) {
            // fromStorageDisk reads through the filesystem, so this works
            // whether the invoice lives on the local disk or in S3.
            $message->attach(
                Attachment::fromStorageDisk(
                    config('ahaic.disks.private'),
                    $this->invoice->document_path,
                )->as($this->invoice->invoice_number.'.pdf')->withMime('application/pdf'),
            );
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
        ];
    }
}
