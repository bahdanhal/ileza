<?php

declare(strict_types=1);

namespace App\Content\Presentation\Http;

use App\Content\Application\BlogArticleRepository;
use App\Content\Application\MarkdownRenderer;
use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    public function __construct(
        private readonly BlogArticleRepository $articles,
        private readonly MarkdownRenderer $markdown,
        private readonly ProductCatalog $catalog,
        private readonly GetProductPriceHistory $priceHistory,
    ) {
    }

    #[Route(path: ['pl' => '/blog/', 'en' => '/en/blog/'], name: 'blog_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('blog/index.html.twig', [
            'articles' => $this->articles->all($request->getLocale()),
        ]);
    }

    #[Route(
        path: ['pl' => '/blog/{slug}', 'en' => '/en/blog/{slug}'],
        name: 'blog_article',
        requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'],
        methods: ['GET']
    )]
    public function article(string $slug, Request $request): Response
    {
        $article = $this->articles->find($request->getLocale(), $slug);
        if ($article === null) {
            throw $this->createNotFoundException();
        }

        $this->priceHistory->preload($article->getPriceSlugs());
        $blocks = [];
        $parts = preg_split(
            '/\{\{\s*price\(["\']([a-z0-9-]+)["\']\)\s*\}\}/u',
            $article->getBodyMarkdown(),
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        ) ?: [];
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                if (trim($part) !== '') {
                    $blocks[] = ['type' => 'markdown', 'html' => $this->markdown->render($part)];
                }
                continue;
            }
            $product = $this->catalog->get($part);
            if ($product !== null) {
                $blocks[] = [
                    'type' => 'price',
                    'product' => $product,
                    'latest' => $this->priceHistory->latestForProduct($part),
                ];
            }
        }

        return $this->render('blog/article.html.twig', [
            'article' => $article,
            'blocks' => $blocks,
        ]);
    }
}
