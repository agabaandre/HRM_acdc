<?php

use App\Services\ActivityReport\ActivityReportFormSchema;

it('defines mission types and type-specific extras from the template', function () {
    $schema = new ActivityReportFormSchema;
    $types = collect($schema->missionTypes())->pluck('value')->all();

    expect($types)->toContain('official_engagement')
        ->and($types)->toContain('training')
        ->and($types)->toContain('events')
        ->and($schema->extrasByMissionType()['official_engagement'])->not->toBeEmpty()
        ->and($schema->extrasByMissionType()['training'][0]['key'])->toBe('trainees_description')
        ->and($schema->extrasByMissionType()['events'][0]['key'])->toBe('key_stakeholders')
        ->and($schema->memberStates())->toContain('Ethiopia')
        ->and($schema->memberStates())->toContain('Nigeria');
});

it('round-trips location venue and challenges recommendations encodings', function () {
    $schema = new ActivityReportFormSchema;

    $encodedLoc = $schema->encodeLocationVenue('Addis Ababa', 'HQ Room 8');
    $parsedLoc = $schema->parseLocationVenue($encodedLoc);
    expect($parsedLoc['location'])->toBe('Addis Ababa')
        ->and($parsedLoc['venue'])->toBe('HQ Room 8');

    $encodedCh = $schema->encodeChallengesRecommendations('Delay', 'Hire more staff');
    $parsedCh = $schema->parseChallengesRecommendations($encodedCh);
    expect($parsedCh['challenges'])->toBe('Delay')
        ->and($parsedCh['recommendations'])->toBe('Hire more staff');

    expect($schema->parseLocationVenue('Nairobi — Hilton'))->toMatchArray([
        'location' => 'Nairobi',
        'venue' => 'Hilton',
    ]);
});
