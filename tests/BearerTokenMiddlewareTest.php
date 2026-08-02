<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Middlewares;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BearerTokenMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_ACCEPT']);
        App::destroy();
    }

    public static function blankTokens(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ['   ']
        ];
    }

    #[Test]
    #[DataProvider('blankTokens')]
    public function rejects_requests_when_the_configured_token_is_blank(string $token): void
    {
        // Even a client sending exactly the configured token is turned away
        $this->bootKirby(['headless' => ['token' => $token]], 'Bearer ' . $token);

        $this->assertSame(401, Middlewares::validateBearerToken()?->code());
    }

    #[Test]
    public function accepts_a_token_php_considers_falsy(): void
    {
        $this->bootKirby(['headless' => ['token' => '0']], 'Bearer 0');

        $this->assertNull(Middlewares::validateBearerToken());
    }

    #[Test]
    public function guards_the_site_with_a_token_php_considers_falsy(): void
    {
        $this->bootKirby(['headless' => ['token' => '0']]);

        $this->assertSame(401, Middlewares::validateBearerToken()?->code());
    }

    #[Test]
    public function allows_requests_when_no_token_is_configured(): void
    {
        $this->bootKirby([]);

        $this->assertNull(Middlewares::validateBearerToken());
    }

    /**
     * Handing a browser to the Panel is opt-in. A site that never asked for it
     * would otherwise bounce every visitor out of the frontend and into the
     * backend the moment a token is configured.
     */
    #[Test]
    public function keeps_a_browser_out_unless_the_panel_redirect_is_enabled(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

        $this->bootKirby(['headless' => ['token' => 'secret']]);

        $this->assertSame(401, Middlewares::validateBearerToken(true)?->code());
    }

    #[Test]
    public function redirects_to_the_panel_when_the_authorization_header_is_missing(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

        $this->bootKirby([
            'headless' => [
                'token' => 'secret',
                'panel' => ['redirect' => true]
            ]
        ]);

        $this->assertSame(302, Middlewares::validateBearerToken(true)?->code());
    }

    /**
     * Only a browser navigation is worth redirecting – a client asking for
     * JSON gets an answer it can parse, so a tokenless site stays reachable
     * without a meaningless `Authorization` header.
     */
    #[Test]
    public function answers_a_client_that_asked_for_json_instead_of_redirecting(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $this->bootKirby([
            'headless' => [
                'token' => 'secret',
                'panel' => ['redirect' => true]
            ]
        ]);

        $this->assertSame(401, Middlewares::validateBearerToken(true)?->code());
    }

    /**
     * `prefersJson()` is a test for JSON, not against HTML: a client that
     * sends no `Accept` header at all prefers neither, and answering it with
     * a Panel redirect would strand every HTTP client that omits the header.
     */
    #[Test]
    public function answers_a_client_that_stated_no_preference_instead_of_redirecting(): void
    {
        $this->bootKirby([
            'headless' => [
                'token' => 'secret',
                'panel' => ['redirect' => true]
            ]
        ]);

        $this->assertSame(401, Middlewares::validateBearerToken(true)?->code());
    }

    /**
     * The request headers are read from `$_SERVER` when Kirby boots,
     * so the Authorization header has to be in place beforehand.
     */
    private function bootKirby(array $options, string|null $authorization = null): void
    {
        if ($authorization !== null) {
            $_SERVER['HTTP_AUTHORIZATION'] = $authorization;
        }

        new App([
            'roots' => ['index' => __DIR__],
            'options' => $options
        ]);
    }
}
