<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, Ban, Building2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

type Booth = {
    id: number;
    zone: string;
    booth_number: string;
    size: string;
    status: 'available' | 'reserved' | 'assigned' | 'blocked';
    partner_id: number | null;
    notes: string | null;
    partner?: { id: number; organization_name: string; slug: string } | null;
};

type PartnerRef = { id: number; organization_name: string; slug: string };

const props = defineProps<{
    conference: { id: number; name: string } | null;
    boothsByZone: Record<string, Booth[]>;
    partners: PartnerRef[];
    summary: { total: number; available: number; reserved: number; assigned: number; blocked: number };
}>();

const STATUS_STYLE: Record<Booth['status'], string> = {
    available: 'bg-emerald-50 border-emerald-300 hover:border-emerald-500 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100',
    reserved:  'bg-amber-50 border-amber-300 hover:border-amber-500 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100',
    assigned:  'bg-primary/10 border-primary hover:border-primary text-primary-foreground dark:bg-primary/20 text-primary',
    blocked:   'bg-muted border-muted-foreground/30 text-muted-foreground line-through',
};

const STATUS_LABEL: Record<Booth['status'], string> = {
    available: 'Available',
    reserved:  'Reserved',
    assigned:  'Assigned',
    blocked:   'Blocked',
};

const showDialog = ref(false);
const editing = ref<Booth | null>(null);

const form = useForm({
    status: 'available' as Booth['status'],
    partner_id: null as number | null,
    notes: '',
});

function open(booth: Booth) {
    editing.value = booth;
    form.clearErrors();
    form.status = booth.status;
    form.partner_id = booth.partner_id;
    form.notes = booth.notes ?? '';
    showDialog.value = true;
}

function submit() {
    if (!editing.value) return;
    form.put(`/admin/booths/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { showDialog.value = false; },
    });
}

function release() {
    form.status = 'available';
    form.partner_id = null;
    submit();
}

const zones = computed(() => Object.keys(props.boothsByZone).sort());

const utilisationPct = computed(() => {
    if (!props.summary.total) return 0;
    return Math.round(((props.summary.assigned + props.summary.reserved) / props.summary.total) * 100);
});

const partnerNeedsSelection = computed(() => form.status === 'assigned' || form.status === 'reserved');
</script>

<template>
    <Head title="Booths" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold">Booth allocation</h1>
                <p class="text-muted-foreground text-sm">
                    {{ conference?.name ?? 'No active conference' }} · {{ summary.total }} booths
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <Badge variant="outline" class="border-emerald-400 text-emerald-700 dark:text-emerald-300">
                    <CheckCircle2 class="mr-1 h-3 w-3" /> {{ summary.available }} available
                </Badge>
                <Badge variant="outline" class="border-amber-400 text-amber-700 dark:text-amber-300">
                    {{ summary.reserved }} reserved
                </Badge>
                <Badge variant="secondary">
                    {{ summary.assigned }} assigned
                </Badge>
                <Badge variant="outline" class="text-muted-foreground">
                    <Ban class="mr-1 h-3 w-3" /> {{ summary.blocked }} blocked
                </Badge>
            </div>
        </div>

        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Overall utilisation</CardTitle>
                <CardDescription>Assigned + reserved as % of the total booth inventory.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="bg-muted h-3 w-full overflow-hidden rounded-full">
                    <div class="bg-primary h-full transition-all" :style="{ width: `${utilisationPct}%` }" />
                </div>
                <p class="text-muted-foreground mt-2 text-xs">{{ utilisationPct }}% booked</p>
            </CardContent>
        </Card>

        <Card v-for="zone in zones" :key="zone">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base">
                    <Building2 class="h-4 w-4" /> {{ zone }}
                </CardTitle>
                <CardDescription>
                    Booths #{{ boothsByZone[zone][0]?.booth_number }}–#{{ boothsByZone[zone][boothsByZone[zone].length - 1]?.booth_number }} · {{ boothsByZone[zone][0]?.size ?? '3x3' }} standard
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10">
                    <button
                        v-for="booth in boothsByZone[zone]"
                        :key="booth.id"
                        type="button"
                        class="flex flex-col items-start gap-0.5 rounded-md border-2 p-2 text-left text-xs transition"
                        :class="STATUS_STYLE[booth.status]"
                        @click="open(booth)"
                    >
                        <div class="font-semibold">#{{ booth.booth_number }}</div>
                        <div class="line-clamp-2 text-[11px] opacity-80">
                            {{ booth.partner?.organization_name ?? STATUS_LABEL[booth.status] }}
                        </div>
                    </button>
                </div>
            </CardContent>
        </Card>

        <div v-if="!zones.length" class="text-muted-foreground rounded-md border p-6 text-center">
            No booths seeded for this conference yet.
        </div>

        <!-- Booth edit dialog -->
        <Dialog :open="showDialog" @update:open="showDialog = $event">
            <DialogContent v-if="editing" class="bg-background sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Booth #{{ editing.booth_number }} — {{ editing.zone }}</DialogTitle>
                    <DialogDescription>
                        Assign or update the booking status for this booth.
                    </DialogDescription>
                </DialogHeader>

                <form class="flex flex-col gap-4 py-2" @submit.prevent="submit">
                    <div class="flex flex-col gap-1.5">
                        <Label>Status</Label>
                        <Select :model-value="form.status" @update:model-value="form.status = $event as Booth['status']">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="available">Available</SelectItem>
                                <SelectItem value="reserved">Reserved</SelectItem>
                                <SelectItem value="assigned">Assigned</SelectItem>
                                <SelectItem value="blocked">Blocked</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div v-if="partnerNeedsSelection" class="flex flex-col gap-1.5">
                        <Label>Partner</Label>
                        <Select
                            :model-value="form.partner_id ? String(form.partner_id) : ''"
                            @update:model-value="form.partner_id = $event ? Number($event) : null"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pick partner organization" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in partners" :key="p.id" :value="String(p.id)">
                                    {{ p.organization_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.partner_id" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="notes">Notes</Label>
                        <Input id="notes" v-model="form.notes" class="bg-background" placeholder="Optional context" autocomplete="off" />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <DialogFooter class="justify-between pt-2 sm:justify-between">
                        <Button
                            v-if="editing.status !== 'available'"
                            type="button"
                            variant="ghost"
                            class="text-destructive"
                            @click="release"
                        >
                            <XCircle class="mr-1 h-4 w-4" /> Release
                        </Button>
                        <span v-else />
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" @click="showDialog = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">Save</Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
