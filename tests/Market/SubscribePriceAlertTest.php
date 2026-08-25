<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\PriceAlertMailerInterface;
use App\Market\Application\ProductCatalog;
use App\Market\Application\SubscribePriceAlert;
use App\Market\Application\UnsubscribePriceAlert;
use App\Market\Application\VerifyPriceAlert;
use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceAlertRepository;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use App\Market\Infrastructure\DoctrinePriceAlertRepository;
use App\Tests\DoctrineTestCase;

final class SubscribePriceAlertTest extends DoctrineTestCase
{
    private ProductCatalog $catalog;
    private PriceAlertRepository $repository;
    private MockPriceAlertMailer $mailer;
    private SubscribePriceAlert $subscribe;
    private VerifyPriceAlert $verify;
    private UnsubscribePriceAlert $unsubscribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new ProductCatalog();
        $this->repository = new DoctrinePriceAlertRepository($this->entityManager, 'secret-key');
        $this->mailer = new MockPriceAlertMailer();
        $this->subscribe = new SubscribePriceAlert($this->catalog, $this->repository, $this->mailer);
        $this->verify = new VerifyPriceAlert($this->repository);
        $this->unsubscribe = new UnsubscribePriceAlert($this->repository);
    }

    public function testSubscribeSendsVerificationEmailAndActivatesOnVerify(): void
    {
        $alert = $this->subscribe->execute(
            productSlug: 'iphone-14-128gb',
            email: 'alerts@example.com',
            targetPriceGrosz: 240000,
            ipAddress: '127.0.0.1',
            locale: 'pl',
        );

        $this->assertCount(1, $this->mailer->verificationEmails);
        $this->assertSame('alerts@example.com', $this->mailer->verificationEmails[0]['alert']->email);
        $this->assertFalse($alert->isVerified);

        // Verify token
        $verified = $this->verify->execute($alert->verificationToken);
        $this->assertNotNull($verified);
        $this->assertTrue($verified->isActive());

        // Unsubscribe
        $unsubscribed = $this->unsubscribe->execute($alert->unsubscribeToken);
        $this->assertNotNull($unsubscribed);
        $this->assertSame(PriceAlert::STATUS_UNSUBSCRIBED, $unsubscribed->status);
    }

    public function testSubscribeThrowsExceptionForUnknownProductOrInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->subscribe->execute(
            productSlug: 'non-existent-product',
            email: 'valid@example.com',
            targetPriceGrosz: null,
            ipAddress: '127.0.0.1',
        );
    }

    public function testSubscribeThrowsExceptionForInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->subscribe->execute(
            productSlug: 'iphone-14-128gb',
            email: 'invalid-email-address',
            targetPriceGrosz: null,
            ipAddress: '127.0.0.1',
        );
    }
}
