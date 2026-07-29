<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, ArrowLeft, ArrowRight, Sparkles, Users, LayoutGrid, Diamond, Crown, Mic, Eye, Award } from 'lucide-vue-next';
import { computed } from 'vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { SponsorshipPackage, Conference } from '@/types/partner';

const props = defineProps<{
    package: SponsorshipPackage;
    conference: Conference;
}>();

const page = usePage();
const isLoggedIn = computed(() => !!page.props.auth?.user);

const pkg = computed(() => props.package);

const tierBadgeColors: Record<string, string> = {
    diamond: 'bg-cyan-100 text-cyan-800 border-cyan-300',
    platinum: 'bg-slate-100 text-slate-700 border-slate-400',
    gold: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    silver: 'bg-gray-100 text-gray-700 border-gray-300',
    cso: 'bg-primary/10 text-primary border-primary/30',
    exhibitor: 'bg-accent/10 text-accent border-accent/30',
};

function formatCurrency(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0,
    }).format(amount);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="`${pkg.name} - ${conference.name}`" />

    <div class="min-h-screen bg-ahaic-offwhite">
        <PublicHeader />

        <!-- Hero Header -->
        <section class="bg-primary px-6 py-12 text-white">
            <div class="mx-auto max-w-6xl">
                <Link href="/packages" class="mb-4 inline-flex items-center gap-1 text-sm text-white/70 hover:text-white">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Packages
                </Link>
                <div class="flex items-center gap-3">
                    <h1 class="font-heading text-3xl font-bold sm:text-4xl">
                        {{ pkg.name }}
                    </h1>
                    <Badge
                        variant="outline"
                        :class="tierBadgeColors[pkg.tier] || ''"
                    >
                        {{ pkg.tier === 'cso' ? 'CSO' : pkg.tier.charAt(0).toUpperCase() + pkg.tier.slice(1) }}
                    </Badge>
                    <Diamond v-if="pkg.tier === 'diamond'" class="h-5 w-5 text-cyan-300" />
                    <Crown v-else-if="pkg.tier === 'platinum'" class="h-5 w-5 text-slate-300" />
                    <Sparkles v-else-if="pkg.tier === 'gold'" class="h-5 w-5 text-yellow-300" />
                </div>
                <p class="mt-2 text-white/70">
                    {{ conference.name }} &middot; {{ formatDate(conference.start_date) }} - {{ formatDate(conference.end_date) }}
                </p>
            </div>
        </section>

        <!-- Content -->
        <section class="mx-auto max-w-6xl px-6 py-12">
            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main Details -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Package Description -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Package Description</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-muted-foreground leading-relaxed">
                                {{ pkg.description || 'No description available.' }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- What's Included — Key Highlights -->
                    <Card>
                        <CardHeader>
                            <CardTitle>What's Included</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div v-if="pkg.session_slots > 0" class="rounded-xl border bg-muted/30 p-5">
                                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Mic class="h-5 w-5 text-primary" />
                                    </div>
                                    <p class="text-base font-semibold">Session Slots</p>
                                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                                        {{ pkg.session_slots }} dedicated session slot{{ pkg.session_slots !== 1 ? 's' : '' }} during the conference
                                    </p>
                                </div>

                                <div v-if="pkg.exhibition_space" class="rounded-xl border bg-muted/30 p-5">
                                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <LayoutGrid class="h-5 w-5 text-primary" />
                                    </div>
                                    <p class="text-base font-semibold">Exhibition Space</p>
                                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ pkg.exhibition_space }}</p>
                                </div>

                                <div v-if="pkg.complimentary_registrations" class="rounded-xl border bg-muted/30 p-5">
                                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Users class="h-5 w-5 text-primary" />
                                    </div>
                                    <p class="text-base font-semibold">Complimentary Passes</p>
                                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                                        {{ pkg.complimentary_registrations.total }} total registrations
                                        <template v-if="pkg.complimentary_registrations.vip > 0">
                                            <br />{{ pkg.complimentary_registrations.vip }} VIP + {{ pkg.complimentary_registrations.standard }} Standard
                                        </template>
                                    </p>
                                </div>

                                <div v-if="pkg.max_partners" class="rounded-xl border bg-muted/30 p-5">
                                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Award class="h-5 w-5 text-primary" />
                                    </div>
                                    <p class="text-base font-semibold">Limited Availability</p>
                                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                                        Only {{ pkg.max_partners }} partnership spots available
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Thought Leadership -->
                    <Card v-if="pkg.thought_leadership && pkg.thought_leadership.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Mic class="h-5 w-5 text-primary" />
                                Thought Leadership & Speaking
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul class="space-y-3">
                                <li
                                    v-for="(item, i) in pkg.thought_leadership"
                                    :key="'tl-' + i"
                                    class="flex items-start gap-3 text-sm leading-relaxed"
                                >
                                    <Check class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <!-- Visibility & Presence -->
                    <Card v-if="pkg.visibility && pkg.visibility.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Eye class="h-5 w-5 text-primary" />
                                Visibility & Presence
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul class="space-y-3">
                                <li
                                    v-for="(item, i) in pkg.visibility"
                                    :key="'vis-' + i"
                                    class="flex items-start gap-3 text-sm leading-relaxed"
                                >
                                    <Check class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <!-- Additional Benefits -->
                    <Card v-if="pkg.benefits && pkg.benefits.length > 0">
                        <CardHeader>
                            <CardTitle>Additional Benefits</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul class="space-y-3">
                                <li
                                    v-for="(benefit, i) in pkg.benefits"
                                    :key="'b-' + i"
                                    class="flex items-start gap-3 text-sm leading-relaxed"
                                >
                                    <Check class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    <span>{{ benefit }}</span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar — Package Price & Quick Facts -->
                <div class="space-y-6">
                    <Card class="sticky top-6 overflow-hidden">
                        <!-- Price Header -->
                        <div class="bg-primary px-6 py-5 text-center text-white">
                            <p class="text-sm font-medium text-white/70">Package Price</p>
                            <p class="mt-1 text-4xl font-bold tracking-tight">
                                {{ formatCurrency(pkg.price, pkg.currency) }}
                            </p>
                        </div>

                        <CardContent class="p-6">
                            <!-- Quick Facts -->
                            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Package Details</h4>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2">
                                    <span class="text-muted-foreground">Tier</span>
                                    <Badge variant="outline" :class="tierBadgeColors[pkg.tier] || ''">
                                        {{ pkg.tier === 'cso' ? 'CSO' : pkg.tier.charAt(0).toUpperCase() + pkg.tier.slice(1) }}
                                    </Badge>
                                </div>
                                <div v-if="pkg.session_slots > 0" class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2">
                                    <span class="text-muted-foreground">Sessions</span>
                                    <span class="font-semibold">{{ pkg.session_slots }} slot{{ pkg.session_slots !== 1 ? 's' : '' }}</span>
                                </div>
                                <div v-if="pkg.exhibition_space" class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2">
                                    <span class="text-muted-foreground">Exhibition</span>
                                    <span class="font-semibold text-right max-w-[55%]">{{ pkg.exhibition_space }}</span>
                                </div>
                                <div v-if="pkg.complimentary_registrations" class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2">
                                    <span class="text-muted-foreground">Passes</span>
                                    <span class="font-semibold">{{ pkg.complimentary_registrations.total }} included</span>
                                </div>
                                <div v-if="pkg.max_partners" class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2">
                                    <span class="text-muted-foreground">Availability</span>
                                    <span class="font-semibold">{{ pkg.max_partners }} spots</span>
                                </div>
                            </div>

                            <Separator class="my-5" />

                            <!-- CTA Buttons -->
                            <div class="space-y-3">
                                <Link
                                    v-if="isLoggedIn"
                                    href="/partner/expression-of-interest"
                                    class="block"
                                >
                                    <Button class="w-full" size="lg">
                                        Express Interest
                                        <ArrowRight class="ml-2 h-4 w-4" />
                                    </Button>
                                </Link>
                                <Link v-else href="/register" class="block">
                                    <Button class="w-full" size="lg">
                                        Register to Partner
                                        <ArrowRight class="ml-2 h-4 w-4" />
                                    </Button>
                                </Link>
                                <Link href="/packages" class="block">
                                    <Button variant="outline" class="w-full" size="sm">
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        View All Packages
                                    </Button>
                                </Link>
                            </div>

                            <!-- Custom packages note -->
                            <p class="mt-4 text-center text-xs leading-relaxed text-muted-foreground">
                                Customized packages are available and include hosting dinner or cocktail receptions, having a launch event amongst others.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>
    </div>
</template>
