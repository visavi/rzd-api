<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use Rzd\Model\TransferResult;
use Rzd\Request\TransferSearch;

/**
 * Поиск с пересадками
 *
 * Обычный поиск отдает только прямые поезда. Здесь сайт строит цепочки из
 * нескольких рейсов, считает время ожидания и переезды между вокзалами
 */
final readonly class Transfers extends Endpoint
{
    private const SEARCH_PATH = '/apib2b/mmp/onewayRoutesStream/v2';

    /**
     * Варианты поездки с пересадками
     */
    public function search(TransferSearch $request): TransferResult
    {
        return TransferResult::fromArray($this->transport->post(self::SEARCH_PATH, $request->toBody()));
    }
}
