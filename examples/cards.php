<?php

declare(strict_types=1);

/**
 * Карты и абонементы перевозчиков
 */

require __DIR__ . '/bootstrap.php';

$client = rzd();

$cards = attempt(fn() => $client->references->cards());

$discount = array_filter($cards, static fn(object $card): bool => ! $card->isPass());
$passes = array_filter($cards, static fn(object $card): bool => $card->isPass());

heading(sprintf('Всего карт %d: скидочных %d, абонементов %d', count($cards), count($discount), count($passes)));

heading('Скидочные карты, самые дешёвые');

usort($discount, static fn(object $a, object $b): int => ($a->price ?? INF) <=> ($b->price ?? INF));

foreach (array_slice($discount, 0, 10) as $card) {
    printf(
        "%-5s %s %10s руб  скидка %2d%%  %3d дней  %s\n",
        $card->code,
        pad($card->name, 30),
        price($card->price),
        $card->discount ?? 0,
        $card->activeDays ?? 0,
        implode(', ', $card->carTypes),
    );
}

heading('Абонементы на поездки, самые дешёвые');

usort($passes, static fn(object $a, object $b): int => ($a->price ?? INF) <=> ($b->price ?? INF));

foreach (array_slice($passes, 0, 10) as $card) {
    printf(
        "%-5s %s %10s руб  %2d поездок  %3d дней\n",
        $card->code,
        pad($card->name, 30),
        price($card->price),
        $card->tripQuantity ?? 0,
        $card->activeDays ?? 0,
    );
}

heading('Карты для купе с наибольшей скидкой');

$compartment = array_filter($cards, static fn(object $card): bool => $card->fitsCarType('Compartment'));

usort($compartment, static fn(object $a, object $b): int => ($b->discount ?? 0) <=> ($a->discount ?? 0));

foreach (array_slice($compartment, 0, 5) as $card) {
    printf(
        "%-5s %s скидка %2d%%  %s\n",
        $card->code,
        pad($card->name, 30),
        $card->discount ?? 0,
        $card->refundable ? 'возврат разрешён' : 'без возврата',
    );
}
