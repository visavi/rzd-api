<?php

namespace Rzd\Tests;

use PHPUnit\Framework\TestCase;
use Rzd\Config;

class ConfigTest extends TestCase
{
    /**
     * Тест значений по умолчанию
     */
    public function testDefaults(): void
    {
        $config = new Config();

        $this->assertSame('ru', $config->getLanguage());
        $this->assertSame(5.0, $config->getTimeout());
        $this->assertSame(1, $config->getRetryDelay());
        $this->assertFalse($config->getDebugMode());
        $this->assertNull($config->getProxy());
        $this->assertNull($config->getReferer());
        $this->assertNull($config->getHandler());
        $this->assertSame(Config::DEFAULT_USER_AGENT, $config->getUserAgent());
    }

    /**
     * Тест изменения настроек
     */
    public function testSetters(): void
    {
        $config = new Config();
        $handler = static fn() => null;

        $config->setLanguage('en');
        $config->setTimeout(30.0);
        $config->setRetryDelay(3);
        $config->setDebugMode(true);
        $config->setProxy('socks5://127.0.0.1:1080');
        $config->setUserAgent('TestAgent/1.0');
        $config->setReferer('https://ticket.rzd.ru/');
        $config->setHandler($handler);

        $this->assertSame('en', $config->getLanguage());
        $this->assertSame(30.0, $config->getTimeout());
        $this->assertSame(3, $config->getRetryDelay());
        $this->assertTrue($config->getDebugMode());
        $this->assertSame('socks5://127.0.0.1:1080', $config->getProxy());
        $this->assertSame('TestAgent/1.0', $config->getUserAgent());
        $this->assertSame('https://ticket.rzd.ru/', $config->getReferer());
        $this->assertSame($handler, $config->getHandler());
    }

    /**
     * Обработчик можно сбросить
     */
    public function testHandlerCanBeReset(): void
    {
        $config = new Config();

        $config->setHandler(static fn() => null);
        $config->setHandler(null);

        $this->assertNull($config->getHandler());
    }
}
