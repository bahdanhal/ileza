<?php

declare(strict_types=1);

namespace App\Content\Infrastructure;

use App\Content\Application\BlogArticleRepository;
use App\Content\Domain\BlogArticle;
use App\Entity\BlogArticleEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineBlogArticleRepository implements BlogArticleRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return list<BlogArticle> */
    public function all(string $locale): array
    {
        if (!in_array($locale, ['en', 'pl'], true)) {
            return [];
        }

        /** @var list<BlogArticleEntity> $entities */
        $entities = $this->entityManager->getRepository(BlogArticleEntity::class)->findBy(
            ['locale' => $locale],
            ['publishedAt' => 'DESC']
        );

        return array_map($this->map(...), $entities);
    }

    public function find(string $locale, string $slug): ?BlogArticle
    {
        /** @var BlogArticleEntity|null $entity */
        $entity = $this->entityManager->getRepository(BlogArticleEntity::class)->findOneBy([
            'locale' => $locale,
            'slug' => $slug,
        ]);

        return $entity === null ? null : $this->map($entity);
    }

    private function map(BlogArticleEntity $entity): BlogArticle
    {
        return new BlogArticle(
            $entity->getLocale(),
            $entity->getSlug(),
            $entity->getAlternateSlug(),
            $entity->getTitle(),
            $entity->getDescription(),
            $entity->getBodyMarkdown(),
            $entity->getPublishedAt(),
            $entity->getUpdatedAt(),
        );
    }
}
