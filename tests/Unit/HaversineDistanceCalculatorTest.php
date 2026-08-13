<?php

namespace Tests\Unit;

use App\Services\Geo\HaversineDistanceCalculator;
use Tests\TestCase;

class HaversineDistanceCalculatorTest extends TestCase
{
    private HaversineDistanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(HaversineDistanceCalculator::class);
    }

    public function test_identical_points_are_zero_km_apart(): void
    {
        $this->assertSame(0.0, $this->calculator->distanceInKm(33.8938, 35.5018, 33.8938, 35.5018));
    }

    public function test_known_distance_between_beirut_and_tripoli_lebanon(): void
    {
        // Beirut (33.8938, 35.5018) to Tripoli, Lebanon (34.4367, 35.8497) —
        // straight-line (not driving) distance, ~68.3km.
        $distance = $this->calculator->distanceInKm(33.8938, 35.5018, 34.4367, 35.8497);

        $this->assertEqualsWithDelta(68.3, $distance, 1.0);
    }

    public function test_known_distance_between_new_york_and_london(): void
    {
        // A well-known reference pair: ~5570km great-circle distance.
        $distance = $this->calculator->distanceInKm(40.7128, -74.0060, 51.5074, -0.1278);

        $this->assertEqualsWithDelta(5570, $distance, 20);
    }

    public function test_distance_is_symmetric(): void
    {
        $aToB = $this->calculator->distanceInKm(33.8938, 35.5018, 34.4367, 35.8497);
        $bToA = $this->calculator->distanceInKm(34.4367, 35.8497, 33.8938, 35.5018);

        $this->assertSame($aToB, $bToA);
    }

    public function test_points_across_the_antimeridian_are_still_computed_correctly(): void
    {
        // 179.9E to -179.9E are ~0.2 degrees of longitude apart going the
        // short way around, not ~360 degrees the naive way.
        $distance = $this->calculator->distanceInKm(0, 179.9, 0, -179.9);

        $this->assertLessThan(50, $distance);
    }
}
