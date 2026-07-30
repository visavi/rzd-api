<?php

declare(strict_types=1);

/**
 * Страница со ссылками на примеры, открывается через встроенный сервер
 *
 * Подключается из index.php, когда запуск идет не из консоли.
 *
 * @var array<string, string> $examples Список примеров с описаниями
 * @var string|false          $proxy    Прокси из переменной окружения
 */

if (! isset($examples)) {
    http_response_code(500);

    exit('Страница подключается из index.php');
}

?>
<!doctype html>
<meta charset="utf-8">
<title>Примеры работы с API РЖД</title>
<style>
    body { max-width: 46rem; margin: 2rem auto; padding: 0 1rem; font: 16px/1.5 system-ui, sans-serif; }
    code { background: #f2f2f2; padding: .1rem .3rem; border-radius: .2rem; }
    .note { background: #eef4fb; padding: .8rem 1rem; border-radius: .3rem; margin: 1rem 0; }
    .warn { background: #fbf0e4; }
    ul { padding-left: 0; list-style: none; }
    li { margin: .9rem 0; }
    a { font-weight: 600; }
    p.desc { margin: .15rem 0 0; color: #444; }
</style>

<h1>Примеры работы с API РЖД</h1>

<?php if (! $proxy) { ?>
    <div class="note warn">
        Сайт РЖД принимает запросы только с российских адресов. Вне РФ примеры
        завершатся ошибкой соединения, серверу нужен прокси:<br>
        <code>RZD_PROXY=socks5://127.0.0.1:1080 php -S localhost:8000 -t examples</code>
    </div>
<?php } ?>

<div class="note">
    Каждый пример запускается и из консоли:<br>
    <code>php examples/search_trains.php</code><br><br>
    Описание методов и форматов ответа — в
    <a href="https://github.com/visavi/rzd-api/blob/master/readme.md">readme</a>.
</div>

<ul>
    <?php foreach ($examples as $example => $description) { ?>
        <li>
            <a href="<?= htmlspecialchars($example, ENT_QUOTES) ?>.php"><?= htmlspecialchars($example, ENT_QUOTES) ?>.php</a>
            <p class="desc"><?= htmlspecialchars($description, ENT_QUOTES) ?></p>
        </li>
    <?php } ?>
</ul>
