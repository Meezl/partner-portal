<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, AlertCircle } from 'lucide-vue-next';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { ChangeRequest } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    changeRequests: ChangeRequest[];
}>();

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatType(type: string) {
    return type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ');
}
</script>

<template>
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading text-3xl font-bold tracking-tight">Change Requests</h1>
                <p class="text-muted-foreground mt-1">Track and manage your schedule change requests.</p>
            </div>
            <Link href="/partner/change-requests/create">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    New Request
                </Button>
            </Link>
        </div>

        <Card v-if="changeRequests.length === 0">
            <CardContent class="flex flex-col items-center gap-4 py-12">
                <AlertCircle class="text-muted-foreground h-12 w-12" />
                <div class="text-center">
                    <p class="font-medium">No change requests</p>
                    <p class="text-muted-foreground mt-1 text-sm">You have not submitted any change requests yet.</p>
                </div>
                <Link href="/partner/change-requests/create">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Create Request
                    </Button>
                </Link>
            </CardContent>
        </Card>

        <Card v-else>
            <CardHeader>
                <CardTitle>All Requests</CardTitle>
                <CardDescription>{{ changeRequests.length }} change request(s)</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-muted-foreground px-4 py-3 text-left font-medium">Session</th>
                                <th class="text-muted-foreground px-4 py-3 text-left font-medium">Type</th>
                                <th class="text-muted-foreground px-4 py-3 text-left font-medium">Status</th>
                                <th class="text-muted-foreground px-4 py-3 text-left font-medium">Submitted</th>
                                <th class="text-muted-foreground px-4 py-3 text-left font-medium">Resolution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cr in changeRequests" :key="cr.id" class="hover:bg-muted/50 border-b transition-colors">
                                <td class="px-4 py-3 font-medium">{{ cr.session?.title ?? 'Session #' + cr.conference_session_id }}</td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline">{{ formatType(cr.type) }}</Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <StatusBadge :status="cr.status" />
                                </td>
                                <td class="text-muted-foreground px-4 py-3">
                                    {{ cr.reviewed_at ? formatDate(cr.reviewed_at) : 'Pending' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="cr.resolution_notes" class="text-muted-foreground text-xs">{{ cr.resolution_notes }}</span>
                                    <span v-else class="text-muted-foreground text-xs">--</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
