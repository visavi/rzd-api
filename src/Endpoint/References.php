<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use Rzd\Model\Card;
use Rzd\Model\Tariff;

/**
 * Справочники сайта
 */
final readonly class References extends Endpoint
{
    private const TARIFFS_PATH = '/apib2b/p/Info/V1/References/Tariffs';
    private const CARDS_PATH = '/api/v1/railway-service/prices/cards';
    private const CONFIG_PATH = '/api/v1/app_config';

    /**
     * Справочник тарифов
     *
     * @return list<Tariff>
     */
    public function tariffs(): array
    {
        $response = $this->transport->post(self::TARIFFS_PATH, [], $this->serviceProvider());

        return array_values(array_map(
            static fn(array $tariff): Tariff => Tariff::fromArray($tariff),
            array_filter($response['Tariffs'] ?? [], 'is_array'),
        ));
    }

    /**
     * Карты и абонементы перевозчиков
     *
     * Дают скидку на билеты или фиксированное число поездок
     *
     * @return list<Card>
     */
    public function cards(): array
    {
        $response = $this->transport->get(self::CARDS_PATH);

        return array_values(array_map(
            static fn(array $card): Card => Card::fromArray($card),
            array_filter($response['Cards'] ?? [], 'is_array'),
        ));
    }

    /**
     * Конфигурация сайта
     *
     * Содержит флаги доступности функций, лимиты пассажиров, адреса
     * справочников. Возвращается как есть: набор ключей меняется сайтом
     * и в модель его закреплять смысла нет
     *
     * @return array<string, mixed>
     */
    public function appConfig(): array
    {
        return $this->transport->get(self::CONFIG_PATH);
    }
}
