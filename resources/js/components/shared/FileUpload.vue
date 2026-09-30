<script setup lang="ts">
import { Upload, X, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        accept?: string;
        maxSize?: number;
        /** Accept a whole batch at once instead of a single file. */
        multiple?: boolean;
    }>(),
    {
        accept: '*',
        maxSize: 10,
        multiple: false,
    },
);

/**
 * `change` carries the single selection; `changeMultiple` carries the whole
 * current batch and only fires when `multiple` is set. Keeping them apart lets
 * single-file callers stay bound to a `File | null` form field.
 */
const emit = defineEmits<{
    change: [file: File | null];
    changeMultiple: [files: File[]];
}>();

const selectedFiles = ref<File[]>([]);
const isDragOver = ref(false);
const error = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const selectedFile = computed(() => selectedFiles.value[0] ?? null);

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function validateFile(file: File): boolean {
    if (props.maxSize && file.size > props.maxSize * 1024 * 1024) {
        error.value = `${file.name} exceeds the ${props.maxSize} MB limit.`;

        return false;
    }

    if (props.accept && props.accept !== '*') {
        const accepted = props.accept.split(',').map((a) => a.trim().toLowerCase());
        const ext = '.' + file.name.split('.').pop()?.toLowerCase();
        const mime = file.type.toLowerCase();

        const isValid = accepted.some((a) => {
            if (a.startsWith('.')) {
return ext === a;
}

            if (a.endsWith('/*')) {
return mime.startsWith(a.replace('/*', '/'));
}

            return mime === a;
        });

        if (!isValid) {
            error.value = `${file.name} is not an accepted file type. Allowed: ${props.accept}`;

            return false;
        }
    }

    return true;
}

function emitSelection() {
    if (props.multiple) {
        emit('changeMultiple', [...selectedFiles.value]);

        return;
    }

    emit('change', selectedFiles.value[0] ?? null);
}

function handleFiles(files: File[]) {
    error.value = null;
    const valid = files.filter((file) => validateFile(file));

    if (!valid.length) {
        return;
    }

    if (props.multiple) {
        // Adding to the selection lets a partner drop several batches in a row.
        const existing = new Set(
            selectedFiles.value.map((file) => `${file.name}:${file.size}`),
        );

        selectedFiles.value = [
            ...selectedFiles.value,
            ...valid.filter((file) => !existing.has(`${file.name}:${file.size}`)),
        ];
    } else {
        selectedFiles.value = [valid[0]];
    }

    emitSelection();
}

function onDrop(e: DragEvent) {
    isDragOver.value = false;
    handleFiles([...(e.dataTransfer?.files ?? [])]);
}

function onFileSelect(e: Event) {
    const target = e.target as HTMLInputElement;
    handleFiles([...(target.files ?? [])]);

    // Clear the native input so re-picking the same file fires `change` again.
    target.value = '';
}

function removeFile(index = 0) {
    selectedFiles.value.splice(index, 1);
    error.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }

    emitSelection();
}

function clearAll() {
    selectedFiles.value = [];
    error.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }

    emitSelection();
}

function openPicker() {
    fileInput.value?.click();
}
</script>

<template>
    <div>
        <input
            ref="fileInput"
            type="file"
            :accept="accept"
            :multiple="multiple"
            class="hidden"
            @change="onFileSelect"
        />

        <!-- Drop zone -->
        <div
            v-if="multiple || !selectedFile"
            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-8 transition-colors"
            :class="[
                isDragOver
                    ? 'border-primary bg-primary/5'
                    : 'border-muted-foreground/25 hover:border-primary/50',
            ]"
            @click="openPicker"
            @dragover.prevent="isDragOver = true"
            @dragleave="isDragOver = false"
            @drop.prevent="onDrop"
        >
            <Upload class="mb-2 size-8 text-muted-foreground" />
            <p class="text-sm font-medium">
                {{ multiple ? 'Drop files here or click to browse' : 'Drop file here or click to browse' }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                <template v-if="multiple">Select several at once &middot; </template>Max {{ maxSize }} MB each
                <template v-if="accept !== '*'"> &middot; {{ accept }}</template>
            </p>
        </div>

        <!-- Selection -->
        <div v-if="selectedFiles.length" :class="multiple ? 'mt-3 space-y-2' : ''">
            <div
                v-for="(file, index) in selectedFiles"
                :key="`${file.name}-${file.size}-${index}`"
                class="flex items-center gap-3 rounded-lg border bg-muted/30 px-4 py-3"
            >
                <FileText class="size-8 shrink-0 text-muted-foreground" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ file.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ formatSize(file.size) }}</p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    @click="removeFile(index)"
                >
                    <X class="size-4" />
                </Button>
            </div>

            <div v-if="multiple && selectedFiles.length > 1" class="flex items-center justify-between">
                <p class="text-xs text-muted-foreground">
                    {{ selectedFiles.length }} files ready to upload
                </p>
                <Button type="button" variant="ghost" size="sm" @click="clearAll">
                    Remove all
                </Button>
            </div>
        </div>

        <!-- Error -->
        <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
    </div>
</template>
