<?php

declare(strict_types=1);

namespace Rzd\Request;

/**
 * Значения параметров, которые сайт передает во все запросы одинаковыми
 *
 * Перечислениями не оформлены: других вариантов у сайта не встречено,
 * а придумывать их вслепую значит документировать несуществующее
 */
final readonly class Defaults
{
    /**
     * Выдавать обычные места вместе с местами для маломобильных пассажиров
     */
    public const SPECIAL_PLACES_DEMAND = 'StandardPlacesAndForDisabledPersons';

    /**
     * Пассажирские вагоны, в отличие от автомобилевозов
     */
    public const CAR_ISSUING_TYPE = 'Passenger';

    /**
     * Система бронирования по умолчанию, если поезд ее не сообщил
     */
    public const PROVIDER = 'P1';
}
