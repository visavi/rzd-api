<?php

declare(strict_types=1);

/**
 * Справочник тарифов и конфигурация сайта
 */

require __DIR__ . '/bootstrap.php';

$client = rzd();

$tariffs = attempt(fn() => $client->references->tariffs());

heading(sprintf('Тарифов в справочнике: %d, из них действующих: %d', count($tariffs), count(array_filter(
    $tariffs,
    static fn(object $tariff): bool => $tariff->isActive(),
))));

foreach (array_slice($tariffs, 0, 20) as $tariff) {
    printf(
        "%-4d %s %-8s %-10s %s\n",
        $tariff->id,
        pad($tariff->sysName, 22),
        $tariff->category ?? '',
        $tariff->status ?? '',
        $tariff->nonRefundable ? 'невозвратный' : '',
    );
}

heading('Из конфигурации сайта');

$config = attempt(fn() => $client->references->appConfig());

printf("адрес подсказок станций   %s\n", $config['stations_search_url'] ?? '-');
printf("лимит пассажиров          %s\n", json_encode($config['passengers_limit'] ?? null));
printf("режим поиска              %s\n", $config['search_mode'] ?? '-');
printf("капча в поиске            %s\n", isset($config['search_captcha']) ? 'настроена' : 'нет');
