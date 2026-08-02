<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Middlewares;
use Kirby\Cms\App;
use Kirby\Content\VersionId;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ResolvePageMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        App::destroy();
    }

    #[Test]
    public function resolves_a_page_whose_slug_is_zero_instead_of_the_homepage(): void
    {
        $this->app();

        $this->assertSame('{"id":"0"}', Middlewares::tryResolvePage([], ['0'])->body());
    }

    #[Test]
    public function falls_back_to_the_homepage_for_an_empty_path(): void
    {
        $this->app();

        $this->assertSame('{"id":"home"}', Middlewares::tryResolvePage([], [''])->body());
    }

    #[Test]
    public function serves_a_404_for_an_extension_without_a_representation(): void
    {
        $this->app();

        $this->assertSame(404, Middlewares::tryResolvePage([], ['about.txt'])->code());
    }

    #[Test]
    public function renders_the_content_representation_for_a_matching_extension(): void
    {
        $this->app();

        $response = Middlewares::tryResolvePage([], ['about.xml']);

        $this->assertSame('<xml>about</xml>', $response->body());
        $this->assertSame('text/xml', $response->type());
    }

    /**
     * Page JSON comes from the default template – that is the whole point of
     * the plugin – so a site without a `*.json.php` representation keeps working.
     */
    #[Test]
    public function renders_page_json_from_the_default_template(): void
    {
        $this->app();

        $this->assertSame('{"id":"about"}', Middlewares::tryResolvePage([], ['about.json'])->body());
    }

    /**
     * Kirby hands the response object to the template, so a JSON template can
     * set a status code or a header the same way an HTML one does.
     */
    #[Test]
    public function lets_the_template_configure_the_response(): void
    {
        $this->app();

        $response = Middlewares::tryResolvePage([], ['responder']);

        $this->assertSame(201, $response->code());
        $this->assertSame('yes', $response->header('X-Custom'));
        $this->assertSame('application/json', $response->type());
    }

    /**
     * The Panel links previews with a token, which is what lets an editor see
     * a page before it is published – Kirby honors it in `App::resolve()`.
     */
    #[Test]
    public function renders_a_draft_for_a_valid_preview_token(): void
    {
        $this->app();
        $token = App::instance()->site()->draft('secret')->version(VersionId::latest())->previewToken();
        App::destroy();

        $_GET = ['_token' => $token, '_version' => 'latest'];
        $this->app();

        $this->assertSame('{"id":"secret"}', Middlewares::tryResolvePage([], ['secret'])->body());
    }

    #[Test]
    public function keeps_a_draft_hidden_without_a_preview_token(): void
    {
        $this->app();

        $this->assertSame(404, Middlewares::tryResolvePage([], ['secret'])->code());
    }

    #[Test]
    public function redirects_the_default_content_type_to_the_page_url(): void
    {
        $this->app();

        $response = Middlewares::tryResolvePage([], ['about.html']);

        $this->assertSame(301, $response->code());
        $this->assertSame('/about', $response->header('Location'));
    }

    private function app(): void
    {
        new App([
            'roots' => [
                'index' => __DIR__,
                'templates' => __DIR__ . '/fixtures/templates'
            ],
            'site' => [
                'drafts' => [
                    ['slug' => 'secret', 'template' => 'default']
                ],
                'children' => [
                    ['slug' => 'home', 'template' => 'default'],
                    ['slug' => 'about', 'template' => 'default'],
                    ['slug' => '0', 'template' => 'default'],
                    ['slug' => 'responder', 'template' => 'responder']
                ]
            ]
        ]);
    }
}
