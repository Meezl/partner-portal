<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, ArrowRight, Calendar, MapPin, Sparkles, Diamond, Crown, Award, Users, Mic } from 'lucide-vue-next';
import { computed } from 'vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { SponsorshipPackage, Conference } from '@/types/partner';

const props = defineProps<{
    packages: SponsorshipPackage[];
    conference: Conference;
}>();

const page = usePage();
const isLoggedIn = computed(() => !!page.props.auth?.user);

const tierColors: Record<string, string> = {
    diamond: 'border-cyan-300 bg-gradient-to-b from-cyan-50 to-white',
    platinum: 'border-slate-400 bg-gradient-to-b from-slate-50 to-white',
    gold: 'border-yellow-400 bg-gradient-to-b from-yellow-50 to-white',
    silver: 'border-gray-300 bg-gray-50',
    cso: 'border-primary bg-primary/5',
    exhibitor: 'border-accent bg-accent/5',
};

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
    <Head :title="conference ? `Partnership Packages - ${conference.name}` : `Partnership Packages`" />

    <div v-if="!conference" class="min-h-screen flex items-center justify-center bg-ahaic-offwhite">
        <div class="text-center p-8 bg-white rounded-lg shadow">
            <h2 class="text-2xl font-bold mb-2">No Active Conference</h2>
            <p class="text-muted-foreground">Please check back later or contact the administrator.</p>
        </div>
    </div>

    <div v-else class="min-h-screen bg-ahaic-offwhite">
        <!-- Header Nav -->
                <PublicHeader />

        <!-- Hero Section -->
        <section class="relative bg-primary px-6 py-20 text-white">
            <div class="mx-auto max-w-6xl text-center">
                <Badge variant="secondary" class="mb-4">
                    {{ conference.year }} Conference
                </Badge>
                <h1 class="font-heading text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                    {{ conference.name }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-white/80">
                    Partner with us to make a lasting impact on health in Africa
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-6 text-sm text-white/70">
                    <span class="flex items-center gap-2">
                        <Calendar class="h-4 w-4" />
                        {{ formatDate(conference.start_date) }} - {{ formatDate(conference.end_date) }}
                    </span>
                    <span class="flex items-center gap-2">
                        <MapPin class="h-4 w-4" />
                        {{ conference.venue }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Packages Grid -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div class="mb-12 text-center">
                <h2 class="font-heading text-3xl font-bold text-gray-900">
                    Sponsorship Packages
                </h2>
                <p class="mt-3 text-lg text-muted-foreground">
                    Choose the partnership tier that best aligns with your organization's goals
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="relative flex flex-col border-2 transition-shadow hover:shadow-lg"
                    :class="tierColors[pkg.tier] || ''"
                >
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <Badge
                                variant="outline"
                                :class="tierBadgeColors[pkg.tier] || ''"
                            >
                                {{ pkg.tier === 'cso' ? 'CSO' : pkg.tier.charAt(0).toUpperCase() + pkg.tier.slice(1) }}
                            </Badge>
                            <Diamond v-if="pkg.tier === 'diamond'" class="h-5 w-5 text-cyan-500" />
                            <Crown v-else-if="pkg.tier === 'platinum'" class="h-5 w-5 text-slate-500" />
                            <Sparkles v-else-if="pkg.tier === 'gold'" class="h-5 w-5 text-yellow-500" />
                        </div>
                        <CardTitle class="mt-2 text-xl">{{ pkg.name }}</CardTitle>
                        <CardDescription v-if="pkg.description">
                            {{ pkg.description }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex-1">
                        <div class="mb-6">
                            <span class="text-3xl font-bold text-gray-900">
                                {{ formatCurrency(pkg.price, pkg.currency) }}
                            </span>
                        </div>

                        <Separator class="mb-4" />

                        <div class="space-y-3 text-sm">
                            <!-- Key highlights -->
                            <div v-if="pkg.session_slots > 0" class="flex items-center gap-2">
                                <Mic class="h-4 w-4 text-primary" />
                                <span>{{ pkg.session_slots }} session slot{{ pkg.session_slots !== 1 ? 's' : '' }}</span>
                            </div>
                            <div v-if="pkg.exhibition_space" class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-primary" />
                                <span>{{ pkg.exhibition_space }}</span>
                            </div>
                            <div v-if="pkg.complimentary_registrations" class="flex items-center gap-2">
                                <Users class="h-4 w-4 text-primary" />
                                <span>
                                    {{ pkg.complimentary_registrations.total }} complimentary passes
                                    <template v-if="pkg.complimentary_registrations.vip > 0">
                                        ({{ pkg.complimentary_registrations.vip }} VIP + {{ pkg.complimentary_registrations.standard }} Standard)
                                    </template>
                                </span>
                            </div>

                            <!-- Thought leadership highlights (show first 2) -->
                            <template v-if="pkg.thought_leadership?.length">
                                <div
                                    v-for="(item, i) in pkg.thought_leadership.slice(0, 2)"
                                    :key="'tl-' + i"
                                    class="flex items-start gap-2"
                                >
                                    <Check class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    <span>{{ item }}</span>
                                </div>
                            </template>

                            <!-- Key benefits (show first 2) -->
                            <template v-if="pkg.benefits?.length">
                                <div
                                    v-for="(benefit, i) in pkg.benefits.slice(0, 2)"
                                    :key="'b-' + i"
                                    class="flex items-start gap-2"
                                >
                                    <Check class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    <span>{{ benefit }}</span>
                                </div>
                            </template>

                            <!-- More items indicator -->
                            <p v-if="(pkg.thought_leadership?.length ?? 0) + (pkg.benefits?.length ?? 0) + (pkg.visibility?.length ?? 0) > 4" class="text-xs text-primary font-medium">
                                + {{ (pkg.thought_leadership?.length ?? 0) + (pkg.benefits?.length ?? 0) + (pkg.visibility?.length ?? 0) - 4 }} more benefits...
                            </p>
                        </div>

                        <p
                            v-if="pkg.max_partners"
                            class="mt-4 text-xs text-muted-foreground"
                        >
                            Limited to {{ pkg.max_partners }} partners
                        </p>
                    </CardContent>

                    <CardFooter class="flex flex-col gap-2">
                        <Link :href="`/packages/${pkg.slug}`" class="w-full">
                            <Button variant="outline" class="w-full">
                                View Details
                            </Button>
                        </Link>
                        <Link
                            v-if="isLoggedIn"
                            href="/partner/expression-of-interest"
                            class="w-full"
                        >
                            <Button class="w-full">
                                Express Interest
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Button>
                        </Link>
                        <Link v-else href="/register" class="w-full">
                            <Button class="w-full">
                                Register to Partner
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Button>
                        </Link>
                    </CardFooter>
                </Card>
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
