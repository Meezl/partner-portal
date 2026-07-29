<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Pencil, Trash2, Package } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
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
import { Separator } from '@/components/ui/separator';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Conference, SponsorshipPackage, PackageTier } from '@/types/partner';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    conference: Conference;
    packages: SponsorshipPackage[];
}>();

// Conference form
const form = useForm({
    name: props.conference.name,
    year: props.conference.year,
    start_date: props.conference.start_date,
    end_date: props.conference.end_date,
    venue: props.conference.venue,
    description: props.conference.description || '',
    registration_deadline: props.conference.registration_deadline || '',
    onboarding_deadline: props.conference.onboarding_deadline || '',
    lock_date: props.conference.lock_date || '',
});

function submitConference() {
    form.put(`/admin/conferences/${props.conference.id}`);
}

// Package dialog
const showPackageDialog = ref(false);
const editingPackage = ref<SponsorshipPackage | null>(null);
const deletingPackageId = ref<number | null>(null);

const packageForm = useForm({
    name: '',
    tier: 'silver' as PackageTier,
    price: 0,
    currency: 'USD',
    description: '',
    benefits: '',
    session_slots: 1,
    exhibition_space: '',
    max_partners: null as number | null,
});

function openAddPackage() {
    editingPackage.value = null;
    packageForm.reset();
    packageForm.tier = 'silver';
    packageForm.currency = 'USD';
    packageForm.session_slots = 1;
    showPackageDialog.value = true;
}

function openEditPackage(pkg: SponsorshipPackage) {
    editingPackage.value = pkg;
    packageForm.name = pkg.name;
    packageForm.tier = pkg.tier;
    packageForm.price = pkg.price;
    packageForm.currency = pkg.currency;
    packageForm.description = pkg.description || '';
    packageForm.benefits = pkg.benefits?.join('\n') || '';
    packageForm.session_slots = pkg.session_slots;
    packageForm.exhibition_space = pkg.exhibition_space || '';
    packageForm.max_partners = pkg.max_partners;
    showPackageDialog.value = true;
}

function submitPackage() {
    if (editingPackage.value) {
        packageForm.put(`/admin/conferences/${props.conference.id}/packages/${editingPackage.value.id}`, {
            onSuccess: () => {
 showPackageDialog.value = false; 
},
        });
    } else {
        packageForm.post(`/admin/conferences/${props.conference.id}/packages`, {
            onSuccess: () => {
 showPackageDialog.value = false; 
},
        });
    }
}

function confirmDeletePackage() {
    if (!deletingPackageId.value) {
return;
}

    packageForm.delete(`/admin/conferences/${props.conference.id}/packages/${deletingPackageId.value}`, {
        onSuccess: () => {
 deletingPackageId.value = null; 
},
    });
}

function formatCurrency(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
    }).format(amount);
}

const tierBadgeColors: Record<string, string> = {
    diamond: 'bg-cyan-100 text-cyan-800',
    platinum: 'bg-slate-100 text-slate-700',
    gold: 'bg-yellow-100 text-yellow-800',
    silver: 'bg-gray-100 text-gray-700',
    cso: 'bg-primary/10 text-primary',
    exhibitor: 'bg-accent/10 text-accent',
};
</script>

<template>
    <Head :title="`Edit: ${conference.name}`" />

    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <Link href="/admin/conferences" class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="h-4 w-4" />
                Back to Conferences
            </Link>
            <h1 class="font-heading text-2xl font-bold">Edit Conference</h1>
        </div>

        <!-- Conference Edit Form -->
        <Card>
            <CardHeader>
                <CardTitle>Conference Details</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-6" @submit.prevent="submitConference">
                    <div class="space-y-2">
                        <Label for="name">Conference Name</Label>
                        <Input id="name" v-model="form.name" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="year">Year</Label>
                        <Input id="year" v-model.number="form.year" type="number" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="start_date">Start Date</Label>
                            <Input id="start_date" v-model="form.start_date" type="date" />
                        </div>
                        <div class="space-y-2">
                            <Label for="end_date">End Date</Label>
                            <Input id="end_date" v-model="form.end_date" type="date" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="venue">Venue</Label>
                        <Input id="venue" v-model="form.venue" />
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="registration_deadline">Registration Deadline</Label>
                            <Input id="registration_deadline" v-model="form.registration_deadline" type="date" />
                        </div>
                        <div class="space-y-2">
                            <Label for="onboarding_deadline">Onboarding Deadline</Label>
                            <Input id="onboarding_deadline" v-model="form.onboarding_deadline" type="date" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="lock_date">Lock Date</Label>
                        <Input id="lock_date" v-model="form.lock_date" type="date" />
                    </div>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">
                            Save Changes
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Packages Section -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Sponsorship Packages</CardTitle>
                    <CardDescription>Manage the packages available for this conference.</CardDescription>
                </div>
                <Button @click="openAddPackage">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Package
                </Button>
            </CardHeader>
            <CardContent>
                <div v-if="packages.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                    No packages configured yet.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="pkg in packages"
                        :key="pkg.id"
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="flex items-center gap-3">
                            <Package class="h-5 w-5 text-muted-foreground" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ pkg.name }}</span>
                                    <Badge variant="outline" :class="tierBadgeColors[pkg.tier]" class="capitalize text-xs">
                                        {{ pkg.tier }}
                                    </Badge>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatCurrency(pkg.price, pkg.currency) }}
                                    &middot; {{ pkg.session_slots }} session{{ pkg.session_slots !== 1 ? 's' : '' }}
                                    <span v-if="pkg.max_partners"> &middot; Max {{ pkg.max_partners }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button variant="ghost" size="sm" @click="openEditPackage(pkg)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" class="text-destructive" @click="deletingPackageId = pkg.id">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Package Dialog -->
        <Dialog :open="showPackageDialog" @update:open="showPackageDialog = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingPackage ? 'Edit Package' : 'Add Package' }}</DialogTitle>
                    <DialogDescription>
                        Configure the sponsorship package details.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[60vh] space-y-4 overflow-y-auto py-4">
                    <div class="space-y-2">
                        <Label for="pkg-name">Package Name</Label>
                        <Input id="pkg-name" v-model="packageForm.name" placeholder="Gold Partnership" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Tier</Label>
                            <Select v-model="packageForm.tier">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="gold">Gold</SelectItem>
                                    <SelectItem value="silver">Silver</SelectItem>
                                    <SelectItem value="cso">CSO</SelectItem>
                                    <SelectItem value="exhibitor">Exhibitor</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Currency</Label>
                            <Select v-model="packageForm.currency">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="USD">USD</SelectItem>
                                    <SelectItem value="EUR">EUR</SelectItem>
                                    <SelectItem value="KES">KES</SelectItem>
                                    <SelectItem value="GBP">GBP</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="pkg-price">Price</Label>
                        <Input id="pkg-price" v-model.number="packageForm.price" type="number" min="0" />
                    </div>

                    <div class="space-y-2">
                        <Label for="pkg-desc">Description</Label>
                        <textarea
                            id="pkg-desc"
                            v-model="packageForm.description"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            placeholder="Package description..."
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="pkg-benefits">Benefits (one per line)</Label>
                        <textarea
                            id="pkg-benefits"
                            v-model="packageForm.benefits"
                            class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            placeholder="Logo on conference materials&#10;Booth in exhibition area&#10;Speaking opportunity"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="pkg-slots">Session Slots</Label>
                            <Input id="pkg-slots" v-model.number="packageForm.session_slots" type="number" min="0" />
                        </div>
                        <div class="space-y-2">
                            <Label for="pkg-max">Max Partners</Label>
                            <Input id="pkg-max" :model-value="packageForm.max_partners ?? undefined" @update:model-value="packageForm.max_partners = $event ? Number($event) : null" type="number" min="0" placeholder="Unlimited" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="pkg-exhibition">Exhibition Space</Label>
                        <Input id="pkg-exhibition" v-model="packageForm.exhibition_space" placeholder="3m x 3m booth" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showPackageDialog = false">Cancel</Button>
                    <Button
                        :disabled="!packageForm.name || packageForm.processing"
                        @click="submitPackage"
                    >
                        {{ editingPackage ? 'Save Changes' : 'Add Package' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Package Confirmation -->
        <ConfirmDialog
            :open="deletingPackageId !== null"
            title="Delete Package"
            description="Are you sure you want to delete this sponsorship package? Partners already assigned this package will not be affected."
            confirm-label="Delete Package"
            variant="destructive"
            @confirm="confirmDeletePackage"
            @cancel="deletingPackageId = null"
        />
    </div>
</template>
