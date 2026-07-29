<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, DoorOpen } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import DataTable from '@/components/shared/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Room } from '@/types/partner';

defineOptions({ layout: AdminLayout });

type FormatOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    rooms: Room[];
    formatOptions: FormatOption[];
}>();

const showFormDialog = ref(false);
const editingRoom = ref<Room | null>(null);
const deletingRoomId = ref<number | null>(null);

const form = useForm({
    name: '',
    building: '',
    floor: '',
    capacity: 0,
    format_suitability: [] as string[],
    equipment: '' as string,
    is_active: true,
});

function openAddDialog() {
    editingRoom.value = null;
    form.reset();
    form.format_suitability = [];
    form.is_active = true;
    showFormDialog.value = true;
}

function openEditDialog(room: Room) {
    editingRoom.value = room;
    form.name = room.name;
    form.building = room.building || '';
    form.floor = room.floor || '';
    form.capacity = room.capacity;
    form.format_suitability = room.format_suitability || [];
    form.equipment = room.equipment
        ? Object.entries(room.equipment)
              .map(([key, value]) => `${key}: ${value}`)
              .join('\n')
        : '';
    form.is_active = room.is_active;
    showFormDialog.value = true;
}

function toggleSuitability(format: string, checked: boolean) {
    const current = new Set(form.format_suitability);

    if (checked) {
        current.add(format);
    } else {
        current.delete(format);
    }

    form.format_suitability = [...current];
}

function submitForm() {
    if (editingRoom.value) {
        form.put(`/admin/rooms/${editingRoom.value.id}`, {
            onSuccess: () => {
                showFormDialog.value = false;
            },
        });

        return;
    }

    form.post('/admin/rooms', {
        onSuccess: () => {
            showFormDialog.value = false;
        },
    });
}

function confirmDelete() {
    if (!deletingRoomId.value) {
        return;
    }

    router.delete(`/admin/rooms/${deletingRoomId.value}`, {
        onSuccess: () => {
            deletingRoomId.value = null;
        },
    });
}

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'building', label: 'Building' },
    { key: 'floor', label: 'Floor' },
    { key: 'capacity', label: 'Capacity' },
    { key: 'format_suitability', label: 'Formats' },
    { key: 'equipment', label: 'Equipment' },
    { key: 'is_active', label: 'Active' },
];
</script>

<template>
    <Head title="Rooms" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold">Rooms</h1>
                <p class="text-sm text-muted-foreground">
                    Maintain the room inventory that powers the allocation
                    matrix.
                </p>
            </div>
            <Button @click="openAddDialog">
                <Plus class="mr-2 h-4 w-4" />
                Add Room
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :data="props.rooms"
            empty-message="No rooms configured."
        >
            <template #name="{ item }">
                <div class="flex items-center gap-2">
                    <DoorOpen class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium">{{ item.name }}</span>
                </div>
            </template>

            <template #format_suitability="{ item }">
                <div
                    v-if="item.format_suitability?.length"
                    class="flex flex-wrap gap-1"
                >
                    <Badge
                        v-for="format in item.format_suitability"
                        :key="format"
                        variant="secondary"
                        class="text-xs capitalize"
                    >
                        {{ format.replace('_', ' ') }}
                    </Badge>
                </div>
                <span v-else class="text-muted-foreground">All formats</span>
            </template>

            <template #equipment="{ item }">
                <div v-if="item.equipment" class="flex flex-wrap gap-1">
                    <Badge
                        v-for="(val, key) in item.equipment"
                        :key="String(key)"
                        variant="outline"
                        class="text-xs"
                    >
                        {{ key
                        }}<span v-if="val && val !== 'yes'">: {{ val }}</span>
                    </Badge>
                </div>
                <span v-else class="text-muted-foreground">---</span>
            </template>

            <template #is_active="{ item }">
                <Badge :variant="item.is_active ? 'default' : 'secondary'">
                    {{ item.is_active ? 'Active' : 'Inactive' }}
                </Badge>
            </template>

            <template #actions="{ item }">
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="openEditDialog(item)"
                    >
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive"
                        @click="deletingRoomId = item.id"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <Dialog :open="showFormDialog" @update:open="showFormDialog = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editingRoom ? 'Edit Room' : 'Add Room'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            editingRoom
                                ? 'Update room details and supported session formats.'
                                : 'Add a new room to the conference venue.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="name">Room Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Main Hall A"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="building">Building</Label>
                            <Input
                                id="building"
                                v-model="form.building"
                                placeholder="Building A"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="floor">Floor</Label>
                            <Input
                                id="floor"
                                v-model="form.floor"
                                placeholder="1st Floor"
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="capacity">Capacity</Label>
                        <Input
                            id="capacity"
                            v-model.number="form.capacity"
                            type="number"
                            min="0"
                            placeholder="100"
                        />
                    </div>

                    <div class="space-y-3">
                        <Label>Supported Session Formats</Label>
                        <div
                            class="grid gap-3 rounded-lg border border-border/70 p-3 sm:grid-cols-2"
                        >
                            <div
                                v-for="option in props.formatOptions"
                                :key="option.value"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`format-${option.value}`"
                                    :checked="
                                        form.format_suitability.includes(
                                            option.value,
                                        )
                                    "
                                    @update:checked="
                                        toggleSuitability(
                                            option.value,
                                            Boolean($event),
                                        )
                                    "
                                />
                                <Label :for="`format-${option.value}`">{{
                                    option.label
                                }}</Label>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Leave all formats unchecked only if this room can
                            host any session type.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="equipment"
                            >Equipment (one per line, format: key: value)</Label
                        >
                        <textarea
                            id="equipment"
                            v-model="form.equipment"
                            class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder="projector: yes&#10;microphone: 4&#10;interpretation: available"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            :checked="form.is_active"
                            @update:checked="form.is_active = Boolean($event)"
                        />
                        <Label for="is_active">Active</Label>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showFormDialog = false"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="form.processing || !form.name"
                        @click="submitForm"
                    >
                        {{ editingRoom ? 'Save Changes' : 'Add Room' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="deletingRoomId !== null"
            title="Delete Room"
            description="Are you sure you want to delete this room? Any scheduled sessions in this room will need to be reassigned."
            confirm-label="Delete Room"
            variant="destructive"
            @confirm="confirmDelete"
            @cancel="deletingRoomId = null"
        />
    </div>
</template>
