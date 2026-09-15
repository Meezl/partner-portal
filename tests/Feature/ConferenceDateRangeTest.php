<?php

use App\Models\Conference;

function conferenceRunning(?string $start, ?string $end): Conference
{
    return new Conference(['start_date' => $start, 'end_date' => $end]);
}

it('formats the conference dates as one range', function (?string $start, ?string $end, ?string $expected) {
    expect(conferenceRunning($start, $end)->dateRange())->toBe($expected);
})->with([
    'within a month' => ['2027-03-02', '2027-03-05', 'March 2 – 5, 2027'],
    'across a month' => ['2027-02-28', '2027-03-03', 'February 28 – March 3, 2027'],
    'across a year' => ['2026-12-30', '2027-01-02', 'December 30, 2026 – January 2, 2027'],
    'a single day' => ['2027-03-02', '2027-03-02', 'March 2, 2027'],
    'no end date' => ['2027-03-02', null, null],
]);
