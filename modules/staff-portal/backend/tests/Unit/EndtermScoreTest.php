<?php

namespace Tests\Unit;

use Modules\Performance\Support\EndtermScore;
use Tests\TestCase;

class EndtermScoreTest extends TestCase
{
    public function test_score_is_sum_of_rating_times_weight_divided_by_five(): void
    {
        $rating = EndtermScore::fromObjectives([
            1 => ['appraiser_rating' => 4, 'weight' => 50],
            2 => ['appraiser_rating' => 5, 'weight' => 50],
        ]);

        $this->assertSame(90.0, $rating['score']);
        $this->assertSame('outstanding', $rating['category']);
        $this->assertSame('Outstanding Performance', $rating['label']);
    }

    public function test_satisfactory_and_poor_bands_match_ci3(): void
    {
        $satisfactory = EndtermScore::fromObjectives([
            1 => ['appraiser_rating' => 3, 'weight' => 100],
        ]);
        $this->assertSame(60.0, $satisfactory['score']);
        $this->assertSame('satisfactory', $satisfactory['category']);

        $poor = EndtermScore::fromObjectives([
            1 => ['appraiser_rating' => 2, 'weight' => 100],
        ]);
        $this->assertSame(40.0, $poor['score']);
        $this->assertSame('poor', $poor['category']);
    }

    public function test_empty_or_unrated_objectives_are_not_rated(): void
    {
        $empty = EndtermScore::fromObjectives([]);
        $this->assertSame(0.0, $empty['score']);
        $this->assertSame('not_rated', $empty['category']);

        $blank = EndtermScore::fromObjectives([
            1 => ['appraiser_rating' => '', 'weight' => 50],
        ]);
        $this->assertSame(0.0, $blank['score']);
        $this->assertSame('Not Rated – New in Position', $blank['label']);
    }

    public function test_json_string_objectives_are_decoded(): void
    {
        $json = json_encode([
            1 => ['appraiser_rating' => 5, 'weight' => 100],
        ]);
        $rating = EndtermScore::fromObjectives($json);

        $this->assertSame(100.0, $rating['score']);
        $this->assertSame('outstanding', $rating['category']);
    }
}
