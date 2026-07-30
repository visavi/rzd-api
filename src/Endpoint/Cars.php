<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use Rzd\Model\CarImage;
use Rzd\Model\CarList;
use Rzd\Model\CarScheme;
use Rzd\Enum\SchemeView;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Request\CarSchemeSearch;
use Rzd\Request\CarSearch;

/**
 * Вагоны поезда, схемы и фотографии
 */
final readonly class Cars extends Endpoint
{
    private const PATH = '/apib2b/p/Railway/V1/Search/CarPricing';
    private const SCHEME_PATH = '/api/v1/railway-service/carscheme';
    private const IMAGES_PATH = '/api/v1/railway-service/carimage/list';
    private const SCHEME_IMAGE_PATH = '/api/v1/carscheme/image';

    /**
     * Вагоны поезда с номерами свободных мест
     */
    public function search(CarSearch $request): CarList
    {
        return CarList::fromArray($this->transport->post(
            self::PATH,
            $request->toBody(),
            $this->serviceProvider() + ['isBonusPurchase' => 'false'],
        ));
    }

    /**
     * Схема вагона: идентификатор и адреса вариантов чертежа
     */
    public function scheme(CarSchemeSearch $request): CarScheme
    {
        return CarScheme::fromArray(
            $this->transport->get(self::SCHEME_PATH, $request->toQuery()),
        );
    }

    /**
     * Изображение схемы вагона в формате SVG
     *
     * Возвращает содержимое файла, а не адрес: разметку удобнее отдавать
     * в шаблон или сохранять, чем проксировать запрос повторно
     */
    public function schemeImage(int $schemeId, SchemeView $view = SchemeView::DesktopFirstStorey): string
    {
        if ($schemeId <= 0) {
            throw new InvalidArgumentException('Идентификатор схемы должен быть положительным');
        }

        return $this->transport->getRaw(sprintf('%s/%d/%s', self::SCHEME_IMAGE_PATH, $schemeId, $view->value));
    }

    /**
     * Фотографии вагона
     *
     * @return list<CarImage>
     */
    public function images(CarSchemeSearch $request): array
    {
        $response = $this->transport->get(
            self::IMAGES_PATH,
            $request->toImageQuery() + $this->serviceProvider(),
        );

        return array_values(array_map(
            static fn(array $image): CarImage => CarImage::fromArray($image),
            array_filter($response['Images'] ?? [], 'is_array'),
        ));
    }
}
