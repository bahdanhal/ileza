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

    /** @return list<BlogArticle> */
    public function findAllForAdmin(?string $locale = null): array
    {
        $criteria = [];
        if ($locale !== null && in_array($locale, ['en', 'pl'], true)) {
            $criteria['locale'] = $locale;
        }

        /** @var list<BlogArticleEntity> $entities */
        $entities = $this->entityManager->getRepository(BlogArticleEntity::class)->findBy(
            $criteria,
            ['publishedAt' => 'DESC', 'id' => 'DESC']
        );

        return array_map($this->map(...), $entities);
    }

    public function findEntity(int $id): ?BlogArticleEntity
    {
        return $this->entityManager->find(BlogArticleEntity::class, $id);
    }

    public function findEntityByLocaleAndSlug(string $locale, string $slug): ?BlogArticleEntity
    {
        /** @var BlogArticleEntity|null $entity */
        $entity = $this->entityManager->getRepository(BlogArticleEntity::class)->findOneBy([
            'locale' => $locale,
            'slug' => $slug,
        ]);

        return $entity;
    }

    public function save(BlogArticleEntity $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function delete(BlogArticleEntity $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
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
            $entity->getId(),
        );
    }
}
