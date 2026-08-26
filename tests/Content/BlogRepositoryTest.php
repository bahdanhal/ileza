<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Content\Application\BlogRepository;
use App\Entity\BlogArticleEntity;
use App\Tests\DoctrineTestCase;

final class BlogRepositoryTest extends DoctrineTestCase
{
    public function testFindsLocalizedArticlesInReversePublicationOrder(): void
    {
        $older = $this->article('older-guide', 'pl', '2026-08-20');
        $newer = $this->article('newer-guide', 'pl', '2026-08-26');
        $english = $this->article('english-guide', 'en', '2026-08-25');
        $this->entityManager->persist($older);
        $this->entityManager->persist($newer);
        $this->entityManager->persist($english);
        $this->entityManager->flush();

        $repository = new BlogRepository($this->entityManager);

        self::assertSame([$newer, $older], $repository->all('pl'));
        self::assertSame($english, $repository->find('en', 'english-guide'));
        self::assertNull($repository->find('pl', 'english-guide'));
    }

    private function article(string $slug, string $locale, string $date): BlogArticleEntity
    {
        return new BlogArticleEntity(
            $locale,
            $slug,
            'alternate-' . $slug,
            'Guide title',
            'Guide description',
            'Guide body',
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($date),
        );
    }
}
