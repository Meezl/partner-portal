<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Star, Send, MessageSquare } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, Conference, ConferenceSession } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    conference: Conference;
    sessions: ConferenceSession[];
}>();

const form = useForm({
    overall_rating: 0,
    organization_rating: 0,
    communication_rating: 0,
    venue_rating: 0,
    overall_comments: '',
    improvement_suggestions: '',
    session_feedback: props.sessions.map((s) => ({
        conference_session_id: s.id,
        title: s.title,
        rating: 0,
        comments: '',
    })),
});

function setRating(field: 'overall_rating' | 'organization_rating' | 'communication_rating' | 'venue_rating', value: number) {
    form[field] = value;
}

function setSessionRating(index: number, value: number) {
    form.session_feedback[index].rating = value;
}

function submit() {
    form.post('/partner/feedback');
}

const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

const ratingQuestions = [
    { key: 'overall_rating' as const, label: 'Overall Experience', description: 'How would you rate your overall conference experience?' },
    { key: 'organization_rating' as const, label: 'Organization & Logistics', description: 'How well organized was the conference?' },
    { key: 'communication_rating' as const, label: 'Communication', description: 'How was the communication from the conference team?' },
    { key: 'venue_rating' as const, label: 'Venue & Facilities', description: 'How would you rate the venue and facilities?' },
];
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Conference Feedback</h1>
            <p class="text-muted-foreground mt-1">Share your experience at {{ conference.name }}. Your feedback helps us improve.</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Overall Ratings</CardTitle>
                <CardDescription>Rate different aspects of your conference experience.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-8">
                <div v-for="question in ratingQuestions" :key="question.key" class="space-y-2">
                    <div>
                        <Label class="text-base">{{ question.label }}</Label>
                        <p class="text-muted-foreground text-sm">{{ question.description }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-for="i in 5"
                            :key="i"
                            type="button"
                            class="focus:outline-none"
                            @click="setRating(question.key, i)"
                        >
                            <Star
                                class="h-8 w-8 transition-colors"
                                :class="i <= form[question.key] ? 'fill-amber-400 text-amber-400' : 'text-gray-300'"
                            />
                        </button>
                        <span v-if="form[question.key] > 0" class="text-muted-foreground ml-2 text-sm">
                            {{ ratingLabels[form[question.key]] }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Comments</CardTitle>
                <CardDescription>Share additional thoughts about your experience.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <Label for="overall_comments">Overall Comments</Label>
                    <textarea
                        id="overall_comments"
                        v-model="form.overall_comments"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        placeholder="Share your thoughts on the overall conference experience..."
                    />
                </div>

                <div class="space-y-2">
                    <Label for="improvement_suggestions">Suggestions for Improvement</Label>
                    <textarea
                        id="improvement_suggestions"
                        v-model="form.improvement_suggestions"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        placeholder="What could we do better next time?"
                    />
                </div>
            </CardContent>
        </Card>

        <Card v-if="sessions.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <MessageSquare class="h-5 w-5" />
                    Session Feedback
                </CardTitle>
                <CardDescription>Rate and comment on your individual sessions.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div v-for="(sf, index) in form.session_feedback" :key="sf.conference_session_id">
                    <div class="rounded-lg border p-4">
                        <h4 class="mb-3 font-medium">{{ sf.title }}</h4>
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <button
                                    v-for="i in 5"
                                    :key="i"
                                    type="button"
                                    class="focus:outline-none"
                                    @click="setSessionRating(index, i)"
                                >
                                    <Star
                                        class="h-6 w-6 transition-colors"
                                        :class="i <= sf.rating ? 'fill-amber-400 text-amber-400' : 'text-gray-300'"
                                    />
                                </button>
                                <span v-if="sf.rating > 0" class="text-muted-foreground ml-2 text-sm">
                                    {{ ratingLabels[sf.rating] }}
                                </span>
                            </div>
                            <textarea
                                v-model="sf.comments"
                                rows="2"
                                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                                placeholder="Comments about this session..."
                            />
                        </div>
                    </div>
                    <Separator v-if="index < form.session_feedback.length - 1" class="mt-4" />
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end">
            <Button size="lg" @click="submit" :disabled="form.processing">
                <Send class="mr-2 h-4 w-4" />
                Submit Feedback
            </Button>
        </div>
    </div>
</template>
