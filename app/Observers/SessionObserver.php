<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\ConferenceSession;

class SessionObserver
{
    public function created(ConferenceSession $session): void
    {
        $this->logChange($session, 'created');
    }

    public function updated(ConferenceSession $session): void
    {
        if ($session->wasChanged('status')) {
            $this->logChange($session, 'status_changed', [
                'from' => $session->getOriginal('status'),
                'to' => $session->status,
            ]);
        } else {
            $this->logChange($session, 'updated');
        }
    }

    public function deleted(ConferenceSession $session): void
    {
        $this->logChange($session, 'deleted');
    }

    private function logChange(ConferenceSession $session, string $action, array $extra = []): void
    {
        AuditLog::create([
            'auditable_type' => ConferenceSession::class,
            'auditable_id' => $session->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $session->wasChanged() ? collect($session->getOriginal())->only(array_keys($session->getChanges()))->all() : null,
            'new_values' => array_merge($session->getChanges(), $extra),
            'ip_address' => request()->ip(),
        ]);
    }
}
