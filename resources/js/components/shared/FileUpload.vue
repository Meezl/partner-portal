<script setup lang="ts">
import { Upload, X, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        accept?: string;
        maxSize?: number;
    }>(),
    {
        accept: '*',
        maxSize: 10,
    },
);

const emit = defineEmits<{
    change: [file: File | null];
}>();

const selectedFile = ref<File | null>(null);
const isDragOver = ref(false);
const error = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const fileSize = computed(() => {
    if (!selectedFile.value) {
return '';
}

    const bytes = selectedFile.value.size;

    if (bytes < 1024) {
return `${bytes} B`;
}

    if (bytes < 1024 * 1024) {
return `${(bytes / 1024).toFixed(1)} KB`;
}

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
});

function validateFile(file: File): boolean {
    error.value = null;

    if (props.maxSize && file.size > props.maxSize * 1024 * 1024) {
        error.value = `File size exceeds ${props.maxSize} MB limit.`;

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
            error.value = `File type not accepted. Allowed: ${props.accept}`;

            return false;
        }
    }

    return true;
}

function handleFile(file: File) {
    if (validateFile(file)) {
        selectedFile.value = file;
        emit('change', file);
    }
}

function onDrop(e: DragEvent) {
    isDragOver.value = false;
    const file = e.dataTransfer?.files[0];

    if (file) {
handleFile(file);
}
}

function onFileSelect(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
handleFile(file);
}
}

function removeFile() {
    selectedFile.value = null;
    error.value = null;

    if (fileInput.value) {
fileInput.value.value = '';
}

    emit('change', null);
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
            class="hidden"
            @change="onFileSelect"
        />

        <!-- Drop zone -->
        <div
            v-if="!selectedFile"
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
                Drop file here or click to browse
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Max {{ maxSize }} MB
                <template v-if="accept !== '*'"> &middot; {{ accept }}</template>
            </p>
        </div>

        <!-- File preview -->
        <div
            v-else
            class="flex items-center gap-3 rounded-lg border bg-muted/30 px-4 py-3"
        >
            <FileText class="size-8 shrink-0 text-muted-foreground" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                    {{ selectedFile.name }}
                </p>
                <p class="text-xs text-muted-foreground">{{ fileSize }}</p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                class="size-8 shrink-0"
                @click="removeFile"
            >
                <X class="size-4" />
            </Button>
        </div>

        <!-- Error -->
        <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
    </div>
</template>
