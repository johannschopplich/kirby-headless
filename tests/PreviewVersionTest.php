<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Middlewares;
use Kirby\Cms\App;
use Kirby\Content\VersionId;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PreviewVersionTest extends TestCase
{
    private string $root = __DIR__ . '/fixtures/versions';

    protected function setUp(): void
    {
        Dir::make($this->root . '/templates');
        Dir::make($this->root . '/content/notes');
        F::write($this->root . '/templates/default.php', '<?php echo json_encode(["title" => $page->title()->value()]);');
        F::write($this->root . '/content/notes/default.txt', "Title: Saved title\n");
    }

    protected function tearDown(): void
    {
        $_GET = [];
        App::destroy();
        Dir::remove($this->root);
    }

    /**
     * This is the Panel's live preview: the editor sees what they have typed
     * but not yet saved, which only works if the requested version reaches the
     * template through `VersionId::render()`.
     */
    #[Test]
    public function renders_the_unsaved_changes_version_for_a_valid_preview_token(): void
    {
        $kirby = $this->app();
        $version = $kirby->page('notes')->version(VersionId::changes());
        $version->save(['title' => 'Unsaved title']);
        $token = $version->previewToken();
        App::destroy();

        $_GET = ['_token' => $token, '_version' => 'changes'];
        $this->app();

        $this->assertSame('{"title":"Unsaved title"}', Middlewares::tryResolvePage([], ['notes'])->body());
    }

    #[Test]
    public function renders_the_saved_version_without_a_token(): void
    {
        $kirby = $this->app();
        $kirby->page('notes')->version(VersionId::changes())->save(['title' => 'Unsaved title']);
        App::destroy();

        $this->app();

        $this->assertSame('{"title":"Saved title"}', Middlewares::tryResolvePage([], ['notes'])->body());
    }

    /**
     * Content versions live on disk, so this fixture needs a real content root
     * rather than the in-memory site the other page tests boot.
     */
    private function app(): App
    {
        return new App([
            'roots' => [
                'index' => $this->root,
                'templates' => $this->root . '/templates',
                'content' => $this->root . '/content',
                'cache' => $this->root . '/cache',
                'sessions' => $this->root . '/sessions',
                'accounts' => $this->root . '/accounts'
            ]
        ]);
    }
}
