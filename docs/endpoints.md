# Эндпоинты API РЖД

Описание запросов, на которых работает библиотека. Пригодится, если нужно
обратиться к сайту напрямую или разобраться, откуда берётся то или иное поле.

Все адреса на `https://ticket.rzd.ru`.

## Общие правила

**Авторизация не требуется.** Ни один из перечисленных запросов не нуждается
в токенах и сессии. Личный кабинет нужен только для покупки, которую
библиотека не делает. Единственное исключение по кукам — поиск с пересадками,
ему нужна `LANG_SITE` с любым значением.

**Заголовок `User-Agent` обязателен.** Без него защита сайта отвечает `403` с
телом вида `Forbidden ... Request ID: 1|2026-07-30-...`. Именно это, а не
количество свободных мест, было причиной жалоб в issue
[#23](https://github.com/visavi/rzd-api/issues/23). Библиотека подставляет
браузерный `User-Agent` по умолчанию.

**Кириллицу в строке запроса нужно кодировать.** Номера поездов содержат
русские буквы (`130Х`), и на некодированное значение прокси сайта отвечает
`400 Bad request`.

**`service_provider=B2B_RZD`** уходит почти во все запросы.

**Даты** передаются в формате `2026-08-01T00:20:00`, без часового пояса.

**Ошибки** приходят с кодом `500` и телом
`{"Code":"i1","code":"INTERNAL_ERROR","Message":"..."}`. Текст лежит в `Message`,
у части ответов — в `error`. По коду ответа нельзя судить, существует ли путь:
любой неизвестный путь внутри `/api/v1/railway-service/` тоже отдаёт `500`.

## Поиск поездов

```
GET /api/v1/railway-service/prices/train-pricing
```

| Параметр | Значение |
| --- | --- |
| `origin`, `destination` | коды станций или городов, например `2000000` |
| `departureDate` | `2026-08-01T00:00:00` |
| `adultPassengersQuantity` | взрослых пассажиров |
| `childrenPassengersQuantity` | детей без места |
| `getTrainsFromSchedule` | `true` — добавлять поезда из расписания без мест |
| `carGrouping` | `DontGroup` или `Group` |
| `specialPlacesDemand` | `StandardPlacesAndForDisabledPersons` |
| `carIssuingType` | `Passenger` |
| `getByLocalTime` | `true` — время станций, а не московское |
| `hasPlacesForLargeFamily` | места для многодетных |

Отдаёт `Trains[]`. У поезда около семидесяти полей, ключевые: `TrainNumber`,
`Provider`, `OriginStationCode`, `LocalDepartureDateTime`, `TripDuration` в
минутах, `TripDistance` в километрах и `CarGroups[]` — типы вагонов с ценами.

В группе вагонов свободные места лежат в **`TotalPlaceQuantity`**, а не в
`PlaceQuantity`: последнее приходит нулём. У самого вагона наоборот —
`PlaceQuantity` заполнено. Разбивка: `LowerPlaceQuantity`,
`UpperPlaceQuantity`, `LowerSidePlaceQuantity`, `UpperSidePlaceQuantity`.

**Пары маршрутов эндпоинт не поддерживает.** Параметры второй даты
(`returnDate`, `dateBack`, `departureDateBack`, `arrivalDate`) он молча
игнорирует: ответ не меняется ни на байт. Страница поиска туда-обратно на самом
сайте отправляет два независимых запроса, второй с переставленными станциями —
две даты видны только в её адресе, `/searchresults/v/1/{node0}/{node1}/{дата}/{дата2}`.

Реализация: `Rzd\Endpoint\Trains::search()`, `searchReturn()`.

## Вагоны и места

```
POST /apib2b/p/Railway/V1/Search/CarPricing?service_provider=B2B_RZD&isBonusPurchase=false
```

```json
{
  "OriginCode": "2000003",
  "DestinationCode": "2060500",
  "TrainNumber": "130Х",
  "DepartureDate": "2026-08-01T00:20:00",
  "Provider": "P1",
  "SpecialPlacesDemand": "StandardPlacesAndForDisabledPersons",
  "CarIssuingType": "Passenger",
  "OnlyFpkBranded": false,
  "HasPlacesForLargeFamily": false
}
```

**Коды станций здесь другие, чем в поиске:** нужны коды конкретных вокзалов
(`2000003` — Москва Казанская), а не города (`2000000` — Москва). Их отдаёт
поиск поездов в полях `OriginStationCode` и `DestinationStationCode` самого
поезда. `Provider` тоже берётся из поезда.

Отдаёт `Cars[]` и `TrainInfo` — данные поезда той же структуры, что в поиске.
У вагона: `FreePlaces` строкой (`"2, 4"`), `FreePlacesByCompartments` с
раскладкой по купе, `RailwayCarSchemeId`, `CarSubType`, цены и услуги.

Буква после номера места (`4М`, `12Ж`, `22С`) означает пол пассажиров в купе:
мужское, женское, смешанное. К номеру места отношения не имеет.

Реализация: `Rzd\Endpoint\Cars::search()`.

## Маршрут поезда

```
GET /apib2b/p/Railway/V1/Search/TrainRoute
```

| Параметр | Значение |
| --- | --- |
| `TrainNumber` | номер поезда |
| `Origin`, `Destination` | коды станций |
| `OriginName`, `DestinationName` | названия станций, сайт передаёт их вместе с кодами |
| `DepartureDate` | `2026-08-05T00:55:00` |
| `Provider` | `P1` |
| `serviceProvider` | `B2B_RZD`, здесь с малой буквы |
| `GetNewRoute` | `true` |

Отдаёт `Routes[]`, у каждого `RouteStops[]` с остановками: `StationName`,
`StationCode`, местное и московское время прибытия и отправления,
`StopDuration` в минутах, `DaysFromFormingStation`, `TimeZoneDifference`.

Вариантов маршрута может быть несколько, например с прицепными вагонами.

Реализация: `Rzd\Endpoint\Routes::forTrain()`, `all()`.

## Схема вагона

```
GET /api/v1/railway-service/carscheme
```

Параметры: `Carrier`, `CarSubType`, `CarNumber`, `ServiceClass`, `TrainNumber`,
`DepartureDate`, `CarNumeration`.

Отдаёт `SchemeId` и относительные пути вариантов чертежа:
`PcSchemeFirstStorey`, `PcSchemeSecondStorey`, `MobileSchemeFirstVertStorey`,
`MobileSchemeSecondVertStorey`. Второй этаж заполнен только у двухэтажных
вагонов.

Сам чертёж:

```
GET /api/v1/carscheme/image/{schemeId}/{view}
```

Где `view` — `PcFirstStorey`, `PcSecondStorey`, `MobileFirstStoreyVert` или
`MobileSecondStoreyVert`. Отдаёт SVG, около 100 КБ.

Одна схема относится к десяткам поездов: в поле `TrainNumber` ответа приходит
длинный список номеров, которым она подходит.

Реализация: `Rzd\Endpoint\Cars::scheme()`, `schemeImage()`.

## Фотографии вагона

```
GET /api/v1/railway-service/carimage/list
```

Те же параметры, что у схемы, но с малой буквы: `carrier`, `carSubType`,
`carNumber`, `serviceClass`, `carNumeration`, `departureDate`. Номер поезда
дублируется трижды: `trainNumber`, `hiddenTrainNumber`, `displayTrainNumber`.

Отдаёт `Images[]` с `TitleRu`, `Preview` и `Content` — путями изображений.

Реализация: `Rzd\Endpoint\Cars::images()`.

## Станции

```
GET /isdk/suggests
```

Параметры: `Query` — часть названия, `TransportType=rail`, `GroupResults=true`,
`Language`. Адрес не выдуман: он указан в конфигурации сайта как
`stations_search_url`.

Отдаёт `transport_node_suggests[]`. Город и его вокзалы приходят разными узлами
с одинаковым кодом `Codes.Railway`, поэтому повторы нужно отбрасывать. Кроме
кода железной дороги в `Codes` лежат коды пригородных перевозок (`Cbdpr`),
автобусов (`Bus`) и авиации (`Avia`).

```
GET /api/v1/popular_cities/{lang}
```

Список популярных городов со связкой `nodeId` ↔ `expressCode`.

```
GET /api/v1/getobject?id={nodeId}
```

Карточка города или станции по идентификатору узла. `nodeId` участвует в адресах
страниц сайта: `/searchresults/v/1/{nodeId0}/{nodeId1}/{дата}`.

Реализация: `Rzd\Endpoint\Stations`.

## Календарь наличия и цен

```
GET /api/v1/railway-service/train-availability
```

Параметры: `originStationCode`, `destinationStationCode`, `from`, `to` в формате
`2026-08-01`. Отдаёт `AvailabilityItems[]` — только даты, на которые есть места,
в формате `01-08-2026`, то есть в обратном порядке относительно запроса.

```
GET /api/v1/railway-service/train-minimal-pricing
```

Параметры: `originCode`, `destinationCode`, `dateFrom`. Отдаёт
`PriceByDepartureDates[]` с минимальными ценами и разбивкой по перевозчикам,
поездам и типам вагонов.

Дата дня приходит в поле **`DepatureDate`** — с опечаткой на стороне сайта.

```
POST /apib2b/e/scheduleDirection
```

Тело: `{OriginCode, DestinationCode, DepartureDate}`. Отдаёт список месяцев
`{year, month, availableDays, saleDays}` — примерно на тринадцать месяцев
вперёд, из которых заполнены только те, где продажа открыта. Отвечает на другой
вопрос, чем `train-availability`: не где есть места, а до какой даты вообще
открыта продажа по направлению.

Реализация: `Rzd\Endpoint\Prices`.

## Справочники

```
POST /apib2b/p/Info/V1/References/Tariffs?service_provider=B2B_RZD
```

Тело — пустой объект `{}`. Именно объект: на `[]` сайт отвечает ошибкой.
Отдаёт около полутора сотен тарифов с условиями применения.

```
GET /api/v1/railway-service/prices/cards
```

Карты и абонементы перевозчиков, около сотни записей: цена карты, скидка в
процентах, число поездок, типы вагонов и классы обслуживания, сроки продажи и
поездки, ограничения по возрасту, правила возврата и обмена. Параметров не
требует.

```
GET /api/v1/app_config
```

Конфигурация фронта, около 120 КБ: флаги доступности функций, лимиты
пассажиров, адреса справочников. Полезные ключи: `stations_search_url`,
`passengers_limit`, `search_mode`, `search_captcha`.

Реализация: `Rzd\Endpoint\References`.

## Поиск с пересадками

```
POST /apib2b/mmp/onewayRoutesStream/v2
```

Единственный запрос библиотеки, которому **нужна кука** `LANG_SITE`. Без неё
ответ `500 Internal Server Error`. Значение сайту безразлично, проверяется
только наличие: подходит и `LANG_SITE=zz`. Сессия, `JSESSIONID` и авторизация
не нужны.

Тело:

| Поле | Значение |
| --- | --- |
| `start_location`, `finish_location` | `{"city": {"key": "5a323c…"}, "type": "city"}` |
| `start_datetime_range` | `{"from": "…T00:00:00", "to": "…T23:59:59"}` |
| `min_trips_in_leg` | рейсов в цепочке не меньше; `1` добавит прямые |
| `max_trips_in_leg` | рейсов не больше, то есть пересадок плюс один |
| `max_results` | предел числа вариантов |
| `filters[].exact_filter.param_values` | `b2brails`, `cbdpr`, `b2bbus`, `b2bavia`, `b2bboat`, `aeroexpress` |
| `system_params.detailed_location` | `true`, иначе в ответе не будет названий станций |

Города задаются **идентификаторами узлов сайта**, а не кодами станций: их
отдаёт подсказка станций в поле `nodeId`. Узел вокзала сайт принимает наравне
с узлом города, но выдача разная: Москва → Архангельск даёт 8 вариантов от
города и 12 от Ярославского вокзала.

Ответ:

```
multi_modal_routes[]         вариант поездки
  routes[]                   плечи, оформляемые одним билетом
    provider.key             b2brails и прочие
    booking_system           Express3
    segments[].trips[]       конкретные рейсы
      race_number            номер поезда
      products[]             классы обслуживания с ценами
      raw_data               полный ответ обычного поиска, см. ниже
  legs[]                     сшитый путь со ссылками на рейсы
  transfers[]                переезды между вокзалами
```

Деньги приходят объектом `{"kopecks": "553410"}`, причём **строкой**.
Длительность — в формате Go, `807.024s`.

В `trips[].raw_data` под ключом `/Railway/V1/Search/TrainPricing` лежит
**целиком ответ обычного поиска поездов** для этого рейса, вместе с
`CarGroups[]`. Отдельный запрос за вагонами и ценами не нужен.

В предложении рейса класс обслуживания брать нужно из `common_service_classes[]`
(`2Э:ФПК`): одиночное `common_service_class.provider_code` дублирует тип
вагона (`Compartment`).

Пустой `transfers[]` означает, что все пересадки происходят на одном вокзале,
а не что пересадок нет.

Реализация: `Rzd\Endpoint\Transfers`.

## Аэроэкспресс

```
POST /apib2b/p/Aeroexpress/V1/Search/TariffPricing
```

Тело: `{DepartureDate}`, коды станций необязательны. Отдаёт `Tariffs[]` с ценой,
типом, условиями применения, лимитом билетов на заказ и списком принимаемых
документов. Поиска поездов у аэроэкспресса нет: место в тарифе обычно не
фиксировано, а билет действует до 90 дней.

Реализация: `Rzd\Endpoint\Aeroexpress`.

## Что осталось за пределами библиотеки

Найдены, но не реализованы, поскольку относятся к покупке билета, а не к поиску:

```
POST /api/v1/railway/car/place/prices          цены по конкретным местам
POST /apib2b/p/Railway/V1/Search/PurchasedPets выкупленные места для животных
```

Покупка требует авторизации в личном кабинете и проходит через капчу, флаги
которой видны в `app_config` (`search_captcha`, `is_audio_captcha_enabled`).

Работают анонимно, но пока не обёрнуты — справочники и служебные данные сайта:

```
GET  /api/v1/countries                             страны с кодами ISO
GET  /api/v1/directions                            популярные направления
GET  /api/v1/langs, /burger_menu, /footer_menu     языки и меню сайта
GET  /api/v1/translations                          строки интерфейса, 745 КБ
```

Существуют, но требуют доразведки — нужен живой запрос со страниц сайта:

```
GET  /api/v1/suburban-trains/{id}/route            маршрут пригородного поезда
GET  /apib2b/calculator/railway                    калькулятор стоимости
POST /apib2b/p/Railway/V1/AdditionalService/Pricing доп. услуги: питание, постель
```

Проверены и работают анонимно, но за пределами задач библиотеки:

```
POST /apib2b/p/Avia/V1/Search/RoutePricing         авиарейсы, service_provider=B2B_IM,
                                                   коды IATA, тело как у аэроэкспресса
POST /apib2b/p/ForeignRailway/V1/Search/RouteList  зарубежные железные дороги,
                                                   {DepartureDate, Origin, Destination,
                                                   Passengers[{PassengerType, Quantity}]}
GET  /api/v1/combined-routes/check                 есть ли комбинированный маршрут
```

Зарубежные направления, куда ходят поезда РЖД (Минск, Калининград), ищутся
обычным `train-pricing` по кодам станций, `ForeignRailway` для них не нужен: на
кодах вида `2100000` он отвечает «Данное направление не поддерживается».

`combined-routes/check` отдаёт `204 No Content` на всех проверенных парах,
включая Крым, Абхазию и направления с пересадками, поэтому что лежит в теле
успешного ответа — неизвестно.

Полный список эндпоинтов фронта (109 путей) извлекается из JS-бандла сайта:
пути перечислены в `main-*.bundle.js` и чанках `chunk-*.bundle.js`, ссылки на
которые есть в HTML главной страницы.
