<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Wrench, Trash2, Sparkles } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { ResourceAssignment, SessionSchedule, ConferenceSession } from '@/types/partner';

defineOptions({ layout: AdminLayout });

type PoolUser = { id: number; name: string; email: string; role: string };

const props = defineProps<{
    assignments: (ResourceAssignment & { schedule?: SessionSchedule & { session?: ConferenceSession } })[];
    sessions: ConferenceSession[];
    pool?: { session_leads: PoolUser[]; rapporteurs: PoolUser[] };
}>();

const autoAssigning = ref(false);

function runAutoAssign() {
    if (!confirm('Auto-assign 1 session lead + 2 rapporteurs to every scheduled session? Existing manual assignments will be kept.')) {
        return;
    }
    autoAssigning.value = true;
    router.post('/admin/resources/auto-assign', {}, {
        preserveScroll: true,
        onFinish: () => { autoAssigning.value = false; },
    });
}

const showAssignDialog = ref(false);
const filterType = ref('all');

const form = useForm({
    session_schedule_id: '' as string,
    resource_type: '' as string,
    name: '',
    email: '',
});

const selectedUserId = ref<string>('');

const eligibleUsers = computed<PoolUser[]>(() => {
    const p = props.pool;
    if (!p) return [];
    if (form.resource_type === 'session_lead') return p.session_leads;
    if (form.resource_type === 'rapporteur') return p.rapporteurs;
    return [...p.session_leads, ...p.rapporteurs];
});

function onPersonPicked(userId: string) {
    selectedUserId.value = userId;
    const u = eligibleUsers.value.find((x) => String(x.id) === userId);
    if (u) {
        form.name = u.name;
        form.email = u.email;
    }
}

const filteredAssignments = computed(() => {
    if (filterType.value === 'all') {
return props.assignments;
}

    return props.assignments.filter((a) => a.resource_type === filterType.value);
});

const resourceTypes = computed(() => {
    const types = new Set(props.assignments.map((a) => a.resource_type));

    return Array.from(types);
});

function openAssignDialog() {
    form.reset();
    selectedUserId.value = '';
    showAssignDialog.value = true;
}

function submitAssignment() {
    form.post('/admin/resources', {
        onSuccess: () => {
 showAssignDialog.value = false; 
},
    });
}

function removeAssignment(id: number) {
    if (confirm('Remove this resource assignment?')) {
        form.delete(`/admin/resources/${id}`);
    }
}

const columns = [
    { key: 'session', label: 'Session' },
    { key: 'resource_type', label: 'Resource Type' },
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
];
</script>

<template>
    <Head title="Resources" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold">Resource Assignments</h1>
                <p v-if="pool" class="text-muted-foreground text-sm">
                    Pool: {{ pool.session_leads.length }} session leads, {{ pool.rapporteurs.length }} rapporteurs
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" :disabled="autoAssigning" @click="runAutoAssign">
                    <Sparkles class="mr-2 h-4 w-4" />
                    {{ autoAssigning ? 'Assigning…' : 'Auto-assign (1 lead + 2 rapporteurs)' }}
                </Button>
                <Button @click="openAssignDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Assign Resource
                </Button>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-3">
            <Select v-model="filterType">
                <SelectTrigger class="w-[200px]">
                    <SelectValue placeholder="Filter by type" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Types</SelectItem>
                    <SelectItem
                        v-for="type in resourceTypes"
                        :key="type"
                        :value="type"
                    >
                        {{ type }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Badge variant="secondary">
                {{ filteredAssignments.length }} assignment{{ filteredAssignments.length !== 1 ? 's' : '' }}
            </Badge>
        </div>

        <DataTable :columns="columns" :data="filteredAssignments" empty-message="No resource assignments found.">
            <template #session="{ item }">
                <span class="font-medium">
                    {{ item.schedule?.session?.title ?? '---' }}
                </span>
            </template>
            <template #resource_type="{ item }">
                <Badge variant="outline" class="capitalize">
                    <Wrench class="mr-1 h-3 w-3" />
                    {{ item.resource_type }}
                </Badge>
            </template>
            <template #actions="{ item }">
                <Button variant="ghost" size="sm" class="text-destructive" @click="removeAssignment(item.id)">
                    <Trash2 class="h-4 w-4" />
                </Button>
            </template>
        </DataTable>

        <!-- Assign Resource Dialog -->
        <Dialog :open="showAssignDialog" @update:open="showAssignDialog = $event">
            <DialogContent class="bg-background sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Assign Resource</DialogTitle>
                    <DialogDescription>
                        Assign a resource person to a scheduled session.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-4 py-2">
                    <div class="flex flex-col gap-1.5">
                        <Label>Session</Label>
                        <Select v-model="form.session_schedule_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a session" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="session in sessions"
                                    :key="session.id"
                                    :value="String(session.schedule?.id || session.id)"
                                >
                                    {{ session.title }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label>Resource Type</Label>
                        <Select v-model="form.resource_type">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="session_lead">Session Lead</SelectItem>
                                <SelectItem value="rapporteur">Rapporteur</SelectItem>
                                <SelectItem value="moderator">Moderator</SelectItem>
                                <SelectItem value="technician">Technician</SelectItem>
                                <SelectItem value="photographer">Photographer</SelectItem>
                                <SelectItem value="interpreter">Interpreter</SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="eligibleUsers.length" class="flex flex-col gap-1.5">
                        <Label>Person <span class="text-muted-foreground text-xs font-normal">(from pool)</span></Label>
                        <Select :model-value="selectedUserId" @update:model-value="onPersonPicked($event as string)">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pick from rapporteur & session-lead pool" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in eligibleUsers" :key="u.id" :value="String(u.id)">
                                    {{ u.name }} — {{ u.email }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="res-name">Name</Label>
                        <Input id="res-name" v-model="form.name" class="bg-background" placeholder="Full name" autocomplete="off" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="res-email">Email</Label>
                        <Input id="res-email" v-model="form.email" class="bg-background" type="email" placeholder="email@example.com" autocomplete="off" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showAssignDialog = false">Cancel</Button>
                    <Button
                        :disabled="!form.session_schedule_id || !form.resource_type || !form.name || form.processing"
                        @click="submitAssignment"
                    >
                        Assign
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
