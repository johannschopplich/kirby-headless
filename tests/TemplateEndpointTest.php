<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TemplateEndpointTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function serves_a_404_for_a_missing_template(): void
    {
        $kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'templates' => __DIR__ . '/fixtures/templates'
            ]
        ]);

        $response = $kirby->router()->call('api/__template__/missing', 'GET');

        $this->assertSame(404, $response->code());
    }
}
