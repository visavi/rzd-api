<?php

declare(strict_types=1);

/**
 * Поиск с пересадками
 *
 * Обычный поиск отдаёт только прямые поезда. Здесь сайт сам строит цепочки
 * из нескольких рейсов и считает переезды между вокзалами
 */

require __DIR__ . '/bootstrap.php';

use Rzd\Request\TransferSearch;

$client = rzd();

// Города задаются идентификаторами узлов сайта, их отдаёт подсказка станций
$result = attempt(fn() => $client->transfers->search(new TransferSearch(
    origin: '5a13bdc3340c745ca1e8aa54',      // Новый Уренгой
    destination: '5a13baab340c745ca1e7f31c', // Абакан
    date: new DateTimeImmutable('+21 days'),
)));

heading(sprintf('Вариантов поездки: %d', count($result)));

foreach ($result as $number => $route) {
    printf(
        "%d. %s → %s  %s  пересадок %d  от %s\n",
        $number + 1,
        pad($route->origin()?->name, 24),
        pad($route->destination()?->name, 24),
        duration($route->duration()),
        $route->changes(),
        price($route->minPrice),
    );
}

$route = $result->cheapest();

if ($route === null) {
    printf("\nПодходящих вариантов нет\n");

    return;
}

heading('Самый дешёвый вариант по рейсам');

foreach ($route->trips() as $trip) {
    printf(
        "%-8s %s %s → %s %s  %3d мест  от %10s\n",
        $trip->number,
        pad($trip->origin?->name, 24),
        $trip->departure?->format('d.m H:i'),
        $trip->arrival?->format('d.m H:i'),
        pad($trip->destination?->name, 24),
        $trip->freePlaces,
        price($trip->minPrice),
    );

    foreach ($trip->products as $product) {
        printf("    %-14s %3d мест  %10s\n", $product->type, $product->freePlaces, price($product->price));
    }
}

heading('Пересадки');

printf("ожидание между рейсами, всего %s\n", duration($route->waitTotal()));

$trips = $route->trips();

foreach ($route->waits() as $index => $wait) {
    printf("  %s  %s\n", pad($trips[$index]->destination?->name, 24), duration($wait));
}

if ($route->transfers === []) {
    printf("\nпереезжать между вокзалами не нужно\n");
}

foreach ($route->transfers as $transfer) {
    printf(
        "\nпереезд %s → %s, %s, около %s\n",
        $transfer->origin?->name,
        $transfer->destination?->name,
        duration($transfer->duration),
        price($transfer->price),
    );
}

heading('Подробности поезда первого плеча');

$train = $route->trips()[0]->train();

if ($train === null) {
    printf("рейс обслуживается не поездом\n");

    return;
}

printf("%s %s, %s\n", $train->number, $train->name ?? $train->description, $train->carriers[0] ?? '-');

foreach ($train->carGroups as $group) {
    printf("  %s %3d мест  от %10s\n", pad($group->typeName, 14), $group->places, price($group->minPrice));
}
