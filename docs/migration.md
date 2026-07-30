# Переход с 5.x на 6.0

Версия 6.0 написана заново под новое API `ticket.rzd.ru`. Прежний протокол
`pass.rzd.ru` с обменом идентификатором запроса больше не используется, поэтому
совместимости с 5.x нет: изменились имена классов, способ вызова и формат
ответов.

## Что изменилось по существу

**Запрос стал один вместо двух.** Прежний протокол требовал сначала получить
идентификатор `RID` и куки, выждать паузу и повторить запрос с ними. Новое API
отдаёт данные сразу, без сессии и повторов. Отсюда исчезли настройки
`setRetryDelay` и `setRetryLimit`

**Методы возвращают объекты, а не строки JSON.** Раньше каждый метод отдавал
строку, которую вызывающий код разбирал сам. Теперь возвращаются типизированные
модели, а полный ответ сайта доступен через `raw` и `get()`.

**HTTP-клиент передаётся снаружи.** Библиотека работает с любой реализацией
PSR-18 и больше не тянет Guzzle в зависимости. Таймаут и прокси настраиваются
в клиенте.

**Появились данные, которых прежнее API не давало:** номера свободных мест по
купе, схемы вагонов в SVG, фотографии салона, календарь цен по датам, справочник
тарифов.

## Соответствие методов

| 5.x                                | 6.0                                                                |
|------------------------------------|--------------------------------------------------------------------|
| `$api->trainRoutes($params)`       | `$client->trains->search(new TrainSearch(...))`                    |
| `$api->trainRoutesReturn($params)` | `$client->trains->searchReturn(new TrainSearch(...), $returnDate)` |
| `$api->trainCarriages($params)`    | `$client->cars->search(CarSearch::forTrain($train))`               |
| `$api->trainStationList($params)`  | `$client->routes->forTrain(RouteSearch::forTrain($train))`         |
| `$api->stationCode($params)`       | `$client->stations->suggest('ЧЕБ')`                                |
| —                                  | `$client->cars->scheme()`, `schemeImage()`, `images()`             |
| —                                  | `$client->prices->availability()`, `calendar()`                    |
| —                                  | `$client->references->tariffs()`, `appConfig()`                    |
| —                                  | `$client->stations->popular()`, `byNodeId()`                       |

Поиск туда-обратно устроен иначе. Прежний протокол искал пару маршрутов одним
запросом с параметром направления, новое API так не умеет: его собственная
страница туда-обратно отправляет **два независимых запроса**, второй с
переставленными станциями. Проверено — параметры второй даты (`returnDate`,
`dateBack`, `departureDateBack`) эндпоинт молча игнорирует, ответ не меняется.

`searchReturn()` делает эти два запроса за один вызов и возвращает `RoundTrip`:

```php
$trip = $client->trains->searchReturn(
    new Rzd\Request\TrainSearch(
        origin: '2000000',
        destination: '2004000',
        date: new DateTimeImmutable('2026-08-01'),
        adults: 2,
    ),
    new DateTimeImmutable('2026-08-05'),
);

$trip->forward;       // SearchResult туда
$trip->back;          // SearchResult обратно
$trip->hasSeats();    // есть места в обе стороны
$trip->minPrice();    // минимальная стоимость поездки целиком
```

Параметры обратного плеча — число пассажиров, поезда из расписания, группировка
вагонов — повторяют первое, меняются только станции и дата.

## Как переписать код

### Поиск поездов

```php
// 5.x
$api = new Rzd\Api($config);

$routes = json_decode($api->trainRoutes([
    'dir'        => 0,
    'tfl'        => 3,
    'checkSeats' => 1,
    'code0'      => 2000000,
    'code1'      => 2004000,
    'dt0'        => '01.08.2026',
]));

foreach ($routes->list as $train) {
    echo $train->number, $train->time0, $train->date0;
}
```

```php
// 6.0
$client = new Rzd\Client();

$result = $client->trains->search(new Rzd\Request\TrainSearch(
    origin: '2000000',
    destination: '2004000',
    date: new DateTimeImmutable('2026-08-01'),
));

foreach ($result as $train) {
    echo $train->number, $train->departure->format('H:i'), $train->departure->format('d.m.Y');
}
```

Параметры `dir`, `tfl` и `checkSeats` больше не нужны: они относились к прежнему
протоколу. Дата передаётся объектом `DateTimeInterface`, а не строкой в
формате сайта.

### Вагоны

Прежде коды станций и время отправления нужно было переносить из результатов
поиска руками, причём коды требовались другие — конкретных вокзалов, а не
города. Теперь запрос собирается из найденного поезда:

```php
// 5.x
$carriages = json_decode($api->trainCarriages([
    'dir'   => 0,
    'code0' => 2000003,
    'code1' => 2060500,
    'dt0'   => $train->date0,
    'time0' => $train->time0,
    'tnum0' => $train->number,
]));

foreach ($carriages->cars as $car) {
    echo $car->cnumber, $car->type, $car->freeSeats;
}
```

```php
// 6.0
$cars = $client->cars->search(Rzd\Request\CarSearch::forTrain($train));

foreach ($cars as $car) {
    echo $car->number, $car->typeName, $car->places;
    echo implode(', ', $car->placeNumbers());  // номеров мест раньше не было
}
```

### Станции маршрута

```php
// 5.x
$stations = json_decode($api->trainStationList([
    'trainNumber' => $train->number,
    'depDate'     => $train->date0,
]));

foreach ($stations->routes as $stop) { ... }
```

```php
// 6.0
$route = $client->routes->forTrain(Rzd\Request\RouteSearch::forTrain($train));

foreach ($route as $stop) {
    echo $stop->stationName, $stop->arrival?->format('H:i'), $stop->stopDuration;
}
```

### Коды станций

```php
// 5.x
$stations = json_decode($api->stationCode(['stationNamePart' => 'ЧЕБ']), true);
echo $stations[0]['station'], $stations[0]['code'];
```

```php
// 6.0
$stations = $client->stations->suggest('ЧЕБ');
echo $stations[0]->name, $stations[0]->code;
```

Поля переименованы: `station` стало `name`, `codes` и `timezone` сохранились,
добавился `nodeId`.

### Настройки

```php
// 5.x
$config = new Rzd\Config();
$config->setProxy('socks5://127.0.0.1:1080');
$config->setTimeout(30);
$config->setRetryDelay(2);
$config->setUserAgent('MyApp/1.0');
$config->setLanguage('en');

$api = new Rzd\Api($config);
```

```php
// 6.0: сетевое — в HTTP-клиенте, остальное — в Config
$factory = new Nyholm\Psr7\Factory\Psr17Factory();

$client = new Rzd\Client(
    config: new Rzd\Config(
        language: Rzd\Enum\Language::English,
        userAgent: 'MyApp/1.0',
    ),
    httpClient: new GuzzleHttp\Client([
        'proxy'   => 'socks5://127.0.0.1:1080',
        'timeout' => 30,
    ]),
    requestFactory: $factory,
    streamFactory: $factory,
);
```

`setRetryDelay` и `setRetryLimit` удалены вместе с обменом идентификаторами.
`setHandler` тоже: подмена транспорта для тестов теперь делается передачей
подставного PSR-18 клиента.

### Обработка ошибок

```php
// 5.x: любая ошибка приходила как RuntimeException
try {
    $api->trainRoutes($params);
} catch (RuntimeException $e) {
    echo $e->getMessage();
}
```

```php
// 6.0: недоступность сайта, блокировка и ошибка API различаются
try {
    $client->trains->search($search);
} catch (Rzd\Exception\TransportException $e) {
    // сайт недоступен, вне РФ это обычное дело
} catch (Rzd\Exception\ApiException $e) {
    echo $e->statusCode(), $e->errorCode();
} catch (Rzd\Exception\RzdException $e) {
    // всё остальное из библиотеки
}
```

## Требования

PHP поднят с 8.0 до 8.2. Guzzle из обязательных зависимостей удалён, вместо
него `psr/http-client` и `psr/http-factory`.

## Если нужен прежний протокол

Ветка `pass.rzd.ru` работала на момент выпуска 6.0 и остаётся доступной в
версии 5.0.0:

```sh
composer require visavi/rzd-api:^5.0
```

Новых данных туда не добавляется: сайт постепенно переносит функциональность
на `ticket.rzd.ru`.
