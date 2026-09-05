<?php

declare(strict_types=1);

namespace App\Market\Presentation\Http\Admin;

use App\Content\Application\BlogArticleRepository;
use App\Content\Application\MarkdownRenderer;
use App\Entity\BlogArticleEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminBlogController extends AbstractController
{
    private const AUTH_COOKIE_NAME = 'market_admin_auth';

    public function __construct(
        private readonly BlogArticleRepository $blogArticles,
        private readonly MarkdownRenderer $markdown,
        private readonly string $secret,
    ) {
    }

    #[Route('/admin/blog', name: 'market_admin_blog_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToRoute('market_admin_dashboard_root');
        }

        $locale = $request->query->get('locale');
        $locale = is_string($locale) && in_array($locale, ['en', 'pl'], true) ? $locale : null;
        $articles = $this->blogArticles->findAllForAdmin($locale);

        return $this->render('admin/blog/index.html.twig', [
            'articles' => $articles,
            'current_locale' => $locale,
        ]);
    }

    #[Route('/admin/blog/new', name: 'market_admin_blog_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToRoute('market_admin_dashboard_root');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('admin_blog_article', $csrfToken)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('market_admin_blog_new');
            }

            $locale = (string) $request->request->get('locale', 'pl');
            $slug = strtolower(trim((string) $request->request->get('slug', '')));
            $alternateSlug = strtolower(trim((string) $request->request->get('alternate_slug', '')));
            $title = trim((string) $request->request->get('title', ''));
            $description = trim((string) $request->request->get('description', ''));
            $bodyMarkdown = trim((string) $request->request->get('body_markdown', ''));

            // Clean smart punctuation
            $title = $this->cleanSymbols($title);
            $description = $this->cleanSymbols($description);
            $bodyMarkdown = $this->cleanSymbols($bodyMarkdown);

            if ($slug === '' || $title === '' || $bodyMarkdown === '') {
                $this->addFlash('error', 'Slug, Title, and Markdown Content are required.');
                return $this->render('admin/blog/editor.html.twig', [
                    'article' => null,
                    'form_data' => $request->request->all(),
                ]);
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $publishedAt = $now;
            $publishedAtInput = (string) $request->request->get('published_at', '');
            if ($publishedAtInput !== '') {
                try {
                    $publishedAt = new \DateTimeImmutable($publishedAtInput, new \DateTimeZone('UTC'));
                } catch (\Exception) {
                    // Fall back to now
                }
            }

            try {
                $article = new BlogArticleEntity(
                    $locale,
                    $slug,
                    $alternateSlug,
                    $title,
                    $description,
                    $bodyMarkdown,
                    $publishedAt,
                    $now
                );

                $this->blogArticles->save($article);
                $this->addFlash('success', 'Buying guide created successfully.');

                return $this->redirectToRoute('market_admin_blog_list', ['locale' => $locale]);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Failed to create article: ' . $e->getMessage());
                return $this->render('admin/blog/editor.html.twig', [
                    'article' => null,
                    'form_data' => $request->request->all(),
                ]);
            }
        }

        return $this->render('admin/blog/editor.html.twig', [
            'article' => null,
            'form_data' => [
                'locale' => 'pl',
            ],
        ]);
    }

    #[Route('/admin/blog/{id}/edit', name: 'market_admin_blog_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToRoute('market_admin_dashboard_root');
        }

        $entity = $this->blogArticles->findEntity($id);
        if ($entity === null) {
            $this->addFlash('error', 'Article not found.');
            return $this->redirectToRoute('market_admin_blog_list');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('admin_blog_article', $csrfToken)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('market_admin_blog_edit', ['id' => $id]);
            }

            $locale = (string) $request->request->get('locale', 'pl');
            $slug = strtolower(trim((string) $request->request->get('slug', '')));
            $alternateSlug = strtolower(trim((string) $request->request->get('alternate_slug', '')));
            $title = trim((string) $request->request->get('title', ''));
            $description = trim((string) $request->request->get('description', ''));
            $bodyMarkdown = trim((string) $request->request->get('body_markdown', ''));

            // Clean smart punctuation
            $title = $this->cleanSymbols($title);
            $description = $this->cleanSymbols($description);
            $bodyMarkdown = $this->cleanSymbols($bodyMarkdown);

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            try {
                $entity->setLocale($locale);
                $entity->setSlug($slug);
                $entity->setAlternateSlug($alternateSlug);
                $entity->setTitle($title);
                $entity->setDescription($description);
                $entity->setBodyMarkdown($bodyMarkdown);
                $entity->setUpdatedAt($now);

                $this->blogArticles->save($entity);
                $this->addFlash('success', 'Buying guide updated successfully.');

                return $this->redirectToRoute('market_admin_blog_list', ['locale' => $locale]);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Failed to update article: ' . $e->getMessage());
            }
        }

        return $this->render('admin/blog/editor.html.twig', [
            'article' => $entity,
            'form_data' => [
                'id' => $entity->getId(),
                'locale' => $entity->getLocale(),
                'slug' => $entity->getSlug(),
                'alternate_slug' => $entity->getAlternateSlug(),
                'title' => $entity->getTitle(),
                'description' => $entity->getDescription(),
                'body_markdown' => $entity->getBodyMarkdown(),
                'published_at' => $entity->getPublishedAt()->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    #[Route('/admin/blog/{id}/delete', name: 'market_admin_blog_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToRoute('market_admin_dashboard_root');
        }

        $csrfToken = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_blog_article_' . $id, $csrfToken)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('market_admin_blog_list');
        }

        $entity = $this->blogArticles->findEntity($id);
        if ($entity !== null) {
            $locale = $entity->getLocale();
            $this->blogArticles->delete($entity);
            $this->addFlash('success', 'Article deleted.');
            return $this->redirectToRoute('market_admin_blog_list', ['locale' => $locale]);
        }

        return $this->redirectToRoute('market_admin_blog_list');
    }

    #[Route('/admin/blog/preview', name: 'market_admin_blog_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        if (!$this->isAuthenticated($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $markdownText = (string) $request->request->get('content', '');
        $markdownText = $this->cleanSymbols($markdownText);
        $renderedHtml = $this->markdown->render($markdownText);

        $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($renderedHtml)));
        $sentences = array_values(array_filter(preg_split('/[.!?]+/u', $plain) ?: []));
        $words = array_values(array_filter(preg_split('/\s+/u', $plain) ?: []));
        $numSentences = max(1, count($sentences));
        $numWords = max(1, count($words));

        $syllables = 0;
        foreach ($words as $word) {
            $cleanWord = strtolower(trim((string) preg_replace('/[^a-z]/i', '', $word)));
            if (strlen($cleanWord) <= 3) {
                $syllables += 1;
            } else {
                preg_match_all('/[aeiouy]{1,2}/', $cleanWord, $m);
                $syllables += max(1, count($m[0]));
            }
        }

        $wordsPerSentence = $numWords / $numSentences;
        $syllablesPerWord = $syllables / $numWords;
        $flesch = 206.835 - (1.015 * $wordsPerSentence) - (84.6 * $syllablesPerWord);
        $grade = (0.39 * $wordsPerSentence) + (11.8 * $syllablesPerWord) - 15.59;

        return new JsonResponse([
            'html' => $renderedHtml,
            'stats' => [
                'words' => $numWords,
                'sentences' => $numSentences,
                'flesch_reading_ease' => round($flesch, 1),
                'grade_level' => round($grade, 1),
                'words_per_sentence' => round($wordsPerSentence, 1),
            ],
        ]);
    }

    private function cleanSymbols(string $text): string
    {
        $replacements = [
            "\u{2014}" => '-',
            "\u{2013}" => '-',
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{00A0}" => ' ',
        ];

        return strtr($text, $replacements);
    }

    private function isHeaderAuthenticated(Request $request): bool
    {
        $token = $request->headers->get('X-Admin-Token');
        if ($token === null || $token === '') {
            $authHeader = (string) $request->headers->get('Authorization', '');
            if (preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        $cleanToken = trim($token);
        $adminToken = trim((string) ($_ENV['ADMIN_TOKEN'] ?? ($_ENV['MARKET_ADMIN_TOKEN'] ?? $this->secret)));
        $marketAdminToken = trim((string) ($_ENV['MARKET_ADMIN_TOKEN'] ?? ''));
        $secret = trim($this->secret);

        return ($adminToken !== '' && hash_equals($adminToken, $cleanToken))
            || ($secret !== '' && hash_equals($secret, $cleanToken))
            || ($marketAdminToken !== '' && hash_equals($marketAdminToken, $cleanToken));
    }

    private function isAuthenticated(Request $request): bool
    {
        if ($this->isHeaderAuthenticated($request)) {
            return true;
        }

        $cookie = (string) $request->cookies->get(self::AUTH_COOKIE_NAME, '');
        if ($cookie !== '' && trim($this->secret) !== '') {
            $expected = hash_hmac('sha256', 'market_admin_authenticated', $this->secret);
            return hash_equals($expected, $cookie);
        }

        return false;
    }

    protected function isCsrfTokenValid(string $id, #[\SensitiveParameter] ?string $token): bool
    {
        if ($token === null || $token === '' || trim($this->secret) === '') {
            return false;
        }

        $expected = hash_hmac('sha256', 'csrf:' . $id, $this->secret);

        return hash_equals($expected, $token);
    }
}
