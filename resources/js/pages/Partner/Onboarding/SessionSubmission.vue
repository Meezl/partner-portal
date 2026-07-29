<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, CalendarDays } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, ConferenceSession } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

defineProps<{
    partner: Partner;
    sessions: ConferenceSession[];
}>();

const deletingSession = ref<ConferenceSession | null>(null);

function deleteSession(session: ConferenceSession) {
    deletingSession.value = null;
    router.delete(`/partner/sessions/${session.id}`);
}

function formatLabel(format: string) {
    return format.charAt(0).toUpperCase() + format.slice(1).replace(/_/g, ' ');
}
</script>

<template>
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading text-3xl font-bold tracking-tight">Session Submissions</h1>
                <p class="text-muted-foreground mt-1">Manage your conference sessions.</p>
            </div>
            <Link href="/partner/sessions/create">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Add Session
                </Button>
            </Link>
        </div>

        <div v-if="sessions.length === 0">
            <Card>
                <CardContent class="flex flex-col items-center gap-4 py-12">
                    <CalendarDays class="text-muted-foreground h-12 w-12" />
                    <div class="text-center">
                        <p class="font-medium">No sessions yet</p>
                        <p class="text-muted-foreground mt-1 text-sm">Create your first conference session to get started.</p>
                    </div>
                    <Link href="/partner/sessions/create">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Create Session
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>

        <div v-else class="space-y-4">
            <Card v-for="session in sessions" :key="session.id">
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-base">{{ session.title }}</CardTitle>
                            <CardDescription>
                                <Badge variant="outline" class="mr-2">{{ formatLabel(session.format) }}</Badge>
                                <StatusBadge :status="session.status" />
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="`/partner/sessions/${session.id}/edit`">
                                <Button variant="outline" size="sm">
                                    <Pencil class="mr-1 h-4 w-4" />
                                    Edit
                                </Button>
                            </Link>
                            <Button variant="outline" size="sm" class="text-red-600 hover:text-red-700" @click="deletingSession = session">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent v-if="session.description">
                    <p class="text-muted-foreground text-sm line-clamp-2">{{ session.description }}</p>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            :open="deletingSession !== null"
            title="Delete Session"
            description="Are you sure you want to delete this session? This action cannot be undone."
            confirm-label="Delete Session"
            variant="destructive"
            @confirm="deletingSession && deleteSession(deletingSession)"
            @cancel="deletingSession = null"
        />
    </div>
</template>
