<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Clock,
    LayoutGrid,
    Lock,
    MapPin,
    Unlock,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { parseCalendarDate } from '@/lib/utils';
import type { Conference, SessionSlot } from '@/types/partner';

defineOptions({ layout: AdminLayout });

interface OccupiedSlot extends SessionSlot {
    is_assignable: boolean;
    claimed_by_session_id: number | null;
    held_by_session_id: number | null;
    claimed_at: string | null;
    held_at: string | null;
    claimed_by_session?: SlotHolder | null;
    held_by_session?: SlotHolder | null;
}

interface SlotHolder {
    id: number;
    title: string;
    status: string;
    partner?: { id: number; organization_name: string } | null;
}

interface Inconsistency {
    slot_id: number;
    kind: string;
    detail: string;
}

const props = defineProps<{
    conference?: Conference | null;
    slots: OccupiedSlot[];
    inconsistencies: Inconsistency[];
}>();

type Occupancy = {
    state: 'available' | 'held' | 'claimed' | 'blocked';
    holder: SlotHolder | null;
    since: string | null;
};

/**
 * Who has this slot. A claim is a booking the programme team has granted; a
 * hold is a partner's pending request, which is the case the board could not
 * show before.
 */
function occupancy(slot: OccupiedSlot): Occupancy {
    if (slot.claimed_by_session_id) {
        return { state: 'claimed', holder: slot.claimed_by_session ?? null, since: slot.claimed_at };
    }

    if (slot.held_by_session_id) {
        return { state: 'held', holder: slot.held_by_session ?? null, since: slot.held_at };
    }

    return { state: slot.is_assignable ? 'available' : 'blocked', holder: null, since: null };
}

const stateLabels: Record<Occupancy['state'], string> = {
    available: 'Available',
    held: 'Held — awaiting the partner',
    claimed: 'Booked',
    blocked: 'Not bookable',
};

const rows = computed(() =>
    props.slots.map((slot) => ({
        slot,
        occupancy: occupancy(slot),
        problem: props.inconsistencies.find((entry) => entry.slot_id === slot.id) ?? null,
    })),
);

const counts = computed(() => ({
    available: rows.value.filter((row) => row.occupancy.state === 'available').length,
    held: rows.value.filter((row) => row.occupancy.state === 'held').length,
    claimed: rows.value.filter((row) => row.occupancy.state === 'claimed').length,
    blocked: rows.value.filter((row) => row.occupancy.state === 'blocked').length,
}));

const showHeldOnly = ref(false);

const visibleRows = computed(() =>
    showHeldOnly.value ? rows.value.filter((row) => row.occupancy.state === 'held') : rows.value,
);

const byDay = computed(() => {
    const groups = new Map<number, typeof visibleRows.value>();

    visibleRows.value.forEach((row) => {
        const bucket = groups.get(row.slot.day_index);

        if (bucket) {
            bucket.push(row);
        } else {
            groups.set(row.slot.day_index, [row]);
        }
    });

    return [...groups.entries()].sort(([a], [b]) => a - b);
});

function dayHeading(index: number, dayRows: typeof visibleRows.value): string {
    const date = dayRows[0]?.slot.date;

    if (!date) {
        return `Day ${index}`;
    }

    return `Day ${index} — ${parseCalendarDate(date).toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    })}`;
}

function since(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const releasing = ref<OccupiedSlot | null>(null);

function release() {
    const slot = releasing.value;

    if (!slot) {
        return;
    }

    router.delete(`/admin/scheduling/slots/${slot.id}`, {
        preserveScroll: true,
        onFinish: () => {
            releasing.value = null;
        },
    });
}
</script>

<template>
    <Head title="Slot Inventory" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold">Slot Inventory</h1>
                <p class="text-sm text-muted-foreground">
                    Every slot in the scheduling matrix and who has it — including partner drafts
                    still holding a slot, which do not appear on the board.
                </p>
            </div>
            <Button variant="outline" as-child>
                <a href="/admin/scheduling">
                    <LayoutGrid class="mr-2 h-4 w-4" />
                    Room Allocation Board
                </a>
            </Button>
        </div>

        <div
            v-if="inconsistencies.length > 0"
            class="rounded-lg border border-destructive/40 bg-destructive/5 p-4"
        >
            <div class="flex items-start gap-3">
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
                <div class="space-y-2 text-sm">
                    <p class="font-medium">
                        {{ inconsistencies.length }}
                        {{ inconsistencies.length === 1 ? 'slot disagrees' : 'slots disagree' }}
                        with the session holding it
                    </p>
                    <ul class="text-muted-foreground list-disc space-y-1 pl-4">
                        <li v-for="problem in inconsistencies" :key="problem.slot_id">
                            {{ problem.detail }}
                        </li>
                    </ul>
                    <p class="text-muted-foreground">
                        Releasing the slot below fixes it, or run
                        <code class="bg-muted rounded px-1 py-0.5 text-xs">php artisan slots:reconcile --fix</code>.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Available</p>
                    <p class="text-2xl font-bold">{{ counts.available }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Held by a draft</p>
                    <p class="text-2xl font-bold">{{ counts.held }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Booked</p>
                    <p class="text-2xl font-bold">{{ counts.claimed }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Not bookable</p>
                    <p class="text-2xl font-bold">{{ counts.blocked }}</p>
                </CardContent>
            </Card>
        </div>

        <div class="flex items-center gap-3">
            <Button
                :variant="showHeldOnly ? 'default' : 'outline'"
                size="sm"
                @click="showHeldOnly = !showHeldOnly"
            >
                <Lock class="mr-2 h-4 w-4" />
                {{ showHeldOnly ? 'Showing held only' : 'Show held only' }}
            </Button>
        </div>

        <Card v-for="[index, dayRows] in byDay" :key="index">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <CalendarClock class="h-4 w-4" />
                    {{ dayHeading(index, dayRows) }}
                </CardTitle>
                <CardDescription>{{ dayRows.length }} slots</CardDescription>
            </CardHeader>
            <CardContent class="space-y-2">
                <div
                    v-for="row in dayRows"
                    :key="row.slot.id"
                    class="flex flex-col gap-3 rounded-md border p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    :class="row.problem ? 'border-destructive/50 bg-destructive/5' : ''"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ row.slot.slot_code }}</span>
                            <Badge
                                v-if="row.occupancy.state === 'available'"
                                variant="outline"
                                class="border-green-500 text-green-700 dark:text-green-400"
                            >
                                <CheckCircle2 class="mr-1 h-3 w-3" />
                                {{ stateLabels.available }}
                            </Badge>
                            <Badge
                                v-else-if="row.occupancy.state === 'held'"
                                variant="outline"
                                class="border-amber-400 text-amber-700 dark:text-amber-400"
                            >
                                <Lock class="mr-1 h-3 w-3" />
                                {{ stateLabels.held }}
                            </Badge>
                            <Badge v-else-if="row.occupancy.state === 'claimed'" variant="secondary">
                                {{ stateLabels.claimed }}
                            </Badge>
                            <Badge v-else variant="outline" class="text-muted-foreground">
                                {{ stateLabels.blocked }}
                            </Badge>
                        </div>

                        <p class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span class="flex items-center gap-1">
                                <Clock class="h-3 w-3" />
                                {{ row.slot.time_label }}
                            </span>
                            <span v-if="row.slot.default_room" class="flex items-center gap-1">
                                <MapPin class="h-3 w-3" />
                                {{ row.slot.default_room.name }}
                            </span>
                            <span v-if="row.slot.track_label">{{ row.slot.track_label }}</span>
                        </p>

                        <p v-if="row.occupancy.holder" class="text-muted-foreground text-xs">
                            {{ row.occupancy.holder.partner?.organization_name ?? 'Unknown partner' }}
                            — “{{ row.occupancy.holder.title }}”
                            <span class="capitalize">({{ row.occupancy.holder.status }})</span>
                            <template v-if="row.occupancy.since"> · since {{ since(row.occupancy.since) }}</template>
                        </p>
                        <p v-else-if="row.occupancy.state !== 'available' && row.occupancy.state !== 'blocked'" class="text-destructive text-xs">
                            Held by a session that can no longer be found.
                        </p>
                    </div>

                    <Button
                        v-if="row.occupancy.state === 'held' || row.occupancy.state === 'claimed'"
                        variant="outline"
                        size="sm"
                        class="shrink-0"
                        @click="releasing = row.slot"
                    >
                        <Unlock class="mr-2 h-4 w-4" />
                        Release
                    </Button>
                </div>
            </CardContent>
        </Card>

        <ConfirmDialog
            :open="releasing !== null"
            title="Release this slot?"
            :description="
                releasing
                    ? `${releasing.slot_code} goes back into the pool and any partner request for it is closed. The session keeps its details but loses this time.`
                    : ''
            "
            confirm-label="Release slot"
            @confirm="release"
            @cancel="releasing = null"
        />
    </div>
</template>
