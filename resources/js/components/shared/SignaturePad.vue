<script setup lang="ts">
import { Eraser } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * Draw-your-signature box.
 *
 * Emits a PNG data URL, or null once cleared, so the caller can bind it
 * straight to a form field and send it with the rest of the signing details.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        /** Shown under the box, e.g. whose signature this is. */
        label?: string;
        disabled?: boolean;
    }>(),
    { label: 'Sign above', disabled: false },
);

const emit = defineEmits<{ 'update:modelValue': [string | null] }>();

const canvas = ref<HTMLCanvasElement | null>(null);
const hasInk = ref(false);

let context: CanvasRenderingContext2D | null = null;
let drawing = false;

/**
 * Canvases are sized in CSS pixels but drawn in device pixels; without the
 * scale the stroke is blurry on a retina screen and misplaced after a resize.
 */
function prepareCanvas() {
    const element = canvas.value;

    if (!element) {
        return;
    }

    const ratio = window.devicePixelRatio || 1;
    const { width, height } = element.getBoundingClientRect();

    if (width === 0 || height === 0) {
        return;
    }

    element.width = Math.round(width * ratio);
    element.height = Math.round(height * ratio);

    context = element.getContext('2d');

    if (!context) {
        return;
    }

    context.scale(ratio, ratio);
    context.lineWidth = 2;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#111827';
}

function pointFrom(event: PointerEvent) {
    const rect = canvas.value!.getBoundingClientRect();

    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
}

function start(event: PointerEvent) {
    if (props.disabled || !context) {
        return;
    }

    drawing = true;
    canvas.value?.setPointerCapture(event.pointerId);

    const { x, y } = pointFrom(event);

    context.beginPath();
    context.moveTo(x, y);
    // A tap with no drag should still leave a mark.
    context.lineTo(x, y);
    context.stroke();
    hasInk.value = true;
}

function move(event: PointerEvent) {
    if (!drawing || !context) {
        return;
    }

    const { x, y } = pointFrom(event);

    context.lineTo(x, y);
    context.stroke();
}

function end(event: PointerEvent) {
    if (!drawing) {
        return;
    }

    drawing = false;
    canvas.value?.releasePointerCapture(event.pointerId);
    commit();
}

function commit() {
    if (!canvas.value || !hasInk.value) {
        emit('update:modelValue', null);

        return;
    }

    emit('update:modelValue', canvas.value.toDataURL('image/png'));
}

function clear() {
    if (!canvas.value || !context) {
        return;
    }

    context.clearRect(0, 0, canvas.value.width, canvas.value.height);
    hasInk.value = false;
    emit('update:modelValue', null);
}

/** A resize resets the backing store, so whatever was drawn is gone. */
function onResize() {
    prepareCanvas();

    if (hasInk.value) {
        hasInk.value = false;
        emit('update:modelValue', null);
    }
}

onMounted(() => {
    prepareCanvas();
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => window.removeEventListener('resize', onResize));
</script>

<template>
    <div class="space-y-2">
        <div
            class="relative overflow-hidden rounded-lg border-2 border-dashed bg-background"
            :class="disabled ? 'opacity-60' : ''"
        >
            <canvas
                ref="canvas"
                class="block h-36 w-full"
                :class="disabled ? 'cursor-not-allowed' : 'cursor-crosshair touch-none'"
                @pointerdown="start"
                @pointermove="move"
                @pointerup="end"
                @pointerleave="end"
                @pointercancel="end"
            />
            <p
                v-if="!hasInk"
                class="text-muted-foreground pointer-events-none absolute inset-0 flex items-center justify-center text-sm"
            >
                Draw your signature here
            </p>
        </div>

        <div class="flex items-center justify-between">
            <p class="text-muted-foreground text-xs">{{ label }}</p>
            <Button
                v-if="hasInk"
                type="button"
                variant="ghost"
                size="sm"
                :disabled="disabled"
                @click="clear"
            >
                <Eraser class="mr-2 h-3.5 w-3.5" />
                Clear
            </Button>
        </div>
    </div>
</template>
