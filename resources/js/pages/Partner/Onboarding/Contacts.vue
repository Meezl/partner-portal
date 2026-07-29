<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Plus, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
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
}>();

interface ContactEntry {
    id?: number;
    name: string;
    email: string;
    phone: string;
    role: string;
    organization: string;
}

const contacts = ref<ContactEntry[]>(
    props.contacts.length > 0
        ? props.contacts.map((c) => ({
              id: c.id,
              name: c.name,
              email: c.email,
              phone: c.phone ?? '',
              role: c.role,
              organization: c.organization ?? '',
          }))
        : [{ name: '', email: '', phone: '', role: 'session_lead', organization: '' }],
);

const form = useForm({
    contacts: contacts.value,
});

function addContact() {
    contacts.value.push({ name: '', email: '', phone: '', role: 'additional', organization: '' });
    form.contacts = contacts.value;
}

function removeContact(index: number) {
    if (contacts.value.length > 1) {
        contacts.value.splice(index, 1);
        form.contacts = contacts.value;
    }
}

function submit() {
    form.contacts = contacts.value;
    form.put('/partner/onboarding/contacts', { preserveScroll: true });
}

const roles = [
    { value: 'session_lead', label: 'Session Lead' },
    { value: 'comms_lead', label: 'Communications Lead' },
    { value: 'additional', label: 'Additional Contact' },
];
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

        <div class="space-y-4">
            <Card v-for="(contact, index) in contacts" :key="index">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Users class="h-4 w-4" />
                            Contact {{ index + 1 }}
                        </CardTitle>
                        <Button
                            v-if="contacts.length > 1"
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
                        </div>

                        <div class="space-y-2">
                            <Label>Email</Label>
                            <Input v-model="contact.email" type="email" placeholder="email@example.com" />
                        </div>

                        <div class="space-y-2">
                            <Label>Phone</Label>
                            <Input v-model="contact.phone" placeholder="+254 700 000 000" />
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
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label>Organization</Label>
                            <Input v-model="contact.organization" placeholder="Organization name" />
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
