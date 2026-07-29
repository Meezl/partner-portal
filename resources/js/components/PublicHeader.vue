<script setup lang="ts">
import { Link, Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
</script>

<template>
  <Head :title="'AHAIC Partner Portal'" />
  <header class="flex items-center justify-between px-6 py-4 bg-white/80 backdrop-blur-sm border-b border-border/50">
    <Link href="/" class="flex items-center gap-3 transition-opacity hover:opacity-80">
      <img src="/img/logo%204.jpg" alt="AHAIC Logo" class="h-10 w-auto" />
      <span class="font-heading text-xl text-primary">AHAIC</span>
      <span class="ml-1 text-sm text-muted-foreground">Partner Portal</span>
    </Link>
    <nav class="flex items-center gap-3">
      <!-- <Link href="/" class="inline-flex items-center gap-2">
        <Button variant="outline">Home</Button>
      </Link> -->
      <Link href="/packages" class="inline-flex items-center gap-2">
        <Button variant="outline">Packages</Button>
      </Link>
      <Link v-if="$page.props.auth.user" href="/dashboard" class="inline-flex items-center gap-2">
        <Button variant="default">Dashboard</Button>
      </Link>
      <template v-else>
        <Link href="/login" class="inline-flex items-center gap-2">
          <Button variant="outline">Log in</Button>
        </Link>
        <Link href="/register" class="inline-flex items-center gap-2">
          <Button variant="default">Register</Button>
        </Link>
      </template>
    </nav>
  </header>
</template>
