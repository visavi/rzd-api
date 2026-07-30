<?php

declare(strict_types=1);

namespace Rzd\Enum;

/**
 * Вариант схемы вагона
 *
 * Схема отдается отдельным файлом SVG, для двухэтажных вагонов этажи
 * разными файлами. Мобильный вариант отличается вертикальной раскладкой
 */
enum SchemeView: string
{
    case DesktopFirstStorey = 'PcFirstStorey';
    case DesktopSecondStorey = 'PcSecondStorey';
    case MobileFirstStorey = 'MobileFirstStoreyVert';
    case MobileSecondStorey = 'MobileSecondStoreyVert';

    /**
     * Поле ответа carscheme, в котором лежит путь к этому варианту
     */
    public function field(): string
    {
        return match ($this) {
            self::DesktopFirstStorey  => 'PcSchemeFirstStorey',
            self::DesktopSecondStorey => 'PcSchemeSecondStorey',
            self::MobileFirstStorey   => 'MobileSchemeFirstVertStorey',
            self::MobileSecondStorey  => 'MobileSchemeSecondVertStorey',
        };
    }
}
