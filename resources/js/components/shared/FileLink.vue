<script setup lang="ts">
import { Download, FileText } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

/**
 * One uploaded file: its name opens it in a new tab, the button saves it.
 *
 * Used wherever a partner's uploads are reviewed, so the partner's own review
 * page and the admin screens present them the same way.
 */
defineProps<{
    name: string;
    url: string;
}>();
</script>

<template>
    <div class="flex items-center gap-2">
        <FileText class="text-muted-foreground h-4 w-4 shrink-0" />
        <a
            :href="url"
            target="_blank"
            rel="noopener"
            class="min-w-0 flex-1 truncate text-sm underline underline-offset-2"
            :title="name"
        >
            {{ name }}
        </a>
        <Button variant="ghost" size="icon" class="size-7 shrink-0" as-child>
            <a :href="url" :download="name" :aria-label="`Download ${name}`">
                <Download class="size-3.5" />
            </a>
        </Button>
    </div>
</template>
