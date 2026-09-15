<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Calendar, FileDown, MapPin } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import BotGuardFields from '@/components/shared/BotGuardFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    conference: { name: string; start_date: string | null; end_date: string | null; venue: string | null } | null;
    csrfToken: string;
    old: { customer_name: string | null; passport_number: string | null };
    errors: Record<string, string>;
}>();

const page = usePage();
const flashError = computed(() => (page.props.flash as { error?: string } | undefined)?.error);

// A bot-guard or throttle rejection is not about any one field, so it shows above the form.
const formError = computed(() => props.errors.throttle || props.errors.website_url || flashError.value);

function formatDate(date: string | null): string {
    return date
        ? new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
        : 'TBC';
}

// The form is a native POST whose response is a file download, so the page
// never navigates away. Briefly disable the button to stop double-submits.
const submitting = ref(false);

function onSubmit() {
    submitting.value = true;
    setTimeout(() => (submitting.value = false), 4000);
}
</script>

<template>
    <Head title="Visa Invitation Letter">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="min-h-screen bg-ahaic-offwhite dark:bg-background">
        <PublicHeader />

        <main class="mx-auto max-w-2xl px-6 py-12">
            <Card v-if="!conference">
                <CardHeader>
                    <CardTitle>Visa letters are not available</CardTitle>
                    <CardDescription>There is no conference open for registration right now. Please check back later.</CardDescription>
                </CardHeader>
            </Card>

            <Card v-else>
                <CardHeader>
                    <CardTitle class="font-heading text-2xl text-primary">Visa Invitation Letter</CardTitle>
                    <CardDescription>
                        Generate an invitation letter to support your visa application for {{ conference.name }}.
                    </CardDescription>
                    <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted-foreground">
                        <span class="inline-flex items-center gap-1.5">
                            <Calendar class="h-4 w-4" />
                            {{ formatDate(conference.start_date) }} – {{ formatDate(conference.end_date) }}
                        </span>
                        <span v-if="conference.venue" class="inline-flex items-center gap-1.5">
                            <MapPin class="h-4 w-4" />
                            {{ conference.venue }}
                        </span>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="formError"
                        class="mb-6 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        role="alert"
                    >
                        {{ formError }}
                    </div>

                    <form method="post" action="/visa-letter" class="relative flex flex-col gap-6" @submit="onSubmit">
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <BotGuardFields />

                        <div class="grid gap-2">
                            <Label for="customer_name">Full name</Label>
                            <Input
                                id="customer_name"
                                name="customer_name"
                                required
                                autofocus
                                maxlength="150"
                                autocomplete="name"
                                :default-value="old.customer_name ?? undefined"
                            />
                            <p class="text-xs text-muted-foreground">Exactly as it appears in your passport.</p>
                            <InputError :message="errors.customer_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="passport_number">Passport number</Label>
                            <Input
                                id="passport_number"
                                name="passport_number"
                                required
                                maxlength="20"
                                autocomplete="off"
                                class="uppercase"
                                :default-value="old.passport_number ?? undefined"
                            />
                            <InputError :message="errors.passport_number" />
                        </div>

                        <Button type="submit" class="w-full sm:w-auto sm:self-start" :disabled="submitting">
                            <FileDown class="h-4 w-4" />
                            {{ submitting ? 'Preparing your letter…' : 'Download letter' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </main>
    </div>
</template>
