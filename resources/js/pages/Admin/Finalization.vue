<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { CheckCircle, XCircle, Lock, Send } from 'lucide-vue-next';
import { computed } from 'vue';
import BlockedActionHint from '@/components/shared/BlockedActionHint.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    checklist: {
        allPartnersSubmitted: boolean;
        allSessionsScheduled: boolean;
        noUnresolvedConflicts: boolean;
        conflictCount: number;
        unsubmittedPartners: number;
        unscheduledSessions: number;
    };
}>();

const allReady = props.checklist.allPartnersSubmitted && props.checklist.allSessionsScheduled && props.checklist.noUnresolvedConflicts;

/** Which checklist items are still failing, so the reason is not a guess. */
const lockBlockers = computed(() => {
    const blockers: string[] = [];

    if (!props.checklist.allPartnersSubmitted) {
        blockers.push('Not every partner has submitted yet.');
    }

    if (!props.checklist.allSessionsScheduled) {
        blockers.push('Some sessions still have no room and time.');
    }

    if (!props.checklist.noUnresolvedConflicts) {
        blockers.push('There are unresolved scheduling conflicts.');
    }

    return blockers;
});

const lockAll = () => {
    if (confirm('Are you sure? This will lock all partner data and sessions.')) {
        router.post('/admin/finalization/lock');
    }
};

const publishAgenda = () => {
    if (confirm('Publish the finalized agenda?')) {
        router.post('/admin/finalization/publish');
    }
};
</script>

<template>
    <Head title="Finalization" />

    <div class="space-y-6 p-6">
        <h1 class="font-heading text-2xl">Conference Finalization</h1>

        <Card>
            <CardHeader>
                <CardTitle>Pre-Finalization Checklist</CardTitle>
                <CardDescription>All conditions must be met before locking the conference</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center gap-3">
                    <CheckCircle v-if="checklist.allPartnersSubmitted" class="h-5 w-5 text-green-600" />
                    <XCircle v-else class="h-5 w-5 text-red-500" />
                    <span>All partners have submitted their materials</span>
                    <span v-if="!checklist.allPartnersSubmitted" class="text-sm text-muted-foreground">({{ checklist.unsubmittedPartners }} remaining)</span>
                </div>

                <div class="flex items-center gap-3">
                    <CheckCircle v-if="checklist.allSessionsScheduled" class="h-5 w-5 text-green-600" />
                    <XCircle v-else class="h-5 w-5 text-red-500" />
                    <span>All sessions have been scheduled</span>
                    <span v-if="!checklist.allSessionsScheduled" class="text-sm text-muted-foreground">({{ checklist.unscheduledSessions }} unscheduled)</span>
                </div>

                <div class="flex items-center gap-3">
                    <CheckCircle v-if="checklist.noUnresolvedConflicts" class="h-5 w-5 text-green-600" />
                    <XCircle v-else class="h-5 w-5 text-red-500" />
                    <span>No unresolved scheduling conflicts</span>
                    <span v-if="!checklist.noUnresolvedConflicts" class="text-sm text-muted-foreground">({{ checklist.conflictCount }} conflicts)</span>
                </div>
            </CardContent>
        </Card>

        <BlockedActionHint :reasons="lockBlockers" class="mb-4" />

        <div class="flex gap-4">
            <Button @click="lockAll" :disabled="!allReady" size="lg">
                <Lock class="mr-2 h-4 w-4" /> Lock All & Finalize
            </Button>
            <Button @click="publishAgenda" variant="outline" size="lg">
                <Send class="mr-2 h-4 w-4" /> Publish Agenda
            </Button>
        </div>
    </div>
</template>
