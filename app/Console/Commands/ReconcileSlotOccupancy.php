<?php

namespace App\Console\Commands;

use App\Models\Conference;
use App\Models\SessionSlot;
use App\Services\SlotOccupancyReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Reports — and optionally fixes — slots that read as taken while nothing
 * accounts for them, which is what a partner sees as "This slot was just taken
 * by another partner" on a board that shows the slot as free.
 */
class ReconcileSlotOccupancy extends Command
{
    protected $signature = 'slots:reconcile
        {--conference= : Limit to one conference id (default: the active one)}
        {--fix : Release the broken slots instead of only listing them}
        {--stale-holds= : Also list holds older than this many days}
        {--release-stale : Release those stale holds too (needs --fix and --stale-holds)}';

    protected $description = 'Find slots whose occupancy disagrees with the sessions holding them';

    public function handle(SlotOccupancyReconciler $reconciler): int
    {
        $conferenceId = $this->conferenceId();

        $problems = $reconciler->problems($conferenceId);
        $this->render('Inconsistent slots', $problems, 'Every slot agrees with the session holding it.');

        $staleDays = $this->option('stale-holds');
        $stale = $staleDays === null
            ? new Collection
            : $reconciler->staleHolds((int) $staleDays, $conferenceId);

        if ($staleDays !== null) {
            $this->newLine();
            $this->render(
                sprintf('Holds older than %d day(s)', (int) $staleDays),
                $stale,
                'No hold has been sitting that long.',
            );
        }

        if (! $this->option('fix')) {
            if ($problems->isNotEmpty() || $stale->isNotEmpty()) {
                $this->newLine();
                $this->comment('Nothing was changed. Re-run with --fix to release these slots.');
            }

            return self::SUCCESS;
        }

        $released = $reconciler->resolve($problems);

        // Stale holds are real reservations, not corruption, so releasing them
        // takes a slot away from a partner who may still be working on it —
        // never bundled into --fix on its own.
        if ($this->option('release-stale')) {
            $released += $reconciler->resolve($stale);
        }

        $this->newLine();
        $this->info(sprintf('Released %d slot(s).', $released));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array{slot: SessionSlot, kind: string, detail: string}>  $rows
     */
    private function render(string $heading, Collection $rows, string $emptyMessage): void
    {
        $this->line("<options=bold>{$heading}</>");

        if ($rows->isEmpty()) {
            $this->info($emptyMessage);

            return;
        }

        $this->table(
            ['Slot', 'Day', 'Time', 'Problem', 'Detail'],
            $rows->map(fn (array $row) => [
                $row['slot']->slot_code,
                $row['slot']->date?->format('D j M') ?? ('Day '.$row['slot']->day_index),
                $row['slot']->time_label,
                $row['kind'],
                $row['detail'],
            ])->all(),
        );
    }

    private function conferenceId(): ?int
    {
        if ($this->option('conference')) {
            return (int) $this->option('conference');
        }

        return Conference::where('status', 'active')->latest()->value('id');
    }
}
