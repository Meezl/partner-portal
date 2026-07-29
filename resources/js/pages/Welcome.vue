<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Globe, Users, Calendar, Award } from 'lucide-vue-next';
import PublicHeader from '@/components/PublicHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Welcome to AHAIC Partner Portal" />

    <div class="min-h-screen bg-ahaic-offwhite dark:bg-background">
        <!-- Header -->
                <PublicHeader />

        <!-- Hero Section -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-primary/90 to-ahaic-brown/80"></div>
            <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><polygon points=%2250,5 95,25 95,75 50,95 5,75 5,25%22 fill=%22none%22 stroke=%22white%22 stroke-width=%221%22/></svg>'); background-size: 60px;"></div>
            <div class="relative mx-auto max-w-7xl px-6 py-24 text-center text-white lg:py-32">
                <Badge variant="secondary" class="mb-6 bg-ahaic-yellow text-black">
                    AHAIC 2027
                </Badge>
                <h1 class="font-heading text-4xl tracking-tight lg:text-6xl">
                    Connected for Change
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-white/90">
                    AHAIC is more than a conference. It connects changemakers from across
                    continents to co-create a better future for health in Africa.
                </p>
                <div class="mt-10 flex items-center justify-center gap-4">
                    <Link href="/packages">
                        <Button size="lg" class="bg-ahaic-yellow text-black hover:bg-ahaic-yellow/90">
                            View Sponsorship Packages
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>
                    </Link>
                    <Link v-if="canRegister" href="/register">
                        <Button size="lg" variant="outline" class="bg-ahaic-green text-white hover:bg-white/10">
                            Become a Partner
                        </Button>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="mx-auto max-w-7xl px-6 py-20">
            <div class="text-center">
                <h2 class="font-heading text-3xl text-foreground">Partner Engagement Portal</h2>
                <p class="mt-4 text-muted-foreground">
                    Your one-stop platform for managing your AHAIC partnership journey
                </p>
            </div>

            <div class="mt-16 grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                <Card class="border-none bg-white shadow-sm dark:bg-card">
                    <CardHeader>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                            <Award class="h-6 w-6 text-primary" />
                        </div>
                        <CardTitle class="mt-4 font-heading">Choose Your Package</CardTitle>
                        <CardDescription>
                            Select from Gold, Silver, CSO, or Exhibitor sponsorship tiers with tailored benefits.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card class="border-none bg-white shadow-sm dark:bg-card">
                    <CardHeader>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-accent/10">
                            <Users class="h-6 w-6 text-accent" />
                        </div>
                        <CardTitle class="mt-4 font-heading">Onboard Your Team</CardTitle>
                        <CardDescription>
                            Submit organization details, session proposals, and team contacts through a guided process.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card class="border-none bg-white shadow-sm dark:bg-card">
                    <CardHeader>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-ahaic-yellow/10">
                            <Calendar class="h-6 w-6 text-ahaic-yellow" />
                        </div>
                        <CardTitle class="mt-4 font-heading">Session Scheduling</CardTitle>
                        <CardDescription>
                            View your assigned sessions, rooms, and time slots. Request changes when needed.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card class="border-none bg-white shadow-sm dark:bg-card">
                    <CardHeader>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                            <Globe class="h-6 w-6 text-primary" />
                        </div>
                        <CardTitle class="mt-4 font-heading">Track Progress</CardTitle>
                        <CardDescription>
                            Monitor your partnership status, invoices, and submissions in real-time.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-primary">
            <div class="mx-auto max-w-7xl px-6 py-16 text-center">
                <h2 class="font-heading text-3xl text-white">Ready to Partner with AHAIC?</h2>
                <p class="mt-4 text-lg text-white/80">
                    Ubuntu: We are because you are. Join us in transforming health across Africa.
                </p>
                <div class="mt-8">
                    <Link href="/packages">
                        <Button size="lg" class="bg-ahaic-yellow text-black hover:bg-ahaic-yellow/90">
                            Explore Packages
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border bg-white dark:bg-card">
            <div class="mx-auto max-w-7xl px-6 py-8">
                <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded bg-primary text-xs font-bold text-white">A</div>
                        <span class="text-sm text-muted-foreground">
                            &copy; {{ new Date().getFullYear() }} Amref Health Africa. All rights reserved.
                        </span>
                    </div>
                    <div class="flex gap-6 text-sm text-muted-foreground">
                        <a href="#" class="hover:text-foreground">Privacy Policy</a>
                        <a href="#" class="hover:text-foreground">Terms & Conditions</a>
                        <a href="#" class="hover:text-foreground">Contact</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
