<h1>Примеры запросов</h1>

<div style="background: sandybrown; padding: 5px; font-weight: bold">
    Обращаем внимание на то, что по новым условиям RZD.RU дату отправки нужно указывать с учетом часового пояса станции отправления
</div>

<div style="background: aliceblue; padding: 5px; margin-top: 5px">
    Сайт rzd.ru принимает запросы только с российских адресов, вне РФ нужен прокси:<br>
    <code>RZD_PROXY=socks5://127.0.0.1:1080 php -S localhost:8000 -t examples</code><br><br>
    Каждый пример запускается и из консоли:<br>
    <code>php examples/train_routes.php</code><br><br>
    Описание всех параметров и форматов ответа находится в
    <a href="https://github.com/visavi/rzd-api/blob/master/readme.md">readme</a>
</div>

<h3><a href="station_code.php">station_code.php</a></h3>
Коды станций, начинающихся с ЧЕБ, с часовым поясом и кодами смежных видов транспорта

<h3><a href="train_routes.php">train_routes.php</a></h3>
Маршруты САНКТ-ПЕТЕРБУРГ - МОСКВА, только с билетами, на завтра

<h3><a href="train_routes_params.php">train_routes_params.php</a></h3>
Тот же маршрут с измененными настройками: язык en, свои userAgent и referer

<h3><a href="train_routes_return.php">train_routes_return.php</a></h3>
Маршруты туда-обратно: завтра туда и через 5 дней обратно

<h3><a href="train_routes_transfer.php">train_routes_transfer.php</a></h3>
Маршруты НОВЫЙ УРЕНГОЙ - АБАКАН с пересадками, параметр md

<h3><a href="train_carriages.php">train_carriages.php</a></h3>
Вагоны, свободные места и цены для первого поезда из найденного маршрута

<h3><a href="train_station_list.php">train_station_list.php</a></h3>
Станции в пути следования для первого поезда из найденного маршрута
