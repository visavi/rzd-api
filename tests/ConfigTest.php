<?php

declare(strict_types=1);

namespace Rzd\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Rzd\Config;
use Rzd\Enum\Language;
use Rzd\Enum\ServiceProvider;

final class ConfigTest extends BaseTestCase
{
    #[Test]
    public function hasSensibleDefaults(): void
    {
        $config = new Config();

        self::assertSame(Language::Russian, $config->language);
        self::assertSame(ServiceProvider::Rzd, $config->serviceProvider);
        self::assertSame(Config::DEFAULT_USER_AGENT, $config->userAgent);
        self::assertSame('https://ticket.rzd.ru', $config->baseUri);
        self::assertSame([], $config->headers);
    }

    #[Test]
    public function acceptsNamedArguments(): void
    {
        $config = new Config(
            language: Language::English,
            userAgent: 'MyApp/1.0',
            baseUri: 'https://example.com',
            headers: ['X-Test' => '1'],
        );

        self::assertSame(Language::English, $config->language);
        self::assertSame('MyApp/1.0', $config->userAgent);
        self::assertSame('https://example.com', $config->baseUri);
        self::assertSame(['X-Test' => '1'], $config->headers);
    }

    #[Test]
    public function withLanguageKeepsOriginalIntact(): void
    {
        $config = new Config();
        $english = $config->withLanguage(Language::English);

        self::assertSame(Language::English, $english->language);
        self::assertSame(Language::Russian, $config->language);
        self::assertNotSame($config, $english);
    }

    #[Test]
    public function withUserAgentKeepsOtherValues(): void
    {
        $config = new Config(language: Language::English, headers: ['X-Test' => '1']);
        $copy = $config->withUserAgent('Other/2.0');

        self::assertSame('Other/2.0', $copy->userAgent);
        self::assertSame(Language::English, $copy->language);
        self::assertSame(['X-Test' => '1'], $copy->headers);
    }

    #[Test]
    public function withHeadersMergesIntoExisting(): void
    {
        $config = (new Config(headers: ['X-First' => '1']))->withHeaders(['X-Second' => '2']);

        self::assertSame(['X-First' => '1', 'X-Second' => '2'], $config->headers);
    }

    #[Test]
    public function withHeadersOverwritesSameName(): void
    {
        $config = (new Config(headers: ['X-Test' => 'старое']))->withHeaders(['X-Test' => 'новое']);

        self::assertSame(['X-Test' => 'новое'], $config->headers);
    }
}
