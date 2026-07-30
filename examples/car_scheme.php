<?php

declare(strict_types=1);

/**
 * Схема вагона и его фотографии
 *
 * Схема отдается изображением SVG, здесь она сохраняется в файл
 */

require __DIR__ . '/bootstrap.php';

use Rzd\Enum\SchemeView;
use Rzd\Request\CarSchemeSearch;
use Rzd\Request\CarSearch;
use Rzd\Request\TrainSearch;

$client = rzd();

$result = attempt(fn() => $client->trains->search(new TrainSearch(
    origin: '2000000',
    destination: '2004000',
    date: new DateTimeImmutable('+7 days'),
)));

$trains = $result->withSeats();

if ($trains === []) {
    fwrite(STDERR, "На эту дату нет поездов со свободными местами\n");
    exit(1);
}

$train = $trains[0];
$cars = attempt(fn() => $client->cars->search(CarSearch::forTrain($train)));

$car = null;

foreach ($cars->withSeats() as $candidate) {
    if ($candidate->subType !== null && $candidate->carrier !== null) {
        $car = $candidate;
        break;
    }
}

if ($car === null) {
    fwrite(STDERR, "Не нашлось вагона с подтипом\n");
    exit(1);
}

$request = CarSchemeSearch::forCar($car, $train);
$scheme = attempt(fn() => $client->cars->scheme($request));

heading(sprintf('Схема вагона %s поезда %s', $car->number, $train->number));

printf("подтип        %s\n", $scheme->subType);
printf("схема         %d\n", $scheme->schemeId);
printf("перевозчик    %s\n", $scheme->carrier);
printf("двухэтажный   %s\n", $scheme->isTwoStorey() ? 'да' : 'нет');

foreach ($scheme->views as $view => $path) {
    printf("%-24s %s\n", $view, $path);
}

if ($scheme->schemeId !== null) {
    $svg = attempt(fn() => $client->cars->schemeImage($scheme->schemeId, SchemeView::DesktopFirstStorey));

    $file = sys_get_temp_dir() . '/rzd-car-scheme-' . $scheme->schemeId . '.svg';

    file_put_contents($file, $svg);

    printf("\nсхема сохранена: %s (%d байт)\n", $file, strlen($svg));
}

heading('Фотографии вагона');

foreach (attempt(fn() => $client->cars->images($request)) as $image) {
    printf("%s %s\n", pad($image->title, 32), $image->content);
}
