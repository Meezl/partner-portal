<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, FileSignature, Handshake, MapPin, Mic, Monitor, Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCalendarDate } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        /** The active conference, so dates and venue come from the record. */
        conference?: {
            name: string;
            year: number;
            start_date: string | null;
            end_date: string | null;
            venue: string | null;
        } | null;
    }>(),
    {
        canRegister: true,
        conference: null,
    },
);

const startDate = computed(() => props.conference?.start_date ?? '2027-02-28');
const endDate = computed(() => props.conference?.end_date ?? '2027-03-03');

const dateRange = computed(() => {
    const start = formatCalendarDate(startDate.value, { day: 'numeric', month: 'short' });
    const end = formatCalendarDate(endDate.value, { day: 'numeric', month: 'short', year: 'numeric' });

    return `${start} – ${end}`;
});

const venue = computed(() => props.conference?.venue ?? 'Kigali Convention Centre, Rwanda');
const year = computed(() => props.conference?.year ?? 2027);

/** Days until the conference opens; hidden once it has started. */
const daysToGo = ref<number | null>(null);
let ticker: ReturnType<typeof setInterval> | undefined;

function countDown() {
    const start = new Date(startDate.value).getTime();
    const days = Math.ceil((start - Date.now()) / 86_400_000);

    daysToGo.value = days > 0 ? days : null;
}

onMounted(() => {
    countDown();
    // An hour is plenty: the figure only ever changes once a day.
    ticker = setInterval(countDown, 3_600_000);
});

onBeforeUnmount(() => clearInterval(ticker));

const facts = computed(() => [
    { icon: MapPin, label: 'Location', value: venue.value, note: 'Kigali, Rwanda' },
    { icon: CalendarDays, label: 'Dates', value: dateRange.value, note: `Four full days, ${year.value}` },
    { icon: Monitor, label: 'Format', value: 'Hybrid conference', note: 'In-person & online streams' },
    { icon: Handshake, label: 'Convened by', value: 'Amref Health Africa', note: 'Government of Rwanda & partners' },
]);

const steps = [
    {
        icon: FileSignature,
        title: 'Express your interest',
        description:
            'Choose a sponsorship package, share your organization details, and sign your partnership agreement in the portal.',
    },
    {
        icon: Mic,
        title: 'Propose your sessions',
        description:
            'Submit session titles, formats and co-hosts, then request the date and time that suits your speakers.',
    },
    {
        icon: Users,
        title: 'Bring your team',
        description:
            'Register delegates, nominate your session and communications leads, and send us your branding assets.',
    },
    {
        icon: CalendarDays,
        title: 'Track everything',
        description:
            'Follow your onboarding progress, invoices and approved schedule in one place, right up to Kigali.',
    },
];
</script>

<template>
    <div class="min-h-screen bg-ahaic-offwhite dark:bg-background">
        <PublicHeader />
        <Head title="AHAIC 2027 Partner Portal" />

        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-ahaic-green/10">
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="
                    background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><polygon points=%2250,5 95,25 95,75 50,95 5,75 5,25%22 fill=%22none%22 stroke=%22%23255325%22 stroke-width=%221%22/></svg>');
                    background-size: 72px;
                "
            ></div>

            <div class="relative mx-auto max-w-6xl px-6 py-16 lg:py-24">
                <p class="font-mono text-xs tracking-[0.2em] text-ahaic-green/70 uppercase dark:text-ahaic-yellow">
                    Edition 06 / AHAIC {{ year }}
                </p>

                <h1 class="font-heading mt-6 text-3xl leading-tight tracking-tight text-ahaic-green sm:text-4xl lg:text-5xl dark:text-foreground">
                    Theme: From Dialogue to Delivery: Redesigning Africa's Ecosystem for a
                    <span class="text-ahaic-yellow italic">Healthy</span>,
                    <span class="text-ahaic-green italic dark:text-ahaic-lightblue">Sovereign Future</span>
                </h1>

                <p class="mt-6 max-w-2xl text-base text-ahaic-brown/80 lg:text-lg dark:text-muted-foreground">
                    This is the partner portal for AHAIC {{ year }}. Sponsors, exhibitors and
                    civil society partners use it to confirm a package, sign their agreement,
                    propose sessions and get their team to Kigali.
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <Link href="/packages">
                        <Button size="lg" class="bg-ahaic-green text-white hover:bg-ahaic-green/90">
                            View sponsorship packages
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>
                    </Link>
                    <Link v-if="canRegister" href="/register">
                        <Button size="lg" class="bg-ahaic-yellow text-black hover:bg-ahaic-yellow/90">
                            Become a partner
                        </Button>
                    </Link>
                    <p v-if="daysToGo" class="text-sm text-ahaic-brown/70 dark:text-muted-foreground">
                        <span class="font-heading text-xl text-ahaic-green dark:text-ahaic-yellow">{{ daysToGo }}</span>
                        days to go
                    </p>
                </div>
            </div>
        </section>

        <!-- Conference facts -->
        <section class="border-b border-ahaic-green/10 bg-white dark:bg-card">
            <div class="mx-auto grid max-w-6xl gap-px px-6 py-10 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="fact in facts" :key="fact.label" class="px-2 py-3">
                    <div class="flex items-center gap-2 text-ahaic-green dark:text-ahaic-yellow">
                        <component :is="fact.icon" class="h-4 w-4" />
                        <span class="font-mono text-[11px] tracking-[0.15em] uppercase">{{ fact.label }}</span>
                    </div>
                    <p class="mt-2 font-heading text-base text-foreground">{{ fact.value }}</p>
                    <p class="text-sm text-muted-foreground">{{ fact.note }}</p>
                </div>
            </div>
        </section>

        <!-- What partners do here -->
        <section class="mx-auto max-w-6xl px-6 py-20">
            <div class="max-w-2xl">
                <p class="font-mono text-xs tracking-[0.2em] text-ahaic-green/70 uppercase dark:text-ahaic-yellow">
                    The partner journey
                </p>
                <h2 class="font-heading mt-3 text-3xl text-foreground">
                    From expression of interest to the room in Kigali
                </h2>
                <p class="mt-4 text-muted-foreground">
                    Everything your organization needs to do before AHAIC {{ year }} happens here,
                    in the order it needs doing. Your progress is saved as you go.
                </p>
            </div>

            <div class="mt-14 grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                <Card v-for="(step, index) in steps" :key="step.title" class="border-none bg-white shadow-sm dark:bg-card">
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-ahaic-green/10">
                                <component :is="step.icon" class="h-5 w-5 text-ahaic-green dark:text-ahaic-yellow" />
                            </div>
                            <span class="font-mono text-xs text-muted-foreground">0{{ index + 1 }}</span>
                        </div>
                        <CardTitle class="font-heading mt-4">{{ step.title }}</CardTitle>
                        <CardDescription>{{ step.description }}</CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </section>

        <!-- Closing call to action -->
        <section class="bg-ahaic-green">
            <div class="mx-auto max-w-6xl px-6 py-16 text-center">
                <p class="font-mono text-xs tracking-[0.2em] text-white/60 uppercase">
                    The room won't be the same without you
                </p>
                <h2 class="font-heading mt-4 text-3xl text-white">
                    Join us in Kigali, {{ formatCalendarDate(startDate, { month: 'long', year: 'numeric' }) }}
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-white/80">
                    Ubuntu: we are because you are. Every partner in the room moves the agenda forward.
                </p>
                <div class="mt-8 flex flex-wrap justify-center gap-4">
                    <Link href="/packages">
                        <Button size="lg" class="bg-ahaic-yellow text-black hover:bg-ahaic-yellow/90">
                            Explore packages
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>
                    </Link>
                    <Link href="/visa-letter">
                        <Button size="lg" variant="outline" class="border-white/40 bg-transparent text-white hover:bg-white/10">
                            Request a visa letter
                        </Button>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border bg-white dark:bg-card">
            <div class="mx-auto max-w-6xl px-6 py-8">
                <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded bg-ahaic-green text-xs font-bold text-white">A</div>
                        <span class="text-sm text-muted-foreground">
                            &copy; {{ new Date().getFullYear() }} Amref Health Africa. All rights reserved.
                        </span>
                    </div>
                    <div class="flex gap-6 text-sm text-muted-foreground">
                        <a href="https://ahaic.org" target="_blank" rel="noopener" class="hover:text-foreground">ahaic.org</a>
                        <Link href="/packages" class="hover:text-foreground">Packages</Link>
                        <Link href="/visa-letter" class="hover:text-foreground">Visa letter</Link>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
