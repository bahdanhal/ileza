<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the smartphone economics analysis articles in Polish and English';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->articles() as $article) {
            $this->addSql(
                'INSERT INTO blog_articles ('
                . 'locale, slug, alternate_slug, title, description, body_markdown, published_at, updated_at) '
                . 'VALUES ('
                . ':locale, :slug, :alternate_slug, :title, :description, :body_markdown, :published_at, :updated_at) '
                . 'ON CONFLICT (locale, slug) DO UPDATE SET '
                . 'alternate_slug = EXCLUDED.alternate_slug, '
                . 'title = EXCLUDED.title, '
                . 'description = EXCLUDED.description, '
                . 'body_markdown = EXCLUDED.body_markdown, '
                . 'updated_at = EXCLUDED.updated_at',
                $article
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM blog_articles WHERE slug IN (:pl_slug, :en_slug)',
            [
                'pl_slug' => 'ekonomia-smartfonow-uzywany-czy-nowy',
                'en_slug' => 'smartphone-economics-used-vs-new',
            ]
        );
    }

    // phpcs:disable Generic.Files.LineLength.TooLong -- Seed copy remains readable in its editorial form.
    /** @return list<array<string, string>> */
    private function articles(): array
    {
        $publishedAt = '2026-09-05T12:45:00+02:00';

        return [
            [
                'locale' => 'pl',
                'slug' => 'ekonomia-smartfonow-uzywany-czy-nowy',
                'alternate_slug' => 'smartphone-economics-used-vs-new',
                'title' => 'Ekonomia smartfonów: używane czy nowe. Twarde liczby, podatki i pułapki rynkowe',
                'description' => 'Krzywa utraty wartości kontra koszty nowości. Rzeczywisty rachunek TCO na 2 lata, podatki w JDG i spółce z o.o., pułapka faktury VAT-marża oraz analiza opłacalności od fabrycznie nowych po 6-letnie telefony.',
                'body_markdown' => <<<'MARKDOWN'
## 1. Krzywa utraty wartości: kto płaci za zerwanie plomby

Największym kosztem nowego smartfona nie jest ładowarka, etui ani abonament. Jest nim natychmiastowa utrata wartości w chwili rozpieczętowania pudełka.

W elektronice użytkowej zjawisko to ma charakter gwałtowny:
- **Pierwsze 24 godziny:** Sam fakt zerwania fabrycznej folii i aktywacji w systemie obniża wartość urządzenia o 15% do 20%. Telefon w stanie idealnym, ale otwarty, natychmiast staje się sprzętem z rynku wtórnego.
- **Pierwszy rok:** iPhone traci średnio 20% do 25% pierwotnej ceny. Flagowce z systemem Android tracą w tym samym czasie od 40% do nawet 55% wartości katalogowej.
- **Drugi i trzeci rok:** Tempo spadku wyraźnie zwalnia. Dwuletni iPhone kosztuje zazwyczaj 50% do 55% ceny startowej, a trzyletni stabilizuje się w granicach 30% do 40%.
- **Czwarty rok i dalej:** Krzywa osiąga stabilny pułap użyteczności (market floor). Spadek wynosi zaledwie kilka procent rocznie, a cena zależy głównie od stanu obudowy i kondycji baterii.

> **Zasada IleZa:** Pierwszy właściciel nowego flagowca płaci najwyższy podatek od nowości na rynku elektroniki. Kupując urządzenie 2- lub 3-letnie, wchodzisz w posiadanie sprzętu o niemal identycznej kulturze pracy, którego najstromszy spadek cenowy sfinansował ktoś inny.

{{ price("iphone-15-128gb") }}

---

## 2. Rachunek TCO na 2 lata: Nowy model bazowy vs 2-latek z własnej kieszeni

Gdy kupujesz telefon za prywatne, opodatkowane oszczędności, liczy się całkowity koszt posiadania (Total Cost of Ownership, TCO).

Dla zachowania precyzji porównujemy tę samą linię produktową: bazowy model flagowy o pojemności 128 GB (segment iPhone 13-16 / Samsung Galaxy S):
- **Wariant A:** Zakup fabrycznie nowego bazowego flagowca w salonie za 4 200 zł brutto (cena premierowa).
- **Wariant B:** Zakup zadbanego 2-letniego flagowca bazowego za 1 400 zł brutto i wymiana baterii.

Oto bilans wydatków po 24 miesiącach:

- **Wariant A: Nowy bazowy flagowiec (0-2 lata)**
  - Cena zakupu: 4 200 zł
  - Wartość po 2 latach (odsprzedaż): ~2 000 zł
  - Utrata wartości (deprecjacja): 2 200 zł
  - Akcesoria (ładowarka 30W, etui, szkło): 200 zł
  - Ubezpieczenie ekranu lub AppleCare+: 400 zł
  - Serwis i naprawy gwarancyjne: 0 zł
  - **Łączny realny koszt za 2 lata: ~2 800 zł (~117 zł miesięcznie)**

- **Wariant B: 2-letni flagowiec bazowy (2-4 lata)**
  - Cena zakupu: 1 400 zł
  - Wartość po 2 latach (odsprzedaż jako 4-latek): ~800 zł
  - Utrata wartości (deprecjacja): 600 zł
  - Wymiana baterii w zaufanym serwisie: 400 zł
  - Akcesoria (etui, szkło): 100 zł
  - Bufor na drobne naprawy: 100 zł
  - **Łączny realny koszt za 2 lata: ~1 200 zł (~50 zł miesięcznie)**

Różnica wynosi aż **1 600 zł na korzyść telefonu używanego**. To ponad **66 zł czystej oszczędności w każdym miesiącu**.

Do tego dochodzi koszt alternatywny kapitału. Różnica 2 800 zł w gotówce na starcie, ulokowana na rachunku oszczędnościowym na 6% w skali roku, przynosi po odliczeniu podatku Belki kolejne ~280 zł zysku w ciągu dwóch lat.

{{ price("iphone-13-128gb") }}

---

## 3. Podatki dla firm: kiedy nowy telefon z salonu ma sens biznesowy

Wielu przedsiębiorców powtarza slogan: "wezmę na firmę, wrzucę w koszty i telefon będzie za darmo". W praktyce podatki łagodzą wydatek, ale go nie kasują.

Smartfon kosztuje poniżej ustawowego progu 10 000 zł netto, dzięki czemu nie wymaga wieloletniej amortyzacji i trafia bezpośrednio w koszty uzyskania przychodów (KUP) w miesiącu zakupu.

### JDG na podatku liniowym (19%) lub skali podatkowej (32%) + czynny VAT
Dla przedsiębiorcy na podatku liniowym z pełnym prawem do odliczenia VAT, zakup nowego telefonu za 4 200 zł brutto wygląda następująco:
- Cena brutto: 4 200 zł (netto: 3 415 zł, VAT 23%: 785 zł).
- Odliczenie 100% VAT (telefon służbowy): odzyskujesz **785 zł**.
- Do kosztów uzyskania przychodów trafia kwota netto: 3 415 zł.
- Tarcza dochodowa (19% PIT + 4,9% składka zdrowotna = 23,9% z 3 415 zł): zysk **816 zł**.
- Łączna tarcza podatkowa: `785 zł + 816 zł` = **1 601 zł** (ok. 38% ceny brutto).
- **Realny koszt zakupu nowego telefonu z salonu: ok. 2 599 zł** (`3 415 zł netto - 816 zł tarczy dochodowej`).

### JDG na Ryczałcie od przychodów ewidencjonowanych
Na ryczałcie sytuacja jest diametralnie inna:
- Koszty uzyskania przychodów nie istnieją (KUP = 0 zł). Nie odliczysz ani złotówki od podatku dochodowego.
- Czynny podatnik VAT odzyska jedynie VAT (785 zł), więc telefon kosztuje go 3 415 zł.
- Przedsiębiorca na ryczałcie zwolniony z VAT płaci pełne 4 200 zł z własnej kieszeni.

### Spółka z o.o. (Klasyczny CIT vs Estoński CIT)
- **Klasyczny CIT (9% mały podatnik / 19% stawka standardowa):** Spółka odlicza 100% VAT, a kwota netto trafia w koszty podatkowe. Tarcza CIT wynosi 9% (307 zł) lub 19% (649 zł), co daje realny koszt zakupu na poziomie odpowiednio 3 108 zł lub 2 766 zł.
- **Estoński CIT:** Należy uważać na telefony i abonamenty kupowane dla wspólników lub członków zarządu. Jeżeli urządzenie nie ma udokumentowanego, wyłącznego związku z pracą operacyjną spółki, urząd skarbowy może zakwalifikować wydatek jako ukryty zysk lub wydatek niezwiązany z działalnością gospodarczą (opodatkowany stawką 10% lub 20% ryczałtu).

### Pułapka faktury VAT-marża na rynku wtórnym
Przedsiębiorcy często zapominają o specyfice faktury VAT-marża, powszechnie wystawianej przez komisy i sprzedawców urządzeń używanych:
- Faktura VAT-marża **nie zawiera podatku VAT do odliczenia**. Kwota VAT w deklaracji JPK_V7 wynosi 0 zł.
- Kupując używany telefon za 1 400 zł na fakturę VAT-marża, przedsiębiorca na podatku liniowym zalicza do KUP całą kwotę 1 400 zł, zyskując tarczę PIT i zdrowotną (23,9%): **335 zł**.
- **Realny koszt używanego telefonu z komisu: ok. 1 065 zł** (`1 400 zł - 335 zł`).

> **Zasada IleZa:** Tarcza podatkowa nie jest darmowym rabatem od państwa. Przy fakturze VAT-marża tracisz odliczenie 23% VAT, ale ze względu na niską bazę zakupu, używany flagowiec (1 065 zł) nadal kosztuje firmę o ponad 1 500 zł mniej niż nowy model z salonu (2 599 zł).

---

## 4. Segmentacja rynku: od fabrycznej nowości po 6 lat

Rynek smartfonów dzieli się na cztery wyraźne strefy ekonomiczne:

### Segment 0-1 rok: Maksymalny prestiż i najwyższy koszt (3 500 - 6 500 zł)
- Najnowocześniejsze procesory, zaawansowane matryce aparatów i fabrycznie świeża bateria o kondycji 100%.
- Pełna 2-letnia ochrona konsumencka i gwarancja producenta.
- Najwyższy koszt amortyzacji w przeliczeniu na każdy dzień posiadania.

### Segment 2-3 lata: Złoty środek opłacalności (1 000 - 1 800 zł)
- Typowe modele: iPhone 13, iPhone 14, Samsung Galaxy S22/S23.
- Obudowy z aluminium i szkła, pełna wodoszczelność IP68, ekrany OLED i aparaty z optyczną stabilizacją obrazu (OIS).
- Gwarancja kolejnych 3 do 4 lat pełnych aktualizacji bezpieczeństwa i systemu.
- Inwestycja około 400 zł w nową, oryginalną baterię przywraca parametry fabryczne na kolejne kilkadziesiąt miesięcy.

### Segment 4-5 lat: Pragmatyzm budżetowy (400 - 800 zł)
- Typowe modele: iPhone 11, iPhone 12.
- Sprzęt w pełni wystarczający do komunikatorów, nawigacji, aplikacji bankowych i profilu zaufanego mObywatel.
- Znikoma roczna utrata wartości rzędu 50 do 100 zł rocznie.
- Idealny wybór na telefon firmowy dla pracownika, urządzenie dla dziecka lub zapasowy telefon podróżny.

{{ price("iphone-11-128gb") }}

### Segment 6 lat i starsze: Ryzyko bezpieczeństwa i utraty wsparcia
- W tym segmencie kończy się oficjalne wsparcie dla systemu operacyjnego i silnika przeglądarki WebKit/Chromium.
- Aplikacje bankowe i płatności zbliżeniowe stopniowo odcinają starsze wersje systemów z powodów bezpieczeństwa.
- Koszt nowej baterii często przekracza wartość rynkową całego telefonu.

---

## 5. Nowy budżetowiec z marketu vs 2-letni flagowiec

Wielu konsumentów stoi przed dylematem: kupić nowy telefon ze średniej półki za 1 200 - 1 500 zł czy 2-letniego flagowca w tej samej kwocie?

Z perspektywy inżynierskiej i ekonomicznej używany flagowiec deklasuje sklepowego budżetowca w niemal każdym kluczowym punkcie:
- **Materiały i wytrzymałość:** Budżetowiec to tworzywo sztuczne i brak odporności na wodę (lub zaledwie IP53). Używany flagowiec oferuje ramkę z lotniczego aluminium lub stali oraz wodoszczelność IP68.
- **Jakość zdjęć i wideo:** Tani telefon kusi marketingowym hasłem "aparat 108 MP", ale łączy go z ciemnym obiektywem, brakiem stabilizacji OIS i tanim sensorem makro 2 MP. Flagowiec posiada zaawansowany procesor sygnałowy ISP, duży fizyczny rozmiar piksela i optykę najwyższej klasy.
- **Szybkość pamięci:** Nowe budżetowce często stosują powolne kości eMMC lub UFS 2.2. Używany flagowiec pracuje na ultraszybkiej pamięci NVMe lub UFS 3.1, co zapobiega przycinaniu interfejsu po roku pracy.
- **Płynność i odsprzedaż:** Tani telefon po 2 latach traci 80% wartości i jest trudny do sprzedania. Używany flagowiec nadal znajduje nabywców w ciągu kilkudziesięciu godzin na platformach ogłoszeniowych.

---

## 6. Kiedy zakup nowego flagowca ma twarde uzasadnienie ekonomiczne

Choć rynek wtórny oferuje bezkonkurencyjny stosunek jakości do ceny, zakup fabrycznie nowego urządzenia w salonie bywa w pełni racjonalny:

1. **Koszt przestoju (Cost of downtime):**
Dla twórcy wideo, menedżera czy osoby prowadzącej handel mobilny telefon jest głównym narzędziem generowania przychodu. Jeden dzień przestoju spowodowany nieoczekiwaną awarią może kosztować więcej niż rata za nowy sprzęt. Pełna gwarancja, pakiet wymiany door-to-door i 100% baterii zapewniają niezawodność operacyjną.

2. **Programy odkupu (Trade-in), promocje i raty 0%:**
Producenci w okresach premierowych oferują dopłaty do odkupu starego telefonu (nawet 500-1 000 zł bonusu) oraz prawdziwe raty 0% bez prowizji. Przy stabilnej inflacji rozłożenie płatności na 20 nieoprocentowanych rat obniża realny koszt kapitału.

3. **Strategia jednego właściciela na 5-6 lat:**
Kupienie nowego flagowca z zamiarem eksploatacji przez cały okres wsparcia producenta (5-7 lat) to znakomita strategia. Jednorazowy wydatek 4 200 zł plus jedna planowa wymiana baterii po 3 latach (~400 zł) rozkłada się na 60-72 miesiące, dając średni koszt rzędu 65-75 zł miesięcznie przy pełnej kontroli nad historią sprzętu.

4. **Eliminacja ryzyka blokad ratalnych i asymetrii informacji:**
Polski rynek wtórny niesie ze sobą ryzyko blokady IMEI przez operatorów (Plus, Play, T-Mobile, Orange) w przypadku niespłacania rat przez pierwotnego właściciela. Dochodzą do tego nieautoryzowane chińskie zamienniki ekranów bez True Tone oraz ukryte profile korporacyjne MDM. Zakup od oficjalnego dystrybutora eliminuje to ryzyko w całości.

> **Zasada IleZa:** Najdroższy telefon z drugiej ręki to ten kupiony bez weryfikacji numeru IMEI u operatora. Wystarczy jedna niespłacona rata pierwotnego abonenta w Plusie, Play, T-Mobile czy Orange, by flagowiec stał się bezużyteczną cegłą z zablokowanym modemem GSM.

---

## Podsumowanie i matryca wyboru

Wybierz **używanego flagowca (wiek 2-3 lata)**, jeżeli:
- Płacisz z własnych, opodatkowanych oszczędności jako osoba prywatna.
- Prowadzisz działalność na ryczałcie lub nie jesteś podatnikiem VAT.
- Chcesz mieć bezkompromisowy aparat, ekran OLED, wodoszczelność IP68 i płynność działania bez płacenia haraczu za nowość.
- Twój miesięczny budżet na sprzęt nie powinien przekraczać 50 zł.

Wybierz **nowy telefon z salonu**, jeżeli:
- Rozliczasz JDG na podatku liniowym lub spółkę z o.o. i odliczasz pełny VAT 23% na firmę.
- Twój model pracy wymaga ciągłości operacyjnej, a każda godzina awarii generuje straty finansowe.
- Planujesz kupić telefon raz i użytkować go przez 5-6 lat aż do wygaszenia wsparcia.
- Chcesz uniknąć jakichkolwiek formalności związanych z weryfikacją numeru IMEI, profili MDM czy historii napraw.

Świadoma decyzja finansowa wymaga odrzucenia emocji. Najdroższy telefon to ten kupiony impulsywnie na 3-letnią ratę u operatora pod wpływem reklamy, którego możliwości w 90% pokryłby zadbany model z rynku wtórnego.
MARKDOWN,
                'published_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ],
            [
                'locale' => 'en',
                'slug' => 'smartphone-economics-used-vs-new',
                'alternate_slug' => 'ekonomia-smartfonow-uzywany-czy-nowy',
                'title' => 'Smartphone economics: used vs new. Hard numbers, taxes, and market traps',
                'description' => 'Depreciation curve vs unboxing costs. A real two-year TCO balance, Polish business taxes and Sp. z o.o., the VAT-margin trap, and market value analysis from factory-new to six-year-old phones.',
                'body_markdown' => <<<'MARKDOWN'
## 1. The depreciation curve: who pays for unboxing

The biggest cost of a new phone is not the charger. It is not the case or the plan. It is the instant price drop when you break the factory seal.

In consumer electronics, this drop is fast:
- **First 24 hours:** Breaking the factory seal cuts resale value by 15% to 20%. Even in mint shape, an unsealed phone is second-hand gear.
- **First year:** An iPhone loses 20% to 25% of its launch price. Android flagships lose between 40% and 55% in those same twelve months.
- **Years two and three:** Value loss flattens out. A two-year-old iPhone sells for 50% to 55% of retail price. By year three, the price settles near 30% to 40%.
- **Year four and beyond:** The curve hits a firm floor. Yearly drops slow down. Resale price then depends mainly on screen condition and battery health.

> **IleZa Rule:** The first owner pays the steepest novelty tax in electronics. When you buy a two- or three-year-old phone, you get matching daily speed while someone else funded the drop.

{{ price("iphone-15-128gb") }}

---

## 2. The two-year TCO bill: new base model vs two-year-old used

When you pay with taxed personal savings, total cost of ownership (TCO) is what counts.

To keep the comparison fair, we look at the same product tier: a base premium flagship with 128 GB of storage (the iPhone 13-16 or Samsung Galaxy S tier):
- **Option A:** Buy a brand-new retail base flagship at launch for 4,200 PLN.
- **Option B:** Buy a clean two-year-old base flagship for 1,400 PLN and install a fresh battery.

Here is the real money balance after two years:

- **Option A: New base flagship (years 0 to 2)**
  - Purchase price: 4,200 PLN
  - Resale value after two years: ~2,000 PLN
  - Value loss (depreciation): 2,200 PLN
  - Accessories (fast charger, case, screen protector): 200 PLN
  - Screen insurance: 400 PLN
  - Warranty repairs: 0 PLN
  - **Total real cost over 2 years: ~2,800 PLN (~117 PLN per month)**

- **Option B: Two-year-old used base flagship (years 2 to 4)**
  - Purchase price: 1,400 PLN
  - Resale value after two years: ~800 PLN
  - Value loss (depreciation): 600 PLN
  - Fresh battery from a good repair shop: 400 PLN
  - Basic case and glass: 100 PLN
  - Minor repair buffer: 100 PLN
  - **Total real cost over 2 years: ~1,200 PLN (~50 PLN per month)**

The gap is **1,600 PLN in favor of the used phone**. That equals **over 66 PLN of clean cash saved every month**.

Capital opportunity cost widens this gap even more. Putting that 2,800 PLN starting difference into a 6% savings account yields around 280 PLN after Polish Belka tax over two years.

{{ price("iphone-13-128gb") }}

---

## 3. Business taxes: when a brand-new retail phone makes sense

Many contractors claim that buying on a company card makes a phone almost free. In practice, taxes soften the bill, but they never erase it.

A phone costs well under the 10,000 PLN statutory threshold. You can write it off at once in the month of purchase without multi-year accounting schedules.

### Sole proprietorship on linear tax (19%) and active 23% VAT
For a business owner on a 19% linear tax with full VAT deduction, buying a new 4,200 PLN phone breaks down like this:
- Gross price: 4,200 PLN (net: 3,415 PLN, VAT 23%: 785 PLN).
- Full 100% VAT deduction (work phone): you recover **785 PLN**.
- You write off the net amount in operating costs: 3,415 PLN.
- Income tax and health contribution relief (23.9% of 3,415 PLN): saves **816 PLN**.
- Total tax shield: `785 PLN + 816 PLN` = **1,601 PLN** (about 38% of gross retail price).
- **Effective purchase cost: around 2,599 PLN** (`3,415 PLN net - 816 PLN income tax relief`).

### Sole proprietorship on Polish flat lump-sum tax (Ryczalt)
Rules change completely under Ryczalt:
- You have zero deductible revenue costs (KUP = 0 PLN). You cannot deduct a single zloty from your income tax.
- A VAT-registered contractor reclaims only the 785 PLN VAT, paying 3,415 PLN net.
- A contractor exempt from VAT pays the full 4,200 PLN out of pocket.

### Limited liability company (Spolka z o.o.)
- **Standard CIT (9% small business or 19% standard):** The company deducts 100% VAT and expenses the net amount. CIT tax relief cuts the bill by 9% (307 PLN) or 19% (649 PLN), giving a real cost of 3,108 PLN or 2,766 PLN.
- **Estonian CIT:** Be careful with phones and plans bought for partners or board members. Unless you prove exclusive operational use, tax inspectors can treat the cost as a hidden profit or non-business expense, taxing it at 10% or 20%.

### The VAT-margin trap on the second-hand market
Many buyers overlook the VAT-margin invoice (faktura VAT-marza) used by most pawn shops and used device dealers:
- A VAT-margin invoice **offers zero deductible VAT**. The VAT amount in your tax filing is strictly 0 PLN.
- If you buy a used phone for 1,400 PLN on VAT-margin, a linear taxpayer writes off the full 1,400 PLN, gaining income tax and health relief (23.9%): **335 PLN**.
- **Real cost of the used shop phone: around 1,065 PLN** (`1,400 PLN - 335 PLN`).

> **IleZa Rule:** Tax write-offs are not free store discounts. With a VAT-margin invoice you lose the 23% VAT deduction. Yet thanks to a lower starting price, a used flagship (1,065 PLN) still leaves company cash reserves over 1,500 PLN higher than buying new at retail (2,599 PLN).

---

## 4. Market tiers: from factory fresh to six years old

The phone market breaks down into four clear economic tiers:

### Tier 0-1 year: Top tier and peak depreciation (3,500 to 6,500 PLN)
- Latest processors, top camera sensors, and pristine 100% battery health.
- Full consumer rights and factory warranty protection.
- Highest cost per day of ownership.

### Tier 2-3 years: The sweet spot of practical value (1,000 to 1,800 PLN)
- Representative picks: iPhone 13, iPhone 14, Samsung Galaxy S22 or S23.
- Premium metal and glass frames, full IP68 water resistance, bright OLED screens, and optical image stabilization.
- Three to four full years of security and system updates ahead.
- A 400 PLN original battery replacement restores factory battery life for years to come.

### Tier 4-5 years: Pure budget utility (400 to 800 PLN)
- Representative picks: iPhone 11, iPhone 12.
- Capable daily drivers for messaging, maps, banking apps, and government ID tools like mObywatel.
- Tiny yearly price drops of 50 to 100 PLN per year.
- Great pick for a kid, a backup travel phone, or a basic staff device.

{{ price("iphone-11-128gb") }}

### Tier 6 years and older: Security cutoff and fading support
- Factory operating system upgrades and web browser engine patches come to an end.
- Banking apps and wireless payments phase out older operating systems.
- Replacing the battery often costs more than the market value of the phone itself.

---

## 5. New mid-ranger vs two-year-old flagship

Shoppers often face this choice: buy a brand-new budget phone for 1,200 to 1,500 PLN or grab a two-year-old flagship for the same money?

From an engineering view, the used flagship wins on almost every count:
- **Build quality:** Budget phones rely on plastic bodies with weak splash resistance (IP53). A used flagship gives you aluminum or steel builds and real IP68 water protection.
- **Camera hardware:** Cheap phones advertise huge 108 MP numbers paired with tiny glass, no stabilization, and poor 2 MP macro sensors. A flagship uses large sensors, high-grade lenses, and fast signal processors.
- **Storage speed:** Cheap phones often use sluggish eMMC or UFS 2.2 chips. A flagship uses fast NVMe or UFS 3.1 storage that prevents UI lag over time.
- **Resale liquidity:** A budget phone loses 80% of its value in two years and is hard to sell. A clean flagship finds buyers within days on local platforms.

---

## 6. When buying a brand-new flagship makes hard sense

While the used market offers huge savings, buying factory-new at retail is fully justified in specific cases:

1. **Cost of downtime:**
If your phone is your main income tool (video creation, mobile trading, or client fieldwork), an unexpected failure costs real revenue. A factory warranty, advance swap options, and fresh battery cells ensure operational uptime.

2. **Trade-in bonuses and zero-interest financing:**
Launch promotions often include trade-in extras (up to 500 or 1,000 PLN in bonus credit) alongside genuine 0% APR installment plans. When general inflation stays steady, spreading payments over 20 fee-free installments lowers real capital cost.

3. **The single-owner five-year plan:**
Buying a retail flagship and keeping it for its full support window (5 to 7 years) is a sound strategy. A single 4,200 PLN purchase plus one battery swap at year three (~400 PLN) spreads over 60 to 72 months. That works out to 65 to 75 PLN per month with total trust in device history.

4. **Avoiding carrier blacklists and market traps:**
The Polish used market carries real risks of carrier IMEI blacklisting (Plus, Play, T-Mobile, Orange) if a previous owner stops paying installments. There are also risks of cheap screen replacements without True Tone and hidden corporate MDM locks. Buying from an authorized dealer removes these risks entirely.

> **IleZa Rule:** The costliest second-hand phone is one bought without checking the carrier IMEI database. A single missed installment payment by the original buyer turns a premium flagship into a locked brick.

---

## Summary and decision guide

Pick a **used flagship (2 to 3 years old)** if:
- You pay out of pocket with taxed personal savings.
- You run a small business under flat Ryczalt or operate without VAT.
- You want flagship cameras, OLED displays, water resistance, and smooth speed without paying novelty markups.
- Your monthly hardware target is under 50 PLN.

Pick a **brand-new retail phone** if:
- You run a business under 19% linear tax or a company and claim full 23% VAT.
- Your work demands total uptime, where hours of downtime cost more than monthly hardware bills.
- You plan to keep the device for 5 to 6 years until security patches end.
- You want zero hassle with IMEI verification, MDM checks, or third-party repair history.

Smart money choices cut through hype. The costliest device is an impulse flagship on a three-year carrier contract. A clean second-hand flagship does ninety percent of the job for a fraction of the bill.
MARKDOWN,
                'published_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ],
        ];
    }
    // phpcs:enable Generic.Files.LineLength.TooLong
}
