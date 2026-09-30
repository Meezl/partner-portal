<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Plus, Trash2, Users, CheckCircle2, Circle, Info } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, PartnerContact } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    contacts: PartnerContact[];
    /** Role key → label for the role picker, from the server. */
    contactRoles: Record<string, string>;
    /**
     * Role key → label for the contacts the section cannot be completed
     * without. Comes from OnboardingProgressService so the form and the
     * progress bar agree on what is mandatory.
     */
    mandatoryRoles: Record<string, string>;
}>();

interface ContactEntry {
    id?: number;
    name: string;
    email: string;
    phone: string;
    role: string;
    designation: string;
    organization: string;
}

function blankContact(role: string): ContactEntry {
    return { name: '', email: '', phone: '', role, designation: '', organization: '' };
}

const mandatoryRoleKeys = Object.keys(props.mandatoryRoles);

/**
 * Open with one empty row per mandatory contact, so the two the partner must
 * name are on screen from the start rather than something they have to
 * discover by watching the progress bar refuse to reach 100%.
 */
function initialContacts(): ContactEntry[] {
    if (props.contacts.length > 0) {
        const saved = props.contacts.map((c) => ({
            id: c.id,
            name: c.name,
            email: c.email,
            phone: c.phone ?? '',
            role: c.role,
            designation: c.designation ?? '',
            organization: c.organization ?? '',
        }));

        const missing = mandatoryRoleKeys
            .filter((role) => !saved.some((contact) => contact.role === role))
            .map((role) => blankContact(role));

        return [...saved, ...missing];
    }

    return mandatoryRoleKeys.map((role) => blankContact(role));
}

const contactRows = ref<ContactEntry[]>(initialContacts());

const form = useForm({
    contacts: contactRows.value,
});

/** Which mandatory contacts are filled in, for the checklist at the top. */
const mandatoryStatus = computed(() =>
    Object.entries(props.mandatoryRoles).map(([role, label]) => ({
        role,
        label,
        complete: contactRows.value.some(
            (contact) => contact.role === role && contact.name.trim() !== '' && contact.email.trim() !== '',
        ),
    })),
);

const allMandatoryComplete = computed(() => mandatoryStatus.value.every((entry) => entry.complete));

function roleLabel(role: string): string {
    return props.contactRoles[role] ?? role;
}

function isMandatory(role: string): boolean {
    return mandatoryRoleKeys.includes(role);
}

/** Per-contact error messages, e.g. contacts.1.email. */
function contactError(index: number, field: string): string | undefined {
    return (form.errors as Record<string, string>)[`contacts.${index}.${field}`];
}

function addContact() {
    contactRows.value.push(blankContact('additional'));
    form.contacts = contactRows.value;
}

function removeContact(index: number) {
    if (contactRows.value.length > 1) {
        contactRows.value.splice(index, 1);
        form.contacts = contactRows.value;
    }
}

function submit() {
    form.contacts = contactRows.value;
    form.put('/partner/onboarding/contacts', { preserveScroll: true });
}

const roles = computed(() =>
    Object.entries(props.contactRoles).map(([value, label]) => ({ value, label })),
);
</script>

<template>
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading text-3xl font-bold tracking-tight">Contacts</h1>
                <p class="text-muted-foreground mt-1">Add key contacts for your partnership.</p>
            </div>
            <Button variant="outline" @click="addContact">
                <Plus class="mr-2 h-4 w-4" />
                Add Contact
            </Button>
        </div>

        <div
            class="rounded-lg border p-4"
            :class="
                allMandatoryComplete
                    ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/30'
                    : 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
            "
        >
            <div class="flex items-start gap-3">
                <Info
                    class="mt-0.5 h-4 w-4 shrink-0"
                    :class="allMandatoryComplete ? 'text-green-700 dark:text-green-400' : 'text-amber-600 dark:text-amber-500'"
                />
                <div class="space-y-3">
                    <p class="text-sm font-medium">
                        Two contacts are mandatory — a name and an email for each completes this section.
                    </p>
                    <ul class="space-y-1.5">
                        <li
                            v-for="entry in mandatoryStatus"
                            :key="entry.role"
                            class="flex items-center gap-2 text-sm"
                        >
                            <CheckCircle2
                                v-if="entry.complete"
                                class="h-4 w-4 text-green-600 dark:text-green-500"
                            />
                            <Circle v-else class="text-muted-foreground h-4 w-4" />
                            <span :class="entry.complete ? '' : 'text-muted-foreground'">
                                {{ entry.label }}
                            </span>
                        </li>
                    </ul>
                    <p class="text-muted-foreground text-xs">
                        Anyone else you add — a media contact, say — is optional and does not change
                        the progress on this section.
                    </p>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <Card v-for="(contact, index) in contactRows" :key="index">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Users class="h-4 w-4" />
                            {{ roleLabel(contact.role) }}
                            <Badge v-if="isMandatory(contact.role)" variant="secondary">Required</Badge>
                        </CardTitle>
                        <Button
                            v-if="contactRows.length > 1"
                            variant="ghost"
                            size="sm"
                            class="text-red-600 hover:text-red-700"
                            @click="removeContact(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Name</Label>
                            <Input v-model="contact.name" placeholder="Full name" />
                            <InputError :message="contactError(index, 'name')" />
                        </div>

                        <div class="space-y-2">
                            <Label>Email</Label>
                            <Input v-model="contact.email" type="email" placeholder="email@example.com" />
                            <InputError :message="contactError(index, 'email')" />
                        </div>

                        <div class="space-y-2">
                            <Label>Phone</Label>
                            <Input v-model="contact.phone" placeholder="+254 700 000 000" />
                            <InputError :message="contactError(index, 'phone')" />
                        </div>

                        <div class="space-y-2">
                            <Label>Role</Label>
                            <Select v-model="contact.role">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="role in roles" :key="role.value" :value="role.value">
                                        {{ role.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="contactError(index, 'role')" />
                        </div>

                        <div class="space-y-2">
                            <Label>Designation</Label>
                            <Input v-model="contact.designation" placeholder="e.g. Director of Communications" />
                            <p class="text-muted-foreground text-xs">Their job title at your organization.</p>
                            <InputError :message="contactError(index, 'designation')" />
                        </div>

                        <div class="space-y-2">
                            <Label>Organization</Label>
                            <Input v-model="contact.organization" placeholder="Organization name" />
                            <InputError :message="contactError(index, 'organization')" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="flex justify-end gap-3">
            <Button variant="outline" @click="addContact">
                <Plus class="mr-2 h-4 w-4" />
                Add More
            </Button>
            <Button @click="submit" :disabled="form.processing">
                <Save class="mr-2 h-4 w-4" />
                Save Contacts
            </Button>
        </div>
    </div>
</template>
