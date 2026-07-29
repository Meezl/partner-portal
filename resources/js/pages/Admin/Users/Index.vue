<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Search, Users as UsersIcon, ShieldCheck, XCircle, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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

type Role = { value: string; label: string; count: number };

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    phone: string | null;
    department: string | null;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
    partner_id: number | null;
    partner?: { id: number; organization_name: string; slug: string } | null;
};

const props = defineProps<{
    users: UserRow[];
    roles: Role[];
    filters: { role: string | null; search: string; active: string };
    totals: { all: number; active: number };
    can: { manage: boolean };
}>();

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? 'all');
const active = ref(props.filters.active ?? 'all');

const ROLE_COLOR: Record<string, string> = {
    super_admin: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    admin: 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200',
    finance: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    programme: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    pco: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200',
    communications: 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-950 dark:text-fuchsia-200',
    partnerships: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    partner: 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-200',
    session_lead: 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200',
    rapporteur: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
};

function labelFor(value: string): string {
    return props.roles.find((r) => r.value === value)?.label ?? value;
}

function apply() {
    router.get(
        '/admin/users',
        {
            role: role.value === 'all' ? null : role.value,
            search: search.value || null,
            active: active.value === 'all' ? null : active.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(apply, 300);
});
watch([role, active], apply);

function clearFilters() {
    search.value = '';
    role.value = 'all';
    active.value = 'all';
}

function formatDate(v: string | null): string {
    if (!v) return '—';
    return new Date(v).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

// ---------- CRUD ----------
const showDialog = ref(false);
const editing = ref<UserRow | null>(null);

const form = useForm({
    name: '',
    email: '',
    role: 'partner',
    phone: '',
    department: '',
    is_active: true,
    password: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'partner';
    form.is_active = true;
    showDialog.value = true;
}

function openEdit(u: UserRow) {
    editing.value = u;
    form.clearErrors();
    form.name = u.name;
    form.email = u.email;
    form.role = u.role;
    form.phone = u.phone ?? '';
    form.department = u.department ?? '';
    form.is_active = u.is_active;
    form.password = '';
    showDialog.value = true;
}

function submit() {
    if (editing.value) {
        form.put(`/admin/users/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showDialog.value = false; },
        });
    } else {
        form.post('/admin/users', {
            preserveScroll: true,
            onSuccess: () => { showDialog.value = false; },
        });
    }
}

function remove(u: UserRow) {
    if (!confirm(`Delete ${u.name}? This cannot be undone.`)) return;
    router.delete(`/admin/users/${u.id}`, { preserveScroll: true });
}

const dialogTitle = computed(() => (editing.value ? 'Edit user' : 'Create user'));
const dialogSubmitLabel = computed(() => (editing.value ? 'Save changes' : 'Create'));
</script>

<template>
    <Head title="Users" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold">Users</h1>
                <p class="text-muted-foreground text-sm">
                    {{ totals.all }} total · {{ totals.active }} active · {{ roles.length }} roles
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <Search class="text-muted-foreground pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" placeholder="Search name or email…" class="w-72 pl-9" />
                </div>
                <Select v-model="role">
                    <SelectTrigger class="w-56">
                        <SelectValue placeholder="All roles" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All roles ({{ totals.all }})</SelectItem>
                        <SelectItem v-for="r in roles" :key="r.value" :value="r.value">
                            {{ r.label }} ({{ r.count }})
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="active">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All statuses</SelectItem>
                        <SelectItem value="active">Active only</SelectItem>
                        <SelectItem value="inactive">Inactive only</SelectItem>
                    </SelectContent>
                </Select>
                <Button v-if="search || role !== 'all' || active !== 'all'" variant="ghost" size="sm" @click="clearFilters">
                    <XCircle class="mr-1 h-4 w-4" /> Clear
                </Button>
                <Button v-if="can.manage" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" /> New user
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <button
                v-for="r in roles"
                :key="r.value"
                type="button"
                class="rounded-md border p-3 text-left transition hover:border-primary"
                :class="role === r.value ? 'border-primary bg-primary/5' : 'border-input'"
                @click="role = r.value"
            >
                <div class="text-muted-foreground text-xs uppercase tracking-wide">{{ r.label }}</div>
                <div class="mt-1 flex items-center gap-2 text-xl font-semibold">
                    <UsersIcon class="h-4 w-4 opacity-60" /> {{ r.count }}
                </div>
            </button>
        </div>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Name</th>
                                <th class="px-4 py-2 text-left font-medium">Email</th>
                                <th class="px-4 py-2 text-left font-medium">Role</th>
                                <th class="px-4 py-2 text-left font-medium">Organization</th>
                                <th class="px-4 py-2 text-left font-medium">Status</th>
                                <th class="px-4 py-2 text-left font-medium">Last login</th>
                                <th v-if="can.manage" class="px-4 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="u in users" :key="u.id" class="hover:bg-muted/30">
                                <td class="px-4 py-2 font-medium">{{ u.name }}</td>
                                <td class="px-4 py-2">
                                    <a :href="`mailto:${u.email}`" class="text-primary hover:underline">{{ u.email }}</a>
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="ROLE_COLOR[u.role] ?? 'bg-gray-100 text-gray-800'"
                                    >
                                        {{ labelFor(u.role) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <Link v-if="u.partner" :href="`/admin/partners/${u.partner.id}`" class="hover:underline">
                                        {{ u.partner.organization_name }}
                                    </Link>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-2">
                                    <Badge v-if="u.is_active" variant="secondary" class="gap-1">
                                        <ShieldCheck class="h-3 w-3" /> Active
                                    </Badge>
                                    <Badge v-else variant="outline">Inactive</Badge>
                                </td>
                                <td class="px-4 py-2 text-muted-foreground">{{ formatDate(u.last_login_at) }}</td>
                                <td v-if="can.manage" class="px-4 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button variant="ghost" size="sm" @click="openEdit(u)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="sm" class="text-destructive" @click="remove(u)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!users.length">
                                <td :colspan="can.manage ? 7 : 6" class="px-4 py-8 text-center text-muted-foreground">
                                    No users match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Create / Edit dialog -->
        <Dialog :open="showDialog" @update:open="showDialog = $event">
            <DialogContent class="bg-background sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        <span v-if="editing">Update details for {{ editing.name }}.</span>
                        <span v-else>Create a new portal account. A temporary password is generated if you leave the field blank.</span>
                    </DialogDescription>
                </DialogHeader>

                <form class="flex flex-col gap-4 py-2" @submit.prevent="submit">
                    <div class="flex flex-col gap-1.5">
                        <Label for="u-name">Name</Label>
                        <Input id="u-name" v-model="form.name" class="bg-background" autocomplete="off" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="u-email">Email</Label>
                        <Input id="u-email" v-model="form.email" type="email" class="bg-background" autocomplete="off" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label>Role</Label>
                            <Select v-model="form.role">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in roles" :key="r.value" :value="r.value">
                                        {{ r.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.role" />
                        </div>
                        <div class="flex items-center gap-3 pt-6">
                            <Checkbox id="u-active" :checked="form.is_active" @update:checked="form.is_active = $event" />
                            <Label for="u-active" class="cursor-pointer">Active</Label>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label for="u-phone">Phone</Label>
                            <Input id="u-phone" v-model="form.phone" class="bg-background" autocomplete="off" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="u-dept">Department</Label>
                            <Input id="u-dept" v-model="form.department" class="bg-background" autocomplete="off" />
                            <InputError :message="form.errors.department" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="u-password">
                            {{ editing ? 'New password' : 'Password' }}
                            <span class="text-muted-foreground text-xs font-normal">
                                ({{ editing ? 'leave blank to keep current' : 'auto-generated if blank' }})
                            </span>
                        </Label>
                        <Input id="u-password" v-model="form.password" type="password" class="bg-background" autocomplete="new-password" />
                        <InputError :message="form.errors.password" />
                    </div>

                    <DialogFooter class="pt-2">
                        <Button type="button" variant="outline" @click="showDialog = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">{{ dialogSubmitLabel }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
