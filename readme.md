# Api сайта rzd.ru

[![Packagist](https://img.shields.io/packagist/v/visavi/rzd-api.svg)](https://packagist.org/packages/visavi/rzd-api)
[![Tests](https://github.com/visavi/rzd-api/actions/workflows/tests.yml/badge.svg)](https://github.com/visavi/rzd-api/actions/workflows/tests.yml)
[![Coverage](https://coveralls.io/repos/github/visavi/rzd-api/badge.svg?branch=master)](https://coveralls.io/github/visavi/rzd-api?branch=master)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.0-777bb4.svg)](https://www.php.net/releases/8.0/)
[![Downloads](https://img.shields.io/packagist/dt/visavi/rzd-api.svg)](https://packagist.org/packages/visavi/rzd-api)
[![License](https://img.shields.io/packagist/l/visavi/rzd-api.svg)](https://github.com/visavi/rzd-api/blob/master/composer.json)

### Что умеет Api
* Получает маршруты в одну точку
* Получает маршруты туда-обратно
* Получает список вагонов выбранного поезда
* Получает список станций в пути следования выбранного маршрута
* Получает коды станций вместе с часовым поясом и кодами смежных видов транспорта

### Содержание

* [Установка](#установка)
* [Демонстрация возможностей](#демонстрация-возможностей)
* [Пример запроса](#пример-запроса)
* [Как устроен обмен с сайтом](https://github.com/visavi/rzd-api/blob/master/docs/protocol.md)
* Методы
  * [trainRoutes](#trainroutes---получает-маршруты-поездов-количество-свободных-мест-цены-итд-в-нем-в-один-конец) - маршруты в одну сторону
  * [trainRoutesReturn](#trainroutesreturn---получает-маршруты-поездов-количество-свободных-мест-цены-итд-туда-обратно) - маршруты туда-обратно
  * [trainCarriages](#traincarriages---получает-список-вагонов-свободные-места-схема-вагона-стоимость-билетов-тип-и-класс-обслуживания) - вагоны и места
  * [trainStationList](#trainstationlist---получение-списка-всех-станций-в-текущем-маршруте-движения) - станции в пути следования
  * [stationCode](#stationcode---получение-списка-кодов-станций) - коды станций
* [Тесты](#тесты)

### Установка

```sh
composer require visavi/rzd-api
```

Требуется PHP 8.0 и расширения json, curl, mbstring

Сайт rzd.ru принимает запросы только с российских адресов. Вне РФ понадобится прокси,
он задается через `Config::setProxy()`

[Подробное описание установки](https://github.com/visavi/rzd-api/blob/master/docs/install.md)

### Демонстрация возможностей

Скачайте архив, распакуйте и перейдите в директорию

Установите необходимые зависимости
```sh
composer install
```

И запустите встроенный веб-сервер
```sh
php -S localhost:8000 -t examples
```

Сайт rzd.ru принимает запросы только с российских адресов, вне РФ понадобится прокси
```sh
RZD_PROXY=socks5://127.0.0.1:1080 php -S localhost:8000 -t examples
```

Каждый пример запускается и отдельно
```sh
php examples/train_routes.php
```

### Пример запроса
```php
<?php

$config = new Rzd\Config();

// Set language
$config->setLanguage('en');

// Set userAgent, по умолчанию задан браузерный, без него сайт отвечает 403
$config->setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 12_1_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 Mobile/15E148 Safari/604.1');

// Set referer
$config->setReferer('https://ticket.rzd.ru/');

// Enable debug mode
$config->setDebugMode(true);

// Set proxy, сайт принимает запросы только с российских адресов
$config->setProxy('socks5://127.0.0.1:1080');

// Set timeout
$config->setTimeout(10);

// Пауза в секундах перед повторным запросом с полученным RID, по умолчанию 1
$config->setRetryDelay(2);

// Подмена транспорта Guzzle, используется в тестах для моков
//$config->setHandler($handlerStack);

//$config не обязателен
$api = new Rzd\Api($config);

// В примере выполняется поиск маршрута САНКТ-ПЕТЕРБУРГ - МОСКВА (только с билетами) на завтра
$params = [
    'dir'          => 0, // 0 - только в один конец, 1 - туда-обратно
    'tfl'          => 3, // 3 - поезда и электрички, 2 - электрички, 1 - поезда 
    'checkSeats'   => 1, // 1 - только с билетами, 0 - все поезда
    //'withoutSeats' => 'y', // Если checkSeats = 0, то этот параметр тоже необходим
    // Коды станций можно получить отдельным запросом
    'code0'        => '2004000', // код станции отправления
    'code1'        => '2000000', // код станции прибытия
    'dt0'          => 'дата на завтра d.m.Y',
    'md'           => 0, // 0 - без пересадок, 1 - с пересадками
];

$routes = $api->trainRoutes($params);
```

## Реализованные запросы

Библиотека сама выполняет двухшаговый обмен с сайтом: первый запрос возвращает
идентификатор RID и куки, второй подставляет их и получает данные. Механика описана
в [отдельном документе](https://github.com/visavi/rzd-api/blob/master/docs/protocol.md),
для работы с методами знать ее не нужно

### trainRoutes - получает маршруты поездов, количество свободных мест, цены итд в нем в один конец

![Маршруты](https://raw.githubusercontent.com/visavi/rzd-api/master/screens/trainRoute.png)

Принимает параметры, все необязательные
* dir - 0 - только в один конец, 1 - туда-обратно
* tfl - 3 - поезда и электрички, 2 - электрички, 1 - поезда
* checkSeats - 0, 1 - поиск в поездах только если есть свободные места
* code0 - код станции отправления
* code1 - код станции прибытия
* dt0 - дата отправления
* md - маршруты с пересадками (1 - с пересадками, 0 - только прямые рейсы)

Параметр layer_id подставляется библиотекой, передавать его не нужно

Возвращает данные направления
* from, fromCode - станция и код отправления
* where, whereCode - станция и код прибытия
* date - дата отправления
* noSeats - true если мест нет вообще
* state - состояние выдачи
* msgList - сообщения сайта
* list - список поездов

Каждый поезд в list содержит
* date0 - дата отправления
* date1 - дата прибытия
* time0 - время отправления
* time1 - время прибытия
* route0 - код станции отправления С-ПЕТ-ЛАД
* route1 - код станции прибытия ТЮМЕНЬ
* number - номер поезда
* timeInWay - время в пути
* brand - Название поезда (Демидовский экспресс)
* carrier - тип поезда ФПК (Фирменный)

* cars - массив свободных мест купе, плацкарт и люкс
* cars.freeSeats - кол.  свободных мест
* cars.itype
* cars.servCls - класс обслуживания (2Ю, 2Ж и т.д.)
* cars.tariff - стоимость билета
* cars.pt - баллы
* cars.typeLoc - полное наименование (Плацкартный, СВ, Купе, Люкс)
* cars.type - сокращенное наименование (Купе, плац, люкс)
* cars.disabledPerson - флаг обозначающий места для инвалидов

### trainRoutesReturn - получает маршруты поездов, количество свободных мест, цены итд, туда-обратно
![Поезда](https://raw.githubusercontent.com/visavi/rzd-api/master/screens/trainRouteReturn.png)

Принимает параметры, все необязательные
* dir - 0 только в один конец, 1 - туда-обратно
* tfl - тип поезда (3 - поезда и электрички, 2 - электрички, 1 - поезда)
* checkSeats поиск только с билетами (1 - с билетами, 0 - все поезда)
* code0 - код станции отправления
* code1 - код станции прибытия
* dt0 - дата отправления
* dt1 - дата возвращения

Возвращает два блока в ключах forward и back, каждый устроен так же, как ответ trainRoutes

### trainCarriages - получает список вагонов, свободные места, схема вагона, стоимость билетов, тип и класс обслуживания
![Вагоны](https://raw.githubusercontent.com/visavi/rzd-api/master/screens/trainCarriages.png)

Необязательные параметр при повторном запросе
* dir - 0 только в один конец, 1 - туда-обратно
* code0 - код станции отправления
* code1 - код станции прибытия
* dt0 - дата отправления (28.03.2016)
* time0 - время отправления (15:30)
* tnum0 - номер поезда (072Е)

Возвращает объект с ключами
* cars - список вагонов, описан ниже
* functionBlocks - доступные действия
* schemes - схемы вагонов
* companies - страховые компании
* train - данные поезда без списка вагонов: номер, бренд, станции и время отправления и прибытия
* companyTypes - типы страховок с тарифами и суммами покрытия
* childrenAge - предельный возраст детского билета
* motherAndChildAge - предельный возраст для тарифа мать и дитя
* foodIconTips - расшифровка значков питания
* psaction - действующие акции
* partialPayment - условия частичной оплаты

Каждый вагон в cars содержит
* cnumber - номер вагона
* type - тип вагона
* typeLoc - полное наименование (Плацкартный, СВ, Купе, Люкс)
* clsType - 2Л, 2Э
* tariff - стоимость билета
* tariffServ - сервис сбор

* seats - массив мест (верхние, верхние боковые, нижние, нижние боковые итд)
* seats.*.places - список свободных мест
* seats.*.tariff - цены за место
* seats.type - сокр. наименование мест (up)
* seats.free - количество мест
* seats.label - полное наименование мест (Верхние)

* schemes схемы вагонов
* html - json массив информация о схеме вагонов
* image - ссылка на картинку

* insuranceCompany - массив с компаниями страхователями и правилами страхования
* shortName - наименование организации
* offerUrl - ссылка на файл с правилами, обычно PDF файл

### trainStationList - получение списка всех станций в текущем маршруте движения
![Станции](https://raw.githubusercontent.com/visavi/rzd-api/master/screens/trainStationList.png)

Запрос идет на адрес https://pass.rzd.ru/ticket/services/route/basicRoute

Принимает параметры
* trainNumber - номер поезда (022А)
* depDate - дата отправления (05.08.2026)

Возвращает объект с ключами
* train - номер поезда и маршрут следования: name, engName, code
* routes - варианты маршрута, каждый содержит title, route и список остановок stops

Каждая остановка в stops содержит
* station.name, station.engName, station.code - станция и ее код
* arvTime, depTime - время прибытия и отправления по местному времени
* arvTimeMSK, depTimeMSK - то же по московскому времени
* waitingTime - время стоянки

### stationCode - Получение списка кодов станций

Принимает параметры
* stationNamePart - часть названия станции, минимум 2 символа

Возвращает массив найденных данных
* station - имя станции
* code - код станции
* region - страна и регион
* type - тип узла: Город или Станция
* timezone - часовой пояс станции, нужен для корректной даты отправления
* codes - коды смежных видов транспорта: Railway, Cbdpr (пригородные перевозки), Bus, Avia, Aeroexpress, ForeignRailway
* stations - коды всех вокзалов города

К примеру при значении stationNamePart = 'ЧЕБ' будут возвращены станции, начинающиеся
на ЧЕБ: Чебоксары, Чебаркуль, Чебсара и другие

Города и станции приходят от сайта разными узлами с одинаковым кодом, метод отдает их без повторов

### Тесты

Основные тесты работают на моках и не обращаются к сети
```sh
composer test
```

Тесты группы live проверяют реальные ответы сайта и ловят смену протокола. В обычный прогон и в CI они не попадают, запускаются отдельно
```sh
RZD_PROXY=socks5://127.0.0.1:1080 composer test:live
```

### License

The class is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
