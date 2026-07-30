<?php

declare(strict_types=1);

namespace Rzd\Model;

use DateTimeImmutable;
use Exception;

/**
 * Основа моделей ответа
 *
 * Сайт отдает у одного вагона больше восьмидесяти полей, у поезда больше
 * семидесяти. Модели описывают то, что нужно на практике, а полный ответ
 * остается доступен через raw, поэтому редкое поле не требует правки
 * библиотеки и не теряется при разборе
 */
abstract readonly class Model
{
    /**
     * @param array<string, mixed> $raw Ответ сайта без изменений
     */
    protected function __construct(public array $raw)
    {
    }

    /**
     * Значение поля из полного ответа
     *
     * Нужен для полей, не вошедших в модель
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        // Сайт присылает пустую строку вместо отсутствующего значения
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function bool(array $data, string $key): bool
    {
        return (bool) ($data[$key] ?? false);
    }

    /**
     * Список строк, пустой если поля нет
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    protected static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * Дата из формата сайта, 2026-08-01T00:20:00
     *
     * @param array<string, mixed> $data
     */
    protected static function date(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            // Формат даты у сайта единый, но ломать разбор ответа из-за него не стоит
            return null;
        }
    }

    /**
     * Коды из списка справочных объектов вида {"key": …, "provider_code": …}
     *
     * Поиск с пересадками ссылается на свои справочники по ключу, но рядом
     * кладет код перевозчика, понятный без дополнительного запроса
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    protected static function codes(array $data, string $key): array
    {
        $items = $data[$key] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $item): ?string => is_array($item) ? self::str($item, 'provider_code') : null,
            $items,
        )));
    }

    /**
     * Цена в рублях из вложенного объекта с копейками
     *
     * Поиск с пересадками отдает деньги как {"kopecks": "553410"}, причем
     * строкой: суммы не помещаются в целое число у части языков
     *
     * @param array<string, mixed> $data
     */
    protected static function money(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || ! is_numeric($value['kopecks'] ?? null)) {
            return null;
        }

        return (int) $value['kopecks'] / 100;
    }

    /**
     * Длительность в секундах из формата Go, например 807.024s
     *
     * @param array<string, mixed> $data
     */
    protected static function seconds(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || ! preg_match('/^(\d+(?:\.\d+)?)s$/', $value, $match)) {
            return null;
        }

        return (int) round((float) $match[1]);
    }

    /**
     * Одна вложенная модель, null если поля нет
     *
     * @template T of self
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $model
     *
     * @return T|null
     */
    protected static function one(array $data, string $key, string $model): ?self
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $model::fromArray($value) : null;
    }

    /**
     * Список вложенных моделей
     *
     * @template T of self
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $model
     *
     * @return list<T>
     */
    protected static function each(array $data, string $key, string $model): array
    {
        $items = $data[$key] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn(array $item): self => $model::fromArray($item),
            array_filter($items, 'is_array'),
        ));
    }

    /**
     * @param array<string, mixed> $data
     */
    abstract public static function fromArray(array $data): static;
}
