<?php

declare(strict_types=1);

/**
 * Тарифы аэроэкспресса
 *
 * Поиска поездов у аэроэкспресса нет: место в тарифе обычно не фиксировано,
 * а билет действует несколько месяцев
 */

require __DIR__ . '/bootstrap.php';

$client = rzd();

$date = new DateTimeImmutable('+7 days');

$tariffs = attempt(fn() => $client->aeroexpress->tariffs($date));

heading(sprintf('Тарифы на %s: %d', $date->format('d.m.Y'), count($tariffs)));

foreach ($tariffs as $tariff) {
    printf(
        "%s %10s руб  %-18s до %2d билетов  %s\n",
        pad($tariff->name, 24),
        price($tariff->price),
        $tariff->type ?? '',
        $tariff->maxTickets ?? 0,
        $tariff->guaranteedSeat ? 'место фиксировано' : 'место не фиксировано',
    );
}

heading('Условия самого дешёвого тарифа');

usort($tariffs, static fn(object $a, object $b): int => ($a->price ?? INF) <=> ($b->price ?? INF));

$cheapest = $tariffs[0];

printf("%s, %s руб\n\n%s\n", $cheapest->name, price($cheapest->price), $cheapest->description);

heading('Принимаемые документы');

printf("%s\n", implode(', ', $cheapest->documentTypes));
