# Api сайта rzd.ru

[![Packagist](https://img.shields.io/packagist/v/visavi/rzd-api.svg)](https://packagist.org/packages/visavi/rzd-api)
[![Tests](https://github.com/visavi/rzd-api/actions/workflows/tests.yml/badge.svg)](https://github.com/visavi/rzd-api/actions/workflows/tests.yml)
[![Coverage](https://coveralls.io/repos/github/visavi/rzd-api/badge.svg?branch=master)](https://coveralls.io/github/visavi/rzd-api?branch=master)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.2-777bb4.svg)](https://www.php.net/releases/8.2/)
[![Downloads](https://img.shields.io/packagist/dt/visavi/rzd-api.svg)](https://packagist.org/packages/visavi/rzd-api)
[![License](https://img.shields.io/packagist/l/visavi/rzd-api.svg)](https://github.com/visavi/rzd-api/blob/master/composer.json)

Клиент API РЖД: поиск поездов, свободные места в вагонах, схемы вагонов,
маршруты следования, календарь цен и справочники.

## Что умеет

* **Поиск поездов** — расписание, время в пути, расстояние, типы вагонов, цены и количество мест
* **Поиск с пересадками** — цепочки рейсов там, где прямых поездов нет, с ожиданием и переездами между вокзалами
* **Вагоны и места** — номера свободных мест по вагонам и купе, цены, услуги
* **Схемы вагонов** — чертеж вагона в SVG и фотографии салона
* **Маршрут поезда** — все остановки с местным и московским временем, стоянками и часовыми поясами
* **Станции** — поиск кодов по названию, популярные города, коды смежных видов транспорта
* **Календарь цен** — минимальные цены по датам, даты с местами и горизонт продажи
* **Справочники** — тарифы, карты и абонементы, конфигурация сайта
* **Аэроэкспресс** — тарифы на поездку в аэропорт

## Содержание

* [Установка](#установка)
* [Быстрый старт](#быстрый-старт)
* [Настройка](#настройка)
* [Методы](#методы)
  * [Поиск поездов](#поиск-поездов)
  * [Поиск с пересадками](#поиск-с-пересадками)
  * [Вагоны и места](#вагоны-и-места)
  * [Схема и фотографии вагона](#схема-и-фотографии-вагона)
  * [Маршрут поезда](#маршрут-поезда)
  * [Станции](#станции)
  * [Календарь цен](#календарь-цен)
  * [Справочники](#справочники)
  * [Аэроэкспресс](#аэроэкспресс)
* [Ошибки](#ошибки)
* [Данные вне моделей](#данные-вне-моделей)
* [Примеры](#примеры)
* [Тесты](#тесты)
* [Переход с 5.x](docs/migration.md)
* [Описание эндпоинтов](docs/endpoints.md)

## Установка

```sh
composer require visavi/rzd-api
```

Библиотека не привязана к конкретному HTTP-клиенту: ей нужна любая реализация
[PSR-18](https://www.php-fig.org/psr/psr-18/) и [PSR-17](https://www.php-fig.org/psr/psr-17/).
Если в проекте их ещё нет, достаточно Guzzle — он даёт и клиент, и фабрики:

```sh
composer require guzzlehttp/guzzle
```

Подойдёт любая другая пара, проверенные варианты:

| Клиент PSR-18          | Фабрики PSR-17                                                                      |
|------------------------|-------------------------------------------------------------------------------------|
| `guzzlehttp/guzzle`    | приедут вместе с ним (`guzzlehttp/psr7`), ставить отдельно не нужно                 |
| `symfony/http-client`  | нужны отдельно: `nyholm/psr7`, `laminas/laminas-diactoros`, `httpsoft/http-message` |
| `php-http/curl-client` | нужны отдельно, те же варианты                                                      |

Реализация подхватывается автоматически через `php-http/discovery`, либо
передаётся в конструктор явно. Обратите внимание: `symfony/http-client` без
реализации PSR-17 не заработает — фабрик в нём нет.

Требования: PHP 8.2 или новее и расширение `json`.

Версия 6.0 работает с новым API `ticket.rzd.ru` и несовместима с 5.x. Если код
написан под прежний протокол `pass.rzd.ru` и переписывать его сейчас не нужно,
оставайтесь на пятой версии:

```sh
composer require visavi/rzd-api:^5.0
```

Она работоспособна, но новых данных туда не добавляется. Порядок перехода
описан в [docs/migration.md](docs/migration.md).

## Быстрый старт

```php
use Rzd\Client;
use Rzd\Request\CarSearch;
use Rzd\Request\TrainSearch;

$client = new Client();

$result = $client->trains->search(new TrainSearch(
    origin: '2000000',       // Москва
    destination: '2004000',  // Санкт-Петербург
    date: new DateTimeImmutable('+7 days'),
));

foreach ($result as $train) {
    printf(
        "%s %s → %s, мест %d, от %.2f\n",
        $train->number,
        $train->departure->format('d.m H:i'),
        $train->arrival->format('d.m H:i'),
        $train->freeSeats(),
        $train->minPrice(),
    );
}

// Вагоны первого поезда: параметры собираются из него самого
$cars = $client->cars->search(CarSearch::forTrain($result->trains[0]));

foreach ($cars->withSeats() as $car) {
    printf("вагон %s %s, места: %s\n", $car->number, $car->typeName, $car->freePlaces);
}
```

Коды станций ищутся по названию:

```php
foreach ($client->stations->suggest('Чебоксары') as $station) {
    printf("%s %s %s\n", $station->name, $station->code, $station->timezone);
}
```

## Настройка

Сетевые настройки — таймаут, прокси, повторы, логирование — задаются в
HTTP-клиенте, а не в библиотеке. Так они настраиваются один раз для всего
приложения, и библиотека не дублирует возможности клиента.

**Сайт принимает запросы только с российских адресов**, с остальных соединение
уходит в таймаут. Вне РФ нужен прокси:

```php
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Rzd\Client;
use Rzd\Config;

$factory = new HttpFactory();

$client = new Client(
    config: new Config(),
    httpClient: new GuzzleClient([
        'proxy'   => 'socks5://127.0.0.1:1080',
        'timeout' => 30,
    ]),
    requestFactory: $factory,
    streamFactory: $factory,
);
```

Если клиент и фабрики не переданы, они определяются автоматически среди
установленных реализаций PSR-18 и PSR-17.

Настройки самой библиотеки:

```php
use Rzd\Config;
use Rzd\Enum\Language;

$config = new Config(
    // Язык ответов сайта
    language: Language::English,

    // Без User-Agent сайт отвечает 403, поэтому по умолчанию подставляется браузерный
    userAgent: 'MyApp/1.0',

    // Дополнительные заголовки к каждому запросу
    headers: ['X-Client-ID' => '22900'],
);
```

Настройки неизменяемы, копию с другими значениями дают методы `withLanguage`,
`withUserAgent` и `withHeaders`.

Заголовки из настроек уходят со всеми запросами. Исключение одно: поиск с
пересадками добавляет к своему запросу куку `LANG_SITE` — без неё сайт отвечает
`500`, поэтому она важнее пользовательского `Cookie`.

## Методы

Клиент разбит по ресурсам: `trains`, `cars`, `routes`, `stations`, `prices`,
`references`, `aeroexpress`, `transfers`.

Параметры передаются объектами запросов. Собрать такой объект можно двумя
способами: обычным конструктором с именованными аргументами либо фабрикой из
уже полученного ответа — `CarSearch::forTrain($train)`,
`RouteSearch::forTrain($train)`, `CarSchemeSearch::forCar($car, $train)`,
`TrainSearch::forStations($from, $to, $date)`,
`TransferSearch::forStations($from, $to, $date)`,
`TrainSearch::forDirection($direction, $date)`,
`TransferSearch::forDirection($direction, $date)`.

Фабрики нужны не только для краткости. Например, запросу вагонов нужны коды
**конкретных вокзалов** (`2000003` — Москва Казанская), а не города
(`2000000` — Москва), которым искали поезда, плюс система бронирования поезда.
Перенося это руками, легко получить пустой ответ вместо ошибки.

### Поиск поездов

```php
$result = $client->trains->search(new TrainSearch(
    origin: '2000000',
    destination: '2004000',
    date: new DateTimeImmutable('2026-08-01'),
    adults: 2,            // взрослых пассажиров
    children: 1,          // детей без места
    fromSchedule: true,   // добавлять поезда из расписания, у которых мест ещё нет
    largeFamily: false,   // искать места для многодетных
    groupCars: false,     // группировать вагоны одного типа
));
```

Если станции найдены подсказкой, коды доставать не нужно:

```php
$result = $client->trains->search(TrainSearch::forStations(
    $client->stations->find('Москва'),
    $client->stations->find('Санкт-Петербург'),
    new DateTimeImmutable('2026-08-01'),
    adults: 2,
));
```

Фабрика принимает и ненайденную станцию: если `find` вернул `null`, будет
`InvalidArgumentException` вместо запроса с пустым кодом.

Популярное направление содержит обе станции сразу, подсказки ему не нужны:

```php
$direction = $client->stations->directions()[0];

$result = $client->trains->search(TrainSearch::forDirection($direction, $date));
```

`SearchResult` перебирается как список поездов и хранит данные направления,
поэтому пустой результат отличим от отсутствия мест:

```php
count($result);            // сколько поездов найдено
$result->trains;           // список Train
$result->withSeats();      // только поезда со свободными местами
$result->cheapest();       // самый дешёвый из тех, где есть места
$result->fastest();        // самый быстрый из тех, где есть места
$result->originName;        // название станции отправления
$result->destinationName;
$result->moscowTime;        // текущее московское время сайта
$result->partial;           // сайт вернул не все поезда направления
```

Поезд:

```php
$train->number;            // 130Х
$train->displayNumber;     // номер для показа пользователю
$train->name;              // название фирменного поезда, иначе null
$train->description;       // категория: СК, ПАСС, СКОР
$train->departure;         // DateTimeImmutable по местному времени станции
$train->arrival;
$train->moscowDeparture;   // то же по московскому времени
$train->duration;          // время в пути в минутах
$train->distance;          // расстояние в километрах
$train->carriers;          // ['ФПК']
$train->carGroups;         // группы вагонов с ценами и местами
$train->freeSeats();       // свободных мест по всем группам
$train->minPrice();        // минимальная цена по всем группам

$train->provider;          // система бронирования, нужна для запроса вагонов
$train->originStationCode; // код станции, а не города — тоже нужен для вагонов
```

Поиск туда-обратно делает два запроса: сайт не умеет искать пару маршрутов
одним, его собственная страница туда-обратно поступает так же. Параметры
обратного плеча повторяют первое, меняются только станции и дата:

```php
$trip = $client->trains->searchReturn($search, new DateTimeImmutable('2026-08-05'));

$trip->forward;      // SearchResult туда
$trip->back;         // SearchResult обратно
$trip->hasSeats();   // есть места в обе стороны
$trip->minPrice();   // минимальная стоимость поездки целиком
```

Группа вагонов:

```php
$group->type;              // Compartment, Luxury, Soft, ReservedSeat, Sedentary
$group->typeName;          // КУПЕ, СВ, ЛЮКС, ПЛАЦ, СИДЯЧИЙ
$group->serviceClasses;    // ['2Э']
$group->places;            // свободных мест
$group->lowerPlaces;       // из них нижних
$group->upperPlaces;
$group->minPrice;
$group->maxPrice;
$group->availability;      // Available, LastPlaces, NotAvailable
```

### Поиск с пересадками

Обычный поиск отдаёт только прямые поезда. Между городами без прямого
сообщения цепочку из нескольких рейсов строит отдельный метод. Города здесь
задаются **идентификаторами узлов сайта**, а не кодами станций: их отдаёт
подсказка станций в поле `nodeId`.

```php
use Rzd\Enum\TransportProvider;
use Rzd\Request\TransferSearch;

$result = $client->transfers->search(new TransferSearch(
    origin: '5a13bdc3340c745ca1e8aa54',      // Новый Уренгой
    destination: '5a13baab340c745ca1e7f31c', // Абакан
    date: new DateTimeImmutable('2026-08-20'),
    minTrips: 2,          // наименьшее число рейсов в цепочке, 1 добавит прямые
    maxTrips: 4,          // наибольшее, то есть пересадок плюс один
    maxResults: 200,      // предел числа вариантов
    providers: [TransportProvider::Rails], // виды транспорта в поиске
));
```

Готовый запрос можно собрать прямо из подсказок:

```php
$request = TransferSearch::forStations(
    $client->stations->find('Новый Уренгой'),
    $client->stations->find('Абакан'),
    new DateTimeImmutable('2026-08-20'),
);
```

Подсказка возвращает и город, и его вокзалы. Годится любой узел, но выдача
отличается: город объединяет вокзалы, а отдельный вокзал даёт больше вариантов
от себя самого — Москва → Архангельск это 8 вариантов от города и 12 от
Ярославского вокзала. Узел города станции доступен как `$station->cityId`.

Результат перебирается как список вариантов поездки:

```php
count($result);            // сколько вариантов найдено
$result->routes;           // список TransferRoute
$result->withSeats();      // варианты, где места есть на всех плечах
$result->cheapest();        // самый дешёвый
$result->fastest();         // самый быстрый
```

Вариант поездки перебирается как список плеч — частей, оформляемых одним
билетом:

```php
$route->changes();         // число пересадок
$route->minPrice;          // стоимость всей поездки по самым дешёвым местам
$route->maxPrice;
$route->duration();        // время в пути в минутах, вместе с ожиданием
$route->waits();           // ожидание на каждой пересадке, в минутах
$route->waitTotal();       // сколько всего стоять на пересадках
$route->departure();       // отправление первого рейса
$route->arrival();         // прибытие последнего
$route->origin();          // Place начала поездки
$route->destination();
$route->hasSeats();        // места есть на всех плечах
$route->trips();           // все рейсы поездки подряд, list<Trip>
$route->legs;              // плечи, list<RouteLeg>
$route->transfers;         // переезды между вокзалами, list<Transfer>
```

Рейс:

```php
$trip->number;             // номер поезда, например 002Э
$trip->transportType;      // Train, Bus, Airplane
$trip->origin;             // Place, с названием станции и города
$trip->destination;
$trip->departure;
$trip->arrival;
$trip->duration();         // время в пути в минутах
$trip->distance;           // километров
$trip->freePlaces;
$trip->minPrice;
$trip->maxPrice;
$trip->products;           // классы обслуживания с ценами, list<TripProduct>
$trip->train();            // Train со всеми данными обычного поиска, либо null
```

Сайт вкладывает в рейс поезда полный ответ обычного поиска, поэтому вагоны
и цены доступны без второго запроса:

```php
foreach ($route->trips() as $trip) {
    foreach ($trip->train()?->carGroups ?? [] as $group) {
        printf("%s %d мест от %s\n", $group->typeName, $group->places, $group->minPrice);
    }
}
```

Переезд между вокзалами появляется, когда цепочка приходит на один вокзал
города, а уезжает с другого. Пустой список `transfers` означает, что все
пересадки происходят на одном вокзале, а не что пересадок нет:

```php
$transfer->origin?->name;   // Ярославль (Московский вокзал)
$transfer->destination?->name; // Ярославль-Главный
$transfer->duration;        // время переезда в минутах, как у рейса и поездки
$transfer->seconds;         // то же без округления
$transfer->price;           // стоимость
```

### Вагоны и места

Запросу вагонов нужны коды **конкретных станций** поезда и его система
бронирования. Всё это есть в найденном поезде, поэтому проще собрать параметры
из него:

```php
$cars = $client->cars->search(CarSearch::forTrain($train));
```

Или задать вручную:

```php
$cars = $client->cars->search(new CarSearch(
    origin: '2000003',
    destination: '2060500',
    trainNumber: '130Х',
    departure: new DateTimeImmutable('2026-08-01 00:20'),
    provider: 'P1',
));
```

```php
count($cars);              // сколько вагонов
$cars->withSeats();        // только вагоны со свободными местами
$cars->cheapest();         // самый дешёвый из тех, где есть места
$cars->train;              // данные поезда, приходят тем же ответом

$car->number;              // 09
$car->typeName;            // КУПЕ
$car->serviceClass;        // 2Э
$car->freePlaces;          // «2, 4» как отдаёт сайт
$car->placeNumbers();      // [2, 4] числами, пометки пола отброшены
$car->places;              // свободных мест
$car->minPrice;
$car->maxPrice;
$car->serviceCost;         // стоимость сервисных услуг, входит в цену
$car->schemeId;            // идентификатор схемы вагона
$car->subType;             // 64К, определяет схему

foreach ($car->compartments as $compartment) {
    printf("купе %s: %s\n", $compartment->number, implode(', ', $compartment->placeNumbers()));
}
```

Пометка после номера места (`4М`, `12Ж`, `22С`) означает пол пассажиров в купе,
к номеру места отношения не имеет и в `placeNumbers()` отбрасывается.

### Схема и фотографии вагона

```php
use Rzd\Enum\SchemeView;
use Rzd\Request\CarSchemeSearch;

$request = CarSchemeSearch::forCar($car, $train);

$scheme = $client->cars->scheme($request);

$scheme->schemeId;          // 567
$scheme->isTwoStorey();
$scheme->has(SchemeView::DesktopSecondStorey);

// Чертёж вагона в SVG
$svg = $client->cars->schemeImage($scheme->schemeId, SchemeView::DesktopFirstStorey);

// Фотографии салона
foreach ($client->cars->images($request) as $image) {
    printf("%s %s\n", $image->title, $image->content);
}
```

### Маршрут поезда

```php
use Rzd\Request\RouteSearch;

$route = $client->routes->search(RouteSearch::forTrain($train));

foreach ($route as $stop) {
    printf(
        "%s приб %s отпр %s стоянка %s мин, МСК %+d\n",
        $stop->stationName,
        $stop->arrival?->format('d.m H:i') ?? '',
        $stop->departure?->format('d.m H:i') ?? '',
        $stop->stopDuration,
        $stop->timeZoneDifference,
    );
}
```

У части поездов сайт отдаёт несколько вариантов маршрута, например с
прицепными вагонами. `search` возвращает основной, `all` — все.

Прежнее имя `routes->forTrain()` сохранено до 7.0, но помечено устаревшим:
рядом с фабрикой запроса вызов читался как
`routes->forTrain(RouteSearch::forTrain($train))`.

### Станции

```php
// Поиск по части названия, повторяющиеся коды отбрасываются
$stations = $client->stations->suggest('ЧЕБ');

// Первая подходящая станция или null: подсказки отсортированы по близости
// к запросу, поэтому для готового названия города разбирать список незачем
$station = $client->stations->find('Чебоксары');

$station->name;             // Чебоксары
$station->code;             // 2060620, он нужен для поиска поездов
$station->nodeId;           // идентификатор узла нового сайта, нужен для пересадок
$station->cityId;           // узел города станции, у самого города равен nodeId
$station->isCity();         // узел города, а не отдельного вокзала
$station->region;           // Российская Федерация
$station->type;             // Город, Станция, Поселок
$station->timezone;         // Europe/Moscow
$station->codes;            // ['Railway' => ..., 'Cbdpr' => ..., 'Bus' => ..., 'Avia' => ...]
$station->stationCodes;     // коды всех вокзалов города

// Популярные города
$client->stations->popular();

// Популярные направления: готовые пары станций
foreach ($client->stations->directions() as $direction) {
    printf("%s → %s\n", $direction->origin?->name, $direction->destination?->name);
}

// Город или станция по идентификатору узла
$client->stations->byNodeId('5a323c29340c7441a0a556bb');
```

### Календарь цен

```php
// Даты, на которые между станциями есть поезда с местами
$dates = $client->prices->availability(
    '2000000',
    '2004000',
    new DateTimeImmutable('+1 day'),
    new DateTimeImmutable('+21 days'),
);

// Минимальные цены по датам отправления
foreach ($client->prices->calendar('2000000', '2004000', new DateTimeImmutable('+1 day')) as $day) {
    printf("%s от %.2f\n", $day->date->format('d.m.Y'), $day->minPrice);

    $day->byCarType();  // ['Compartment' => 2037.30, 'Luxury' => 9043.30]
    $day->carriers();   // ['ФПК', 'ДОСС']
}
```

Отдельный вопрос — до какой даты продажа открыта вообще. Сайт отдаёт календарь
примерно на тринадцать месяцев вперёд, из которых заполнены только доступные:

```php
foreach ($client->prices->saleCalendar('2000000', '2004000') as $month) {
    printf("%d-%02d: дней в продаже %d\n", $month->year, $month->month, count($month->saleDays));

    $month->availableDays;   // числа месяца, на которые есть поезда
    $month->saleDays;        // числа месяца, на которые открыта продажа
    $month->isOnSale(15);    // открыта ли продажа на 15-е
    $month->dates();         // те же дни объектами DateTimeImmutable
}
```

### Справочники

```php
foreach ($client->references->tariffs() as $tariff) {
    printf("%s %s %s\n", $tariff->sysName, $tariff->category, $tariff->isActive() ? '' : 'недействующий');
}

// Конфигурация сайта отдаётся массивом: набор ключей меняется сайтом
$config = $client->references->appConfig();
```

Карты и абонементы перевозчиков — скидка в процентах либо фиксированное число
поездок:

```php
foreach ($client->references->cards() as $card) {
    printf("%s %s %.2f\n", $card->code, $card->name, $card->price);

    $card->discount;         // скидка в процентах
    $card->tripQuantity;     // число поездок у абонемента
    $card->activeDays;       // срок действия в днях
    $card->carTypes;         // ['Compartment', 'Sedentary']
    $card->serviceClasses;
    $card->isPass();          // абонемент на поездки, а не скидочная карта
    $card->fitsCarType('Compartment');
}
```

### Аэроэкспресс

У аэроэкспресса свои тарифы: место обычно не фиксировано, а билет действует
несколько месяцев, поэтому поиска поездов здесь нет.

```php
foreach ($client->aeroexpress->tariffs(new DateTimeImmutable('+7 days')) as $tariff) {
    printf("%s %.2f\n", $tariff->name, $tariff->price);

    $tariff->type;            // Standard, Business
    $tariff->description;     // условия применения
    $tariff->maxTickets;      // сколько билетов можно купить одним заказом
    $tariff->guaranteedSeat;
    $tariff->documentTypes;
}
```

Коды станций необязательны: без них приходят тарифы, действующие на любом
направлении от аэропортов и к ним.

## Ошибки

Все исключения библиотеки реализуют `Rzd\Exception\RzdException`, поэтому
ловятся одним `catch`:

```php
use Rzd\Exception\ApiException;
use Rzd\Exception\ForbiddenException;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Exception\MalformedResponseException;
use Rzd\Exception\RzdException;
use Rzd\Exception\TransportException;

try {
    $client->trains->search($search);
} catch (TransportException $e) {
    // Сайт недоступен: таймаут, обрыв соединения, ошибка прокси.
    // Вне РФ самая частая ошибка
} catch (ForbiddenException $e) {
    // Запрос отбит защитой сайта, обычно из-за пустого User-Agent
} catch (ApiException $e) {
    // Сайт ответил ошибкой
    $e->statusCode();  // 500
    $e->errorCode();   // INTERNAL_ERROR
    $e->body();        // тело ответа целиком
} catch (MalformedResponseException $e) {
    // Ответ успешный, но это не JSON
} catch (InvalidArgumentException $e) {
    // Некорректные параметры, обнаружены до обращения к сайту
} catch (RzdException $e) {
    // Любая ошибка библиотеки
}
```

## Данные вне моделей

Сайт отдаёт у поезда больше семидесяти полей, у вагона больше восьмидесяти.
Модели описывают то, что нужно на практике, а полный ответ остаётся доступен,
поэтому редкое поле не требует правки библиотеки:

```php
$train->get('TrainBrandCode');   // 3033
$train->get('BoardingSystemTypes');
$train->raw;                      // весь ответ сайта по этому поезду
$result->raw;                     // весь ответ целиком
```

Значения, которые присылает сайт (`CarType`, `Provider`, `CarNumeration`),
остаются строками, а не перечислениями: сайт может добавить новое значение,
и перечисление сломало бы клиент на ровном месте. Перечисления используются
только там, где значение выбираем мы: `Language`, `SchemeView`.

## Примеры

Запускаются из корня проекта, вне РФ — с прокси:

```sh
php examples/index.php                 # список примеров
php examples/index.php search_trains   # запустить один
php examples/search_trains.php         # то же напрямую

RZD_PROXY=socks5://127.0.0.1:1080 php examples/index.php search_trains
```

Либо в браузере, со страницей-навигацией по примерам:

```sh
RZD_PROXY=socks5://127.0.0.1:1080 php -S localhost:8000 -t examples
```

| Пример                                            | Что показывает                                |
|---------------------------------------------------|-----------------------------------------------|
| [search_trains.php](examples/search_trains.php)   | поиск поездов, цены, типы вагонов             |
| [round_trip.php](examples/round_trip.php)         | поиск туда-обратно, стоимость поездки целиком |
| [transfers.php](examples/transfers.php)           | цепочки рейсов с пересадками, ожидание        |
| [car_places.php](examples/car_places.php)         | вагоны, свободные места по купе               |
| [car_scheme.php](examples/car_scheme.php)         | схема вагона в SVG и фотографии салона        |
| [train_route.php](examples/train_route.php)       | маршрут поезда по станциям                    |
| [stations.php](examples/stations.php)             | коды станций, популярные города               |
| [price_calendar.php](examples/price_calendar.php) | горизонт продажи, наличие мест, цены по датам |
| [cards.php](examples/cards.php)                   | карты и абонементы со скидками                |
| [aeroexpress.php](examples/aeroexpress.php)       | тарифы аэроэкспресса                          |
| [tariffs.php](examples/tariffs.php)               | справочник тарифов, конфигурация сайта        |

## Тесты

```sh
composer test           # на моках, без обращения к сети
composer test:coverage  # с покрытием
composer analyse        # PHPStan, уровень 8
```

Живые запросы к сайту вынесены в группу `live` и исключены из обычного прогона
и из CI, поскольку сайт принимает их только с российских адресов:

```sh
RZD_PROXY=socks5://127.0.0.1:1080 composer test:live
```

## Лицензия

MIT
