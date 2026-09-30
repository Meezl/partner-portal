<?php

namespace App\Enums;

/**
 * How a partner is settling their invoice.
 *
 * Both types go to finance for the same approve/reject decision; they differ in
 * what the partner attaches and in what approval means — proof of payment says
 * the money has been sent, a purchase order only promises it.
 */
enum PaymentType: string
{
    case ProofOfPayment = 'proof_of_payment';
    case PurchaseOrder = 'purchase_order';

    public function label(): string
    {
        return match ($this) {
            self::ProofOfPayment => 'Paid now',
            self::PurchaseOrder => 'Pay later (LPO/PO)',
        };
    }

    /** What the partner attaches. */
    public function documentLabel(): string
    {
        return match ($this) {
            self::ProofOfPayment => 'Proof of payment',
            self::PurchaseOrder => 'LPO / purchase order',
        };
    }

    /** What the reference field holds. */
    public function referenceLabel(): string
    {
        return match ($this) {
            self::ProofOfPayment => 'Transaction reference',
            self::PurchaseOrder => 'LPO / PO number',
        };
    }

    /**
     * Whether approving this settles the invoice. A purchase order is a
     * commitment, so the invoice stays open until the money actually arrives.
     */
    public function settlesInvoice(): bool
    {
        return $this === self::ProofOfPayment;
    }

    /**
     * @return array<int, array{value: string, label: string, document: string, reference: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'document' => $case->documentLabel(),
            'reference' => $case->referenceLabel(),
        ], self::cases());
    }
}
