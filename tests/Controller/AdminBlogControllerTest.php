<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Content\Application\BlogArticleRepository;
use App\Content\Application\MarkdownRenderer;
use App\Market\Presentation\Http\Admin\AdminBlogController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

final class AdminBlogControllerTest extends TestCase
{
    private string $secret = 'test-secret-key';

    public function testRedirectsToDashboardWhenUnauthenticated(): void
    {
        $repo = $this->createStub(BlogArticleRepository::class);
        $markdown = new MarkdownRenderer();
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('market_admin_dashboard_root')
            ->willReturn('/admin');

        $container = new Container();
        $container->set('router', $router);

        $controller = new AdminBlogController($repo, $markdown, $this->secret);
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->list($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin', $response->getTargetUrl());
    }

    public function testRendersListWhenAuthenticated(): void
    {
        $repo = $this->createMock(BlogArticleRepository::class);
        $repo->expects(self::once())
            ->method('findAllForAdmin')
            ->with(null)
            ->willReturn([]);

        $markdown = new MarkdownRenderer();

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/blog/index.html.twig', self::callback(static fn (array $data): bool => array_key_exists('articles', $data)))
            ->willReturn('<html>list</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new AdminBlogController($repo, $markdown, $this->secret);
        $controller->setContainer($container);

        $request = new Request();
        $request->headers->set('X-Admin-Token', $this->secret);

        $response = $controller->list($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>list</html>', $response->getContent());
    }

    public function testPreviewEndpointRendersMarkdownAndCalculatesStats(): void
    {
        $repo = $this->createStub(BlogArticleRepository::class);
        $markdown = new MarkdownRenderer();

        $controller = new AdminBlogController($repo, $markdown, $this->secret);

        $request = new Request([], ['content' => "## Prosty poradnik\nKrótki tekst bez trudnych słów."]);
        $request->headers->set('X-Admin-Token', $this->secret);

        $response = $controller->preview($request);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('html', $data);
        self::assertArrayHasKey('stats', $data);
        self::assertGreaterThanOrEqual(1, $data['stats']['words']);
    }
}
