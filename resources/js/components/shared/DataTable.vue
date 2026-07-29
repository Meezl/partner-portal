<script setup lang="ts" generic="T">
import { computed } from 'vue';

export interface Column<T> {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
}

const props = withDefaults(
    defineProps<{
        columns: Column<T>[];
        data: T[];
        emptyMessage?: string;
    }>(),
    {
        emptyMessage: 'No records found.',
    },
);

defineSlots<{
    [key: string]: (props: { item: T; value?: unknown }) => unknown;
}>();

function getValue(item: T, key: string): unknown {
    return key.split('.').reduce((obj: unknown, k: string) => {
        if (obj && typeof obj === 'object') {
            return (obj as Record<string, unknown>)[k];
        }

        return undefined;
    }, item);
}
</script>

<template>
    <div class="w-full overflow-auto rounded-lg border">
        <table class="w-full caption-bottom text-sm">
            <thead class="border-b bg-muted/50">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        class="h-10 px-4 text-left align-middle font-medium text-muted-foreground"
                        :class="col.class"
                    >
                        {{ col.label }}
                    </th>
                    <th
                        v-if="$slots.actions"
                        class="h-10 w-[100px] px-4 text-right align-middle font-medium text-muted-foreground"
                    >
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                <template v-if="data.length > 0">
                    <tr
                        v-for="(item, index) in data"
                        :key="index"
                        class="border-b transition-colors hover:bg-muted/50"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3 align-middle"
                            :class="col.class"
                        >
                            <slot
                                :name="col.key"
                                :item="item"
                                :value="getValue(item, col.key)"
                            >
                                {{ getValue(item, col.key) ?? '—' }}
                            </slot>
                        </td>
                        <td
                            v-if="$slots.actions"
                            class="px-4 py-3 text-right align-middle"
                        >
                            <slot name="actions" :item="item" />
                        </td>
                    </tr>
                </template>
                <tr v-else>
                    <td
                        :colspan="columns.length + ($slots.actions ? 1 : 0)"
                        class="px-4 py-8 text-center text-muted-foreground"
                    >
                        {{ emptyMessage }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
