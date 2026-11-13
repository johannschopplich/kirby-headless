<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TemplateEndpointTest extends TestCase
{
    private string $root = __DIR__ . '/fixtures/template-endpoint';

    protected function setUp(): void
    {
        Dir::make($this->root . '/templates');
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        unset($_SERVER['HTTP_AUTHORIZATION']);
        App::destroy();
        Dir::remove($this->root);
    }

    /**
     * Boots a fresh app per request that shares only what is on disk;
     * `App::destroy()` in between would also drop the plugin's routes.
     */
    private function app(): App
    {
        return new App([
            'roots' => [
                'index' => $this->root,
                'templates' => $this->root . '/templates',
                'cache' => $this->root . '/cache'
            ],
            'options' => [
                'cache' => ['pages' => ['active' => true]]
            ]
        ]);
    }

    private function writeTemplate(string $contents): void
    {
        F::write($this->root . '/templates/probe.php', $contents);
    }

    #[Test]
    public function serves_a_404_for_a_missing_template(): void
    {
        $response = $this->app()->router()->call('api/__template__/missing', 'GET');

        $this->assertSame(404, $response->code());
    }

    #[Test]
    public function names_the_missing_template_in_the_error_message(): void
    {
        $body = $this->app()->router()->call('api/__template__/missing', 'GET')->body();

        $this->assertSame('The template "missing" does not exist', Json::decode($body)['message']);
    }

    #[Test]
    public function answers_with_the_status_code_the_template_sets(): void
    {
        $this->writeTemplate('<?php $kirby->response()->code(418); echo "{}";');

        $response = $this->app()->router()->call('api/__template__/probe', 'GET');

        $this->assertSame(418, $response->code());
    }

    #[Test]
    public function replays_the_headers_the_template_sets_on_a_cache_hit(): void
    {
        $this->writeTemplate('<?php $kirby->response()->header("X-Custom", "yes"); echo "{}";');
        $this->app()->router()->call('api/__template__/probe', 'GET');

        $this->writeTemplate('<?php echo "{}";');
        $response = $this->app()->router()->call('api/__template__/probe', 'GET');

        $this->assertSame('yes', $response->header('X-Custom'));
    }

    #[Test]
    public function leaves_a_response_other_than_200_out_of_the_cache(): void
    {
        $this->writeTemplate('<?php $kirby->response()->code(418); echo \'{"value":"first"}\';');
        $this->app()->router()->call('api/__template__/probe', 'GET');

        $this->writeTemplate('<?php echo \'{"value":"second"}\';');
        $body = $this->app()->router()->call('api/__template__/probe', 'GET')->body();

        $this->assertSame(['value' => 'second'], Json::decode($body)['result']);
    }

    #[Test]
    public function leaves_a_template_that_calls_cache_false_out_of_the_cache(): void
    {
        $this->writeTemplate('<?php $kirby->response()->cache(false); echo \'{"value":"first"}\';');
        $this->app()->router()->call('api/__template__/probe', 'GET');

        $this->writeTemplate('<?php echo \'{"value":"second"}\';');
        $body = $this->app()->router()->call('api/__template__/probe', 'GET')->body();

        $this->assertSame(['value' => 'second'], Json::decode($body)['result']);
    }

    #[Test]
    public function drops_a_cached_template_once_its_expires_time_has_passed(): void
    {
        $this->writeTemplate('<?php $kirby->response()->expires(time() - 60); echo \'{"value":"first"}\';');
        $this->app()->router()->call('api/__template__/probe', 'GET');

        $this->writeTemplate('<?php echo \'{"value":"second"}\';');
        $body = $this->app()->router()->call('api/__template__/probe', 'GET')->body();

        $this->assertSame(['value' => 'second'], Json::decode($body)['result']);
    }

    #[Test]
    public function keeps_a_template_rendered_with_a_kirby_session_cookie_out_of_the_cache(): void
    {
        $_COOKIE['kirby_session'] = 'session';
        $this->writeTemplate('<?php echo json_encode([\'user\' => $kirby->user()?->email()]);');
        $this->app()->router()->call('api/__template__/probe', 'GET');

        $_COOKIE = [];
        $this->writeTemplate('<?php echo \'{"value":"second"}\';');
        $body = $this->app()->router()->call('api/__template__/probe', 'GET')->body();

        $this->assertSame(['value' => 'second'], Json::decode($body)['result']);
    }

    #[Test]
    public function keeps_a_cached_template_that_reads_the_authorization_header_from_a_client_that_sends_one(): void
    {
        $this->writeTemplate('<?php $kirby->request()->auth(); echo \'{"value":"first"}\';');
        $this->app()->router()->call('api/__template__/probe', 'GET');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
        $this->writeTemplate('<?php echo \'{"value":"second"}\';');
        $body = $this->app()->router()->call('api/__template__/probe', 'GET')->body();

        $this->assertSame(['value' => 'second'], Json::decode($body)['result']);
    }

    #[Test]
    public function keeps_a_cached_template_that_reads_a_cookie_from_a_client_that_sends_it(): void
    {
        $this->writeTemplate('<?php \Kirby\Http\Cookie::get(\'consent\'); echo \'{"value":"first"}\';');
        $this->app()->router()->call('api/__template__/probe', 'GET');

        $_COOKIE['consent'] = 'yes';
        $this->writeTemplate('<?php echo \'{"value":"second"}\';');
        $body = $this->app()->router()->call('api/__template__/probe', 'GET')->body();

        $this->assertSame(['value' => 'second'], Json::decode($body)['result']);
    }
}
