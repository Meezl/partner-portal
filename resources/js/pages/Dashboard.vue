<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const page = usePage();

onMounted(() => {
    const role = page.props.userRole as string | null;

    if (role === 'partner') {
        router.visit('/partner/dashboard');
    } else if (role && role !== 'partner') {
        router.visit('/admin/dashboard');
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-screen items-center justify-center bg-background">
        <div class="text-center">
            <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
            <p class="mt-4 text-muted-foreground">Redirecting to your dashboard...</p>
        </div>
    </div>
</template>
