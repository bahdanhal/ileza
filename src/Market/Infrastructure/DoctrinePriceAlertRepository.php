<?php

declare(strict_types=1);

namespace App\Market\Infrastructure;

use App\Entity\PriceAlertEntity;
use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceAlertRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePriceAlertRepository implements PriceAlertRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $secret,
    ) {
    }

    public function subscribe(
        string $productSlug,
        string $email,
        ?int $targetPriceGrosz,
        string $ipAddress,
        string $locale = 'pl',
    ): PriceAlert {
        $cleanEmail = strtolower(trim($email));
        $ipHash = substr(hash_hmac('sha256', $ipAddress, $this->secret), 0, 20);
        $cleanLocale = in_array(strtolower($locale), ['pl', 'en'], true) ? strtolower($locale) : 'pl';
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $repository = $this->entityManager->getRepository(PriceAlertEntity::class);
        /** @var PriceAlertEntity|null $existing */
        $existing = $repository->findOneBy([
            'productSlug' => $productSlug,
            'email' => $cleanEmail,
        ]);

        if ($existing !== null) {
            $existing->setTargetPriceGrosz($targetPriceGrosz);
            if (!$existing->isVerified() || $existing->getStatus() === PriceAlert::STATUS_UNSUBSCRIBED) {
                $verificationToken = bin2hex(random_bytes(32));
                $existing->setVerificationToken($verificationToken);
                $existing->setVerified(false);
                $existing->setVerifiedAt(null);
                $existing->setStatus(PriceAlert::STATUS_PENDING);
            }
            $this->entityManager->flush();

            return $this->toDomain($existing);
        }

        $verificationToken = bin2hex(random_bytes(32));
        $unsubscribeToken = bin2hex(random_bytes(32));

        $entity = new PriceAlertEntity(
            $productSlug,
            $cleanEmail,
            $targetPriceGrosz,
            $verificationToken,
            $unsubscribeToken,
            false,
            null,
            $ipHash,
            $cleanLocale,
            $now,
            null,
            null,
            PriceAlert::STATUS_PENDING,
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->toDomain($entity);
    }

    public function findByVerificationToken(string $token): ?PriceAlert
    {
        $repository = $this->entityManager->getRepository(PriceAlertEntity::class);
        /** @var PriceAlertEntity|null $entity */
        $entity = $repository->findOneBy(['verificationToken' => $token]);

        return $entity !== null ? $this->toDomain($entity) : null;
    }

    public function findByUnsubscribeToken(string $token): ?PriceAlert
    {
        $repository = $this->entityManager->getRepository(PriceAlertEntity::class);
        /** @var PriceAlertEntity|null $entity */
        $entity = $repository->findOneBy(['unsubscribeToken' => $token]);

        return $entity !== null ? $this->toDomain($entity) : null;
    }

    public function markVerified(string $verificationToken): ?PriceAlert
    {
        $repository = $this->entityManager->getRepository(PriceAlertEntity::class);
        /** @var PriceAlertEntity|null $entity */
        $entity = $repository->findOneBy(['verificationToken' => $verificationToken]);

        if ($entity === null) {
            return null;
        }

        $entity->setVerified(true);
        $entity->setStatus(PriceAlert::STATUS_ACTIVE);
        $entity->setVerifiedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $this->entityManager->flush();

        return $this->toDomain($entity);
    }

    public function markUnsubscribed(string $unsubscribeToken): ?PriceAlert
    {
        $repository = $this->entityManager->getRepository(PriceAlertEntity::class);
        /** @var PriceAlertEntity|null $entity */
        $entity = $repository->findOneBy(['unsubscribeToken' => $unsubscribeToken]);

        if ($entity === null) {
            return null;
        }

        $entity->setStatus(PriceAlert::STATUS_UNSUBSCRIBED);
        $this->entityManager->flush();

        return $this->toDomain($entity);
    }

    /**
     * @return list<PriceAlert>
     */
    public function findActiveForProduct(string $productSlug): array
    {
        $repository = $this->entityManager->getRepository(PriceAlertEntity::class);
        /** @var list<PriceAlertEntity> $entities */
        $entities = $repository->findBy([
            'productSlug' => $productSlug,
            'status' => PriceAlert::STATUS_ACTIVE,
            'isVerified' => true,
        ]);

        return array_map($this->toDomain(...), $entities);
    }

    /**
     * @return list<PriceAlert>
     */
    public function all(): array
    {
        $repository = $this->entityManager->getRepository(PriceAlertEntity::class);
        /** @var list<PriceAlertEntity> $entities */
        $entities = $repository->findBy([], ['createdAt' => 'DESC']);

        return array_map($this->toDomain(...), $entities);
    }

    public function updateNotificationState(int $alertId, \DateTimeImmutable $notifiedAt, int $notifiedPriceGrosz, bool $flush = true): void
    {
        $repository = $this->entityManager->getRepository(PriceAlertEntity::class);
        /** @var PriceAlertEntity|null $entity */
        $entity = $repository->find($alertId);

        if ($entity !== null) {
            $entity->setLastNotifiedAt($notifiedAt);
            $entity->setLastNotifiedPriceGrosz($notifiedPriceGrosz);
            if ($flush) {
                $this->entityManager->flush();
            }
        }
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @return array{total: int, active: int, pending: int, unsubscribed: int}
     */
    public function countStatistics(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        /** @var list<array{status: string, isVerified: bool, cnt: int|string}> $rows */
        $rows = $qb->select('a.status, a.isVerified, COUNT(a.id) AS cnt')
            ->from(PriceAlertEntity::class, 'a')
            ->groupBy('a.status, a.isVerified')
            ->getQuery()
            ->getArrayResult();

        $total = 0;
        $active = 0;
        $pending = 0;
        $unsubscribed = 0;

        foreach ($rows as $row) {
            $cnt = (int) $row['cnt'];
            $total += $cnt;
            $status = (string) $row['status'];
            $isVerified = (bool) $row['isVerified'];

            if ($status === PriceAlert::STATUS_ACTIVE && $isVerified) {
                $active += $cnt;
            } elseif ($status === PriceAlert::STATUS_UNSUBSCRIBED) {
                $unsubscribed += $cnt;
            } else {
                $pending += $cnt;
            }
        }

        return [
            'total' => $total,
            'active' => $active,
            'pending' => $pending,
            'unsubscribed' => $unsubscribed,
        ];
    }

    private function toDomain(PriceAlertEntity $entity): PriceAlert
    {
        return new PriceAlert(
            $entity->getId(),
            $entity->getProductSlug(),
            $entity->getEmail(),
            $entity->getTargetPriceGrosz(),
            $entity->getVerificationToken(),
            $entity->getUnsubscribeToken(),
            $entity->isVerified(),
            $entity->getVerifiedAt(),
            $entity->getIpHash(),
            $entity->getLocale(),
            $entity->getCreatedAt(),
            $entity->getLastNotifiedAt(),
            $entity->getLastNotifiedPriceGrosz(),
            $entity->getStatus(),
        );
    }
}
