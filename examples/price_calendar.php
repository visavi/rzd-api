<?php

declare(strict_types=1);

/**
 * Календари по датам: горизонт продажи, наличие мест, минимальные цены
 *
 * Прежний протокол такого не умел: приходилось перебирать даты запросами
 */

require __DIR__ . '/bootstrap.php';

$client = rzd();

$origin = '2000000';
$destination = '2004000';

heading('До какой даты открыта продажа');

foreach (attempt(fn() => $client->prices->saleCalendar($origin, $destination)) as $month) {
    $days = $month->saleDays;

    printf(
        "%d-%02d  дней в продаже %2d  %s\n",
        $month->year,
        $month->month,
        count($days),
        $days === [] ? 'продажа закрыта' : sprintf('с %d по %d числа', $days[0], end($days)),
    );
}

heading('Даты со свободными местами на три недели вперёд');

$dates = attempt(fn() => $client->prices->availability(
    $origin,
    $destination,
    new DateTimeImmutable('+1 day'),
    new DateTimeImmutable('+21 days'),
));

foreach (array_chunk($dates, 7) as $week) {
    printf("%s\n", implode('  ', array_map(
        static fn(DateTimeImmutable $date): string => $date->format('d.m'),
        $week,
    )));
}

heading('Минимальные цены по датам');

$days = attempt(fn() => $client->prices->calendar($origin, $destination, new DateTimeImmutable('+1 day')));

foreach ($days as $day) {
    $byType = [];

    foreach ($day->byCarType() as $type => $value) {
        $byType[] = sprintf('%s %s', $type, price($value));
    }

    printf(
        "%s  от %10s   %s\n",
        $day->date?->format('d.m.Y'),
        price($day->minPrice),
        implode('  ', $byType),
    );
}
