<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Entity\BlogArticleEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BlogRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return list<BlogArticleEntity> */
    public function all(string $locale): array
    {
        if (!in_array($locale, ['en', 'pl'], true)) {
            return [];
        }

        /** @var list<BlogArticleEntity> */
        return $this->entityManager->getRepository(BlogArticleEntity::class)->findBy(
            ['locale' => $locale],
            ['publishedAt' => 'DESC']
        );
    }

    public function find(string $locale, string $slug): ?BlogArticleEntity
    {
        /** @var BlogArticleEntity|null */
        return $this->entityManager->getRepository(BlogArticleEntity::class)->findOneBy([
            'locale' => $locale,
            'slug' => $slug,
        ]);
    }
}
