<?php

declare(strict_types=1);

namespace DoctrineMigrations;

// phpcs:disable Generic.Files.LineLength.TooLong

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update blog article copies to plain ASCII punctuation and high readability';
    }

    public function up(Schema $schema): void
    {
        $articles = [
            [
                'locale' => 'pl',
                'slug' => 'ram-w-macbooku-2026',
                'description' => 'Praktyczny wybór pamięci zunifikowanej do pracy biurowej, programowania i zadań kreatywnych - z odniesieniem do aktualnych cen używanych MacBooków.',
                'body_markdown' => <<<'MARKDOWN'
## Najkrótsza odpowiedź

W 2026 roku **16 GB to najbezpieczniejszy wybór dla większości kupujących**. Konfiguracja 8 GB nadal wystarcza do przeglądarki, dokumentów i lekkiej pracy, ale szybciej zaczyna korzystać z pamięci wymiany. Wariant 24 GB ma sens przy maszynach wirtualnych, dużych projektach programistycznych albo obróbce zdjęć i wideo.

## Kiedy wystarczy 8 GB

- Korzystasz głównie z pakietu biurowego, komunikatorów i kilku kart przeglądarki.
- Nie planujesz lokalnych modeli AI, Dockera ani montażu materiału 4K.
- Cena jest ważniejsza niż kilkuletni zapas wydajności.

Pamięci zunifikowanej nie można rozbudować po zakupie, dlatego porównuj dokładną konfigurację RAM i SSD.

{{ price("macbook-air-13-m2-16-gb-ram-512-gb-ssd") }}

## Dlaczego 16 GB jest rozsądnym środkiem

Ta konfiguracja wystarcza do IDE, kilku kontenerów i okazjonalnej obróbki multimediów. Zostawia też margines na kolejne wersje macOS. Sprawdź stan baterii, liczbę cykli i brak blokady MDM.

## Kto wykorzysta 24 GB

Wybierz 24 GB, jeżeli Twoje mierzone obciążenia przekraczają 16 GB. Sam zapas pamięci nie przyspieszy lekkiej pracy. Dla długich renderów warto porównać również MacBooka Pro z aktywnym chłodzeniem.
MARKDOWN,
            ],
            [
                'locale' => 'en',
                'slug' => 'macbook-memory-guide-2026',
                'description' => 'A practical unified-memory choice for office work, software development, and creative workloads, grounded in current Polish used prices.',
                'body_markdown' => <<<'MARKDOWN'
## The short answer

In 2026, **16 GB is the safest choice for most buyers**. An 8 GB machine remains useful for documents, browsing, and light work, but reaches swap sooner. A 24 GB configuration makes sense for virtual machines, larger development projects, or photo and video work.

## When 8 GB is enough

- Your normal workload is office software, messaging, and a modest number of browser tabs.
- You do not plan to run local AI models, Docker stacks, or edit 4K footage.
- Purchase price matters more than several years of performance headroom.

Unified memory cannot be upgraded, so compare the exact memory and SSD configuration.

{{ price("macbook-air-13-m2-16-gb-ram-512-gb-ssd") }}

## Why 16 GB is balanced

This tier handles an IDE, several containers, project work, and occasional media editing. It leaves more room for future macOS releases. Verify battery health, cycle count, and the absence of MDM enrollment.

## Who benefits from 24 GB

Choose 24 GB when measured workloads already exceed 16 GB. Spare memory alone will not accelerate light tasks. For sustained rendering, also compare an actively cooled MacBook Pro.
MARKDOWN,
            ],
            [
                'locale' => 'pl',
                'slug' => 'checklista-uzywany-iphone',
                'description' => "Co sprawdzić przed zapłatą za używanego iPhone'a, aby uniknąć blokady aktywacji, nieoryginalnych części i kosztownej naprawy.",
                'body_markdown' => <<<'MARKDOWN'
## Zanim spotkasz się ze sprzedawcą

Poproś o numer modelu, pojemność, kondycję baterii i historię napraw. Cenę porównuj z dokładnie tym samym wariantem pamięci.

{{ price("iphone-13-128gb") }}

## Blokada aktywacji i zarządzanie firmowe

Sprzedający powinien wylogować Apple ID, wyłączyć funkcję Znajdź iPhone i wymazać urządzenie. Przejdź konfigurację do ekranu głównego. Komunikat o zdalnym zarządzaniu może oznaczać aktywny profil MDM - nie kupuj telefonu, dopóki organizacja go nie usunie.

## Bateria, ekran i części

- Sprawdź kondycję baterii oraz komunikaty serwisowe.
- Wyświetl białe i czarne tło, aby znaleźć przebarwienia i martwe piksele.
- Przetestuj Face ID lub Touch ID, aparaty, mikrofony, ładowanie i przyciski.
- Sprawdź historię części i serwisu.

## Bezpieczna finalizacja

Porównaj IMEI, przeprowadź pełny reset i zapisz numer urządzenia w umowie. Jeżeli sprzedający odmawia testów, zrezygnuj.
MARKDOWN,
            ],
            [
                'locale' => 'en',
                'slug' => 'used-iphone-buying-checklist',
                'description' => 'What to verify before paying for a used iPhone so an activation lock, unknown parts, or a costly repair does not become your problem.',
                'body_markdown' => <<<'MARKDOWN'
## Before meeting the seller

Ask for the model number, storage, battery health, and repair history. Compare the price with the same storage variant rather than the generation alone.

{{ price("iphone-13-128gb") }}

## Activation Lock and company management

The seller should sign out of Apple ID, disable Find My, and erase the device. Complete setup far enough to reach the home screen. A remote-management prompt can indicate active MDM enrollment; do not buy until the organization removes it.

## Battery, display, and parts

- Review battery health and service notices.
- Show solid white and black screens to reveal tint or dead pixels.
- Test Face ID or Touch ID, cameras, microphones, charging, and every button.
- Check Parts and Service History.

## Complete the sale safely

Compare the IMEI, complete a full reset, and record the device number in the sale agreement. Walk away when a seller refuses reasonable tests.
MARKDOWN,
            ],
            [
                'locale' => 'pl',
                'slug' => 'ddr4-czy-ddr5-starszy-pc',
                'description' => 'Kiedy warto rozbudować platformę DDR4, a kiedy dopłacić do nowej płyty, procesora i pamięci DDR5.',
                'body_markdown' => <<<'MARKDOWN'
## Najpierw sprawdź kompatybilność

DDR4 i DDR5 nie są zamienne. Mają inne wycięcie modułu i wymagają zgodnej płyty oraz kontrolera pamięci. Jeżeli komputer obsługuje wyłącznie DDR4, przejście na DDR5 oznacza zmianę platformy.

{{ price("ram-ddr4-desktop-16-gb-3200-mhz") }}

## Kiedy rozbudowa DDR4 ma sens

- Procesor nadal spełnia wymagania, a ograniczeniem jest pamięć.
- Potrzebujesz tanio dojść do 32 GB.
- Płyta ma wolne sloty i wspiera docelową pojemność.

Najbezpieczniej stosować zgodny zestaw modułów o tej samej pojemności, taktowaniu i opóźnieniach.

## Kiedy rozważyć DDR5

Nowa platforma jest uzasadniona, gdy i tak wymieniasz procesor oraz płytę albo aplikacje korzystają z większej przepustowości. Do kosztu pamięci dolicz płytę, CPU i chłodzenie. Dla starszego, nadal wystarczającego PC rozbudowa DDR4 zwykle daje lepszy efekt za złotówkę.
MARKDOWN,
            ],
            [
                'locale' => 'en',
                'slug' => 'ddr4-vs-ddr5-older-pc',
                'description' => 'When to expand a DDR4 system and when a new motherboard, processor, and DDR5 memory are worth the combined cost.',
                'body_markdown' => <<<'MARKDOWN'
## Check compatibility first

DDR4 and DDR5 are not interchangeable. Their modules are keyed differently and require a compatible motherboard and CPU memory controller. If the computer accepts only DDR4, moving to DDR5 means replacing the platform.

{{ price("ram-ddr4-desktop-16-gb-3200-mhz") }}

## When a DDR4 upgrade makes sense

- The processor remains adequate while memory is the bottleneck.
- You need an affordable path to 32 GB.
- The board has free slots and supports the target capacity.

A matched kit with the same capacity, speed, and timings is the safest option.

## When to consider DDR5

A new platform is justified when the processor and motherboard already need replacement or measured workloads benefit from higher bandwidth. Include the motherboard, CPU, and cooling in the comparison. For an older but adequate computer, expanding DDR4 usually delivers more value per zloty.
MARKDOWN,
            ],
        ];

        foreach ($articles as $article) {
            $this->addSql(
                'UPDATE blog_articles SET description = :description, body_markdown = :body_markdown, updated_at = NOW() WHERE locale = :locale AND slug = :slug',
                $article
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
