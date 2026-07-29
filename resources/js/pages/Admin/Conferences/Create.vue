<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const form = useForm({
    name: '',
    year: new Date().getFullYear(),
    start_date: '',
    end_date: '',
    venue: '',
    description: '',
    registration_deadline: '',
    onboarding_deadline: '',
    lock_date: '',
});

function submit() {
    form.post('/admin/conferences');
}
</script>

<template>
    <Head title="Create Conference" />

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <Link href="/admin/conferences" class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="h-4 w-4" />
                Back to Conferences
            </Link>
            <h1 class="font-heading text-2xl font-bold">Create Conference</h1>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Conference Details</CardTitle>
                <CardDescription>Set up a new conference for the AHAIC Partner Portal.</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="name">Conference Name</Label>
                        <Input id="name" v-model="form.name" placeholder="AHAIC 2026" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="year">Year</Label>
                        <Input id="year" v-model.number="form.year" type="number" min="2024" max="2030" />
                        <p v-if="form.errors.year" class="text-sm text-destructive">{{ form.errors.year }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="start_date">Start Date</Label>
                            <Input id="start_date" v-model="form.start_date" type="date" />
                            <p v-if="form.errors.start_date" class="text-sm text-destructive">{{ form.errors.start_date }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="end_date">End Date</Label>
                            <Input id="end_date" v-model="form.end_date" type="date" />
                            <p v-if="form.errors.end_date" class="text-sm text-destructive">{{ form.errors.end_date }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="venue">Venue</Label>
                        <Input id="venue" v-model="form.venue" placeholder="Kenyatta International Convention Centre" />
                        <p v-if="form.errors.venue" class="text-sm text-destructive">{{ form.errors.venue }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            placeholder="Conference description..."
                        />
                        <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="registration_deadline">Registration Deadline</Label>
                            <Input id="registration_deadline" v-model="form.registration_deadline" type="date" />
                        </div>
                        <div class="space-y-2">
                            <Label for="onboarding_deadline">Onboarding Deadline</Label>
                            <Input id="onboarding_deadline" v-model="form.onboarding_deadline" type="date" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="lock_date">Lock Date</Label>
                        <Input id="lock_date" v-model="form.lock_date" type="date" />
                        <p class="text-xs text-muted-foreground">After this date, partners cannot make changes to their submissions.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <Link href="/admin/conferences">
                            <Button variant="outline" type="button">Cancel</Button>
                        </Link>
                        <Button type="submit" :disabled="form.processing">
                            Create Conference
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
