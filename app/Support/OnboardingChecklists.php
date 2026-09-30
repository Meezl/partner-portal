<?php

namespace App\Support;

/**
 * The tick-box lists partners fill in during onboarding.
 *
 * Keys are stored in the partners.exhibition_requirements and
 * branding_requirements.comms_checklist JSON columns, so they must not change
 * once data exists; labels are sent to the forms so the wording lives in one
 * place instead of being duplicated in Vue.
 */
class OnboardingChecklists
{
    /**
     * Exhibition needs beyond what the sponsorship package already covers.
     *
     * @return array<string, string>
     */
    public static function exhibition(): array
    {
        return [
            'additional_furniture' => 'Additional furniture',
            'additional_power' => 'Additional power/electrical points',
            'av_equipment' => 'AV equipment/screens',
            'branding_signage' => 'Branding/signage requirements',
            'storage_space' => 'Storage space',
            'additional_staff_passes' => 'Additional exhibition staff passes',
        ];
    }

    /**
     * Communications deliverables a partner can ask for.
     *
     * @return array<string, string>
     */
    public static function communications(): array
    {
        return [
            'logo_placement' => 'Logo placement on conference materials',
            'website_listing' => 'Listing on the AHAIC website',
            'social_media' => 'Social media mentions and amplification',
            'press_release' => 'Press release / media advisory support',
            'media_interviews' => 'Media interviews for our spokespeople',
            'newsletter_feature' => 'Feature in the conference newsletter',
            'programme_advert' => 'Advert in the conference programme',
            'branded_signage' => 'Branded signage at the venue',
            'photography_video' => 'Photography / video coverage of our session',
            'delegate_bag_insert' => 'Insert in the delegate bag',
        ];
    }

    /**
     * Validation rules for a checklist payload, e.g. rulesFor('exhibition_requirements', self::exhibition()).
     *
     * @param  array<string, string>  $options
     * @return array<string, array<int, string>>
     */
    public static function rulesFor(string $field, array $options): array
    {
        $rules = [
            $field => ['nullable', 'array'],
            "{$field}.other" => ['nullable', 'string', 'max:500'],
        ];

        foreach (array_keys($options) as $key) {
            $rules["{$field}.{$key}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * Normalise a submitted checklist: every option present as a boolean, plus
     * the free-text "Other" note. Keeps stored shape stable no matter which
     * boxes the browser sent.
     *
     * @param  array<string, string>  $options
     * @param  array<string, mixed>|null  $submitted
     * @return array<string, mixed>
     */
    public static function normalise(array $options, ?array $submitted): array
    {
        $submitted ??= [];
        $normalised = [];

        foreach (array_keys($options) as $key) {
            $normalised[$key] = filter_var($submitted[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $other = trim((string) ($submitted['other'] ?? ''));
        $normalised['other'] = $other === '' ? null : $other;

        return $normalised;
    }
}
