<?php

namespace App\Services\ActivityReport;

/**
 * Field guidance and mission-type extras aligned to the Africa CDC Activity Report template
 * and the RBM Platform reporting UI.
 */
class ActivityReportFormSchema
{
    /**
     * @return list<array{value: string, title: string, hint: string}>
     */
    public function missionTypes(): array
    {
        return [
            ['value' => 'official_engagement', 'title' => 'Official engagement', 'hint' => 'Visit to a country, institution or partner'],
            ['value' => 'training', 'title' => 'Training', 'hint' => 'Capacity building / training delivery'],
            ['value' => 'events', 'title' => 'Events', 'hint' => 'Workshops, meetings, conferences'],
            ['value' => 'procurement', 'title' => 'Procurement', 'hint' => 'Procurement-related activity'],
            ['value' => 'in_office', 'title' => 'In-office', 'hint' => 'In-office work without travel mission'],
            ['value' => 'recruitment_hr', 'title' => 'Recruitment & HR', 'hint' => 'Recruitment and HR processes'],
            ['value' => 'others', 'title' => 'Others', 'hint' => 'Any other activity type'],
        ];
    }

    /**
     * Extra fields keyed by mission_type. Empty list uses generic optional fields.
     *
     * @return array<string, list<array{key: string, label: string, hint: string, rows?: int}>>
     */
    public function extrasByMissionType(): array
    {
        return [
            'official_engagement' => [
                ['key' => 'visited_entity', 'label' => 'Name of the visited entity', 'hint' => 'e.g. Member State MoH, NPHI, partner institution', 'rows' => 2],
                ['key' => 'purpose_of_visit', 'label' => 'Purpose of the visit', 'hint' => 'Objective and strategic area / continental agenda contributed to (e.g. Country Engagement Visit)', 'rows' => 3],
                ['key' => 'key_issues_discussed', 'label' => 'Key issues discussed or observed', 'hint' => 'Discussion points, alignment / non-alignment, issues needing management attention', 'rows' => 4],
                ['key' => 'visit_outputs', 'label' => 'Key outputs / outcomes or results of the visit', 'hint' => 'Agreements reached, collaborations or partnerships forged', 'rows' => 3],
                ['key' => 'visit_follow_up', 'label' => 'Key recommendations and follow-up actions', 'hint' => 'Recommendations with expected timelines and responsible entities', 'rows' => 3],
            ],
            'events' => [
                ['key' => 'key_stakeholders', 'label' => 'Key participating stakeholders', 'hint' => 'Name partners or group by category (development partners, MoHs, NPHIs, private sector, NGOs)', 'rows' => 3],
                ['key' => 'key_deliberations', 'label' => 'Key discussions and deliberation points', 'hint' => 'Main deliberation and discussion points during the meeting', 'rows' => 4],
                ['key' => 'event_outputs', 'label' => 'Key outputs / outcomes or results of the meeting', 'hint' => 'e.g. validated continental framework, agreed joint workplan', 'rows' => 3],
                ['key' => 'event_follow_up', 'label' => 'Key follow-up actions', 'hint' => 'Follow-up actions with timelines and responsible entities', 'rows' => 3],
            ],
            'training' => [
                ['key' => 'trainees_description', 'label' => 'Description of the trainees', 'hint' => 'Intended audience e.g. Community Health Workers, Surveillance Officers, Laboratory technicians', 'rows' => 3],
                ['key' => 'delivery_methodology', 'label' => 'Description of delivery methodology', 'hint' => 'Brief description of how the training was delivered', 'rows' => 3],
                ['key' => 'trainees_disaggregation', 'label' => 'Number of trainees and disaggregation', 'hint' => 'Total trained; disaggregate by gender, region, Member States, etc.', 'rows' => 3],
                ['key' => 'participants_evaluation', 'label' => 'Summary of participants’ evaluation & feedback', 'hint' => 'Pre- and post-training knowledge gain comparison where available', 'rows' => 3],
                ['key' => 'training_improvements', 'label' => 'Proposed areas of improvement for the training', 'hint' => 'Changes to content, logistics or delivery methodology', 'rows' => 3],
            ],
            'procurement' => $this->genericExtras(),
            'in_office' => $this->genericExtras(),
            'recruitment_hr' => $this->genericExtras(),
            'others' => $this->genericExtras(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, hint: string, rows?: int}>
     */
    public function genericExtras(): array
    {
        return [
            ['key' => 'methodology', 'label' => 'Summary of key methodology', 'hint' => 'Approach, tools and participant engagement', 'rows' => 3],
            ['key' => 'indicator_achievement', 'label' => 'Achievement against target for the indicator', 'hint' => 'Target for this period, actual value achieved, % reached', 'rows' => 3],
            ['key' => 'lessons_learned', 'label' => 'Lessons learned', 'hint' => 'What worked well, what to improve', 'rows' => 3],
            ['key' => 'next_steps', 'label' => 'Next steps', 'hint' => 'Follow-up actions with timeline and responsible person', 'rows' => 3],
        ];
    }

    /**
     * Section / field guidance shown in the form.
     *
     * @return array<string, string>
     */
    public function fieldGuidance(): array
    {
        return [
            'background' => 'These fields come from the workplan / parent memo and RBM mapping. They are shown for context and are not edited here.',
            'mission_type' => 'Select the activity and session type. Additional specific questions will appear below based on your selection.',
            'dates' => 'Confirm start and end dates. Duration is calculated automatically.',
            'location' => 'Choose the location from the list and describe the venue (building / room). Prefills from the parent memo when available.',
            'organisers' => 'Pick the lead organiser and other Africa CDC team members from staff (do not type free text).',
            'objectives' => 'Pulled from the parent memo / CBP approval where available. Edit if needed (especially for In-office / Others).',
            'participants' => 'Enter the number of participants and the Member States covered by the activity.',
            'strategic_needs' => 'If applicable, link to Africa CDC strategic areas (2023–2027 / AHSS).',
            'narrative' => 'Key results achieved and impact on beneficiaries, as per the result in the RBM framework. Required for a complete report.',
            'challenges' => 'Challenges faced and how they were handled, if applicable.',
            'recommendations' => 'Actionable recommendations for management, if applicable.',
            'verification' => 'Upload supporting evidence (attendance lists, photos, reports, certificates). PDF preferred; images and video also accepted (max 20 MB each).',
            'logistics' => 'Budget code and total cost used. Prefills from the parent memo when the activity is not external-funded.',
            'extras' => 'Complete only the questions that apply to the selected activity type.',
            'related' => 'Tag other memos in the same reporting quarter for context. Tags do not merge form fields.',
        ];
    }

    /**
     * AU Member States for chips select.
     *
     * @return list<string>
     */
    public function memberStates(): array
    {
        return [
            'Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi',
            'Cabo Verde', 'Cameroon', 'Central African Republic', 'Chad', 'Comoros',
            'Congo', 'Côte d’Ivoire', 'Democratic Republic of the Congo', 'Djibouti',
            'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini', 'Ethiopia', 'Gabon',
            'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau', 'Kenya', 'Lesotho', 'Liberia',
            'Libya', 'Madagascar', 'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco',
            'Mozambique', 'Namibia', 'Niger', 'Nigeria', 'Rwanda', 'Sahrawi Arab Democratic Republic',
            'Sao Tome and Principe', 'Senegal', 'Seychelles', 'Sierra Leone', 'Somalia',
            'South Africa', 'South Sudan', 'Sudan', 'Tanzania', 'Togo', 'Tunisia',
            'Uganda', 'Zambia', 'Zimbabwe',
        ];
    }

    /**
     * @return array{location: string, venue: string}
     */
    public function parseLocationVenue(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['location' => '', 'venue' => ''];
        }
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return [
                    'location' => (string) ($decoded['location'] ?? ''),
                    'venue' => (string) ($decoded['venue'] ?? ''),
                ];
            }
        }
        if (str_contains($trimmed, ' — ')) {
            [$loc, $venue] = array_pad(explode(' — ', $trimmed, 2), 2, '');

            return ['location' => trim($loc), 'venue' => trim($venue)];
        }

        return ['location' => $trimmed, 'venue' => ''];
    }

    public function encodeLocationVenue(?string $location, ?string $venue): ?string
    {
        $location = trim((string) $location);
        $venue = trim((string) $venue);
        if ($location === '' && $venue === '') {
            return null;
        }
        if ($venue === '') {
            return $location;
        }
        if ($location === '') {
            return $venue;
        }

        return json_encode(['location' => $location, 'venue' => $venue], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{challenges: string, recommendations: string}
     */
    public function parseChallengesRecommendations(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['challenges' => '', 'recommendations' => ''];
        }
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return [
                    'challenges' => (string) ($decoded['challenges'] ?? ''),
                    'recommendations' => (string) ($decoded['recommendations'] ?? ''),
                ];
            }
        }

        return ['challenges' => $trimmed, 'recommendations' => ''];
    }

    public function encodeChallengesRecommendations(?string $challenges, ?string $recommendations): ?string
    {
        $challenges = trim((string) $challenges);
        $recommendations = trim((string) $recommendations);
        if ($challenges === '' && $recommendations === '') {
            return null;
        }
        if ($recommendations === '') {
            return $challenges;
        }

        return json_encode([
            'challenges' => $challenges,
            'recommendations' => $recommendations,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    public function parseMemberStates(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }
        }

        return array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $trimmed) ?: [])));
    }

    /**
     * @param  list<string>|null  $states
     */
    public function encodeMemberStates(?array $states): ?string
    {
        if ($states === null || $states === []) {
            return null;
        }
        $clean = array_values(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $states
        )));

        return $clean === [] ? null : implode(', ', $clean);
    }
}
