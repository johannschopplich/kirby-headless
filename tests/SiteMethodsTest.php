<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SiteMethodsTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function frontend_url_replaces_the_kirby_base_url_with_the_configured_one(): void
    {
        $kirby = new App([
            'roots' => ['index' => __DIR__],
            'urls' => ['index' => 'https://example.com'],
            'options' => ['headless.panel.frontendUrl' => 'https://frontend.test']
        ]);

        $this->assertSame('https://frontend.test', $kirby->site()->frontendUrl());
    }

    #[Test]
    public function frontend_url_returns_null_when_not_configured(): void
    {
        $kirby = new App(['roots' => ['index' => __DIR__]]);

        $this->assertNull($kirby->site()->frontendUrl());
    }
}
