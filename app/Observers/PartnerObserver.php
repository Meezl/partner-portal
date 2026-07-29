<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Partner;

class PartnerObserver
{
    public function created(Partner $partner): void
    {
        $this->logChange($partner, 'created');
    }

    public function updated(Partner $partner): void
    {
        if ($partner->wasChanged('status')) {
            $this->logChange($partner, 'status_changed', [
                'from' => $partner->getOriginal('status'),
                'to' => $partner->status,
            ]);
        } else {
            $this->logChange($partner, 'updated');
        }
    }

    private function logChange(Partner $partner, string $action, array $extra = []): void
    {
        AuditLog::create([
            'auditable_type' => Partner::class,
            'auditable_id' => $partner->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $partner->wasChanged() ? collect($partner->getOriginal())->only($partner->getChanges())->all() : null,
            'new_values' => array_merge($partner->getChanges(), $extra),
            'ip_address' => request()->ip(),
        ]);
    }
}
