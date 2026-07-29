<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require dirname(__DIR__) . '/vendor/autoload.php';

$cli = PHP_SAPI === 'cli';

if (! $cli) {
    echo '<pre>';
    register_shutdown_function(static function (): void {
        echo '</pre>';
    });
}

$config = new Rzd\Config();
$config->setTimeout(30);

// Сайт пускает только российские адреса, вне РФ нужен прокси
if ($proxy = getenv('RZD_PROXY')) {
    $config->setProxy($proxy);
}

$api = new Rzd\Api($config);

/**
 * Печатает ответ Api и возвращает его декодированным
 *
 * @param string $response
 *
 * @return array
 */
function show(string $response): array
{
    $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

    return $data;
}
