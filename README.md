# dpay PHP SDK

Oficjalna biblioteka PHP do integracji z API płatności [dpay.pl](https://dpay.pl).

## Wymagania

- PHP 7.4 lub nowszy (testowane do PHP 8.5)
- Rozszerzenia: `curl`, `json`, `openssl`

## Instalacja

```bash
composer require dpayglobal/dpay-php-sdk
```

## Szybki start

```php
use DPay\DPayClient;
use DPay\Money;
use DPay\Payment\RegisterPaymentRequest;
use DPay\Payment\ReturnUrls;
use DPay\Payment\TransactionType;

$dpay = new DPayClient([
    'service' => 'nazwa_serwisu',
    'secret_hash' => 'twoj_secret_hash',
]);

$payment = $dpay->payments->register(
    RegisterPaymentRequest::create(
        Money::pln(1050),
        TransactionType::TRANSFERS,
        new ReturnUrls(
            'https://twojsklep.pl/sukces',
            'https://twojsklep.pl/blad',
            'https://twojsklep.pl/ipn'
        )
    )
        ->withDescription('Zamówienie #1234')
        ->withCustom('order-1234')
);

if ($payment->getRedirectUrl() !== null) {
    header('Location: ' . $payment->getRedirectUrl());
    exit;
}
```

## Waluta

Waluta płatności to waluta kwoty `Money`. Kwota w innej walucie niż PLN wysyła `currency_code` sama
(od 0.3.0), np. CZK dla banków czeskich, EUR dla MB WAY, Multibanco i banków słowackich. Serwis musi mieć
tę walutę włączoną (PLN, EUR, CZK, USD, RON).

```php
use DPay\Currency;

RegisterPaymentRequest::create(Money::of(49900, Currency::CZK), TransactionType::TRANSFERS, $urls);
```

`withCurrencyCode()` i `withPhoneNumber()` przyjmują tylko walutę kwoty - `Money::pln()` z kodem `EUR` rzuca
`InvalidArgumentException`.

## MB WAY i kanały bezpośrednie

Kanał bezpośredni (`withChannel()`) wymaga emaila, imienia i nazwiska płatnika oraz `withAcceptTos()`.
MB WAY wymaga dodatkowo kanału `373` i pełnego `DeviceInfo` z `withBrowserJavaEnabled()` - SDK sprawdza to
przed wysłaniem (API odpowiedziałoby 422).

```php
use DPay\Currency;
use DPay\Payment\DeviceInfo;
use DPay\Payment\Payer;

$payment = $dpay->payments->register(
    RegisterPaymentRequest::create(Money::of(1250, Currency::EUR), TransactionType::MB_WAY_DIRECT, $urls)
        ->withPhoneNumber('+351912345678', Currency::EUR)
        ->withChannel('373')
        ->withPayer(Payer::create()->withEmail('cliente@example.pt')->withName('Joao', 'Silva'))
        ->withAcceptTos(true)
        ->withDeviceInfo(
            DeviceInfo::create($accept, 'pt-PT', 24, 1080, 1920, 0, $userAgent, 'Win32', '38.72,-9.14', $deviceId, 'Netscape')
                ->withBrowserJavaEnabled(false)
        )
);
```

Multibanco (kanał `390`, EUR) zwraca referencję do pokazania płatnikowi:

```php
$reference = $payment->getMultibanco();   // getEntity(), getReference(), getAmount(), getExpiresAt()
```

## Płatności cykliczne

Rejestracja idzie razem z płatnością kodem BLIK klienta (kwota `0` - sama zgoda, więcej - opłata inicjalna).
Kolejne obciążenia wysyła Twój serwer, bez kodu.

```php
use DPay\Money;
use DPay\Payment\RegisterPaymentRequest;
use DPay\Payment\ReturnUrls;
use DPay\Payment\TransactionType;
use DPay\Recurring\RecurringRegistration;

$urls = new ReturnUrls('https://twojsklep.pl/sukces', 'https://twojsklep.pl/blad');

$registration = $dpay->payments->register(
    RegisterPaymentRequest::create(Money::pln(0), TransactionType::TRANSFERS, $urls)
        ->withBlikCode($kodBlik, $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'])
        ->withRecurringRegistration(
            RecurringRegistration::create('Abonament Premium', RecurringRegistration::MODEL_O, 'https://twojsklep.pl/regulamin')
                ->withAlias('SUB-1234')
        )
);

$charge = $dpay->payments->register(
    RegisterPaymentRequest::create(Money::pln(4999), TransactionType::TRANSFERS, $urls)
        ->withRecurringAlias('SUB-1234')
        ->withDescription('Abonament Premium 10/2026')
);

$status = $dpay->recurring->status('SUB-1234');          // ACTIVE, INACTIVE, UNREGISTERED, EXPIRED, DECLINED
$retry = $dpay->recurring->retry($charge->getTransactionId()); // po odmowie, np. INSUFFICIENT_FUNDS
$dpay->recurring->cancel('SUB-1234', 'Rezygnacja klienta');
```

Obciążenie wiąże alias z sumą kontrolną, a anulowanie ma własną sumę - SDK liczy obie.
Limity API: `status` do 60, `retry` i `cancel` do 30 zapytań na minutę (licznik wspólny z resztą API
płatności z tego adresu IP) - nie odpytuj statusu w pętli, wynik przychodzi webhookiem.
Odmowę ponowienia opisuje `getFieldErrors()['retry']` (stałe `RetryRefusal`).
Zmiany statusu płatności cyklicznej przychodzą też jako IPN `alias_update` (patrz Obsługa IPN).

## Webhooki

Zdarzenia (`payment.succeeded`, `refund.failed`, `recurring_payment.canceled` i inne) są podpisane.
Weryfikuj je na surowym body, przed parsowaniem JSON:

```php
use DPay\Exception\SignatureVerificationException;
use DPay\Webhook\PaymentObject;
use DPay\Webhook\WebhookVerifier;

try {
    $event = WebhookVerifier::constructEvent(
        (string) file_get_contents('php://input'),
        getallheaders(),
        'whsec_...'                     // sekret endpointu z panelu; w czasie rotacji tablica sekretów
    );
} catch (SignatureVerificationException $exception) {
    http_response_code(400);
    exit;
}

$payment = $event->getPayment();        // PaymentObject, null dla innych obiektów
if ($payment !== null && $payment->getStatus() === PaymentObject::STATUS_SUCCEEDED) {
    markOrderAsPaid($payment->getMerchantReference(), $payment->getAmount()); // Money, grosze + waluta
}
$failure = $payment !== null ? $payment->getFailure() : null;
if ($failure !== null && $failure->isRetryable()) {
    // np. DeclineCode::INSUFFICIENT_FUNDS - klient może zapłacić ponownie
}
http_response_code(200);
```

Obiekty: `getPayment()`, `getRefund()`, `getRecurringPayment()`, `getPayout()`; surowa tablica - `getObject()`.

Deduplikuj zdarzenia po `$event->getId()`. Historię zdarzeń (np. po awarii endpointu) pobierzesz przez
`$dpay->events->iterate(['types' => ['payment.succeeded']])`.

Własny adres zdarzeń jednej płatności: `->withWebhook(WebhookTarget::create('https://twojsklep.pl/webhooks'))`
(podpisywany sekretem webhooków serwisu).

## Obsługa IPN

IPN przychodzi tylko wtedy, gdy podasz adres IPN w `ReturnUrls`.

dpay.pl uznaje IPN za dostarczony wyłącznie, gdy body odpowiedzi to dokładnie `OK`.
Kod HTTP nie jest sprawdzany. Zawsze weryfikuj kwotę z własnym zamówieniem.

```php
use DPay\Exception\SignatureVerificationException;
use DPay\Ipn\IpnEvent;
use DPay\Ipn\IpnVerifier;

try {
    $event = IpnVerifier::constructEvent(
        (string) file_get_contents('php://input'),
        'twoj_secret_hash'
    );

    if ($event->isTransfer()) {
        markOrderAsPaid($event->getId(), $event->getAmount());
    } elseif ($event->isAliasUpdate()) {
        $alias = $event->getAliasUpdate();   // getChangeType(), getAliasType(), getAliasStatus(), ...
    }

    http_response_code(200);
    echo IpnEvent::ACK;
} catch (SignatureVerificationException $exception) {
    http_response_code(400);
    echo 'Invalid signature';
}
```

IPN `alias_update` (zmiana aliasu BLIK OneClick lub płatności cyklicznej) przychodzi na `withAliasIpnUrl()`,
a bez niego na adres IPN. Ma inny podpis (`sha256(id . hash . type . version)`) - `constructEvent()` rozpoznaje
go po `type`.

## Zwroty

```php
use DPay\Money;

$dpay->refunds->create('identyfikator-transakcji');
$dpay->refunds->create('identyfikator-transakcji', Money::pln(500), 'reklamacja');

// Odpowiedź oznacza przyjęcie zwrotu - wynik przychodzi zdarzeniem refund.succeeded / refund.failed
$dpay->refunds->create(
    'identyfikator-transakcji',
    Money::pln(500),
    null,
    \DPay\Webhook\WebhookTarget::create('https://twojsklep.pl/webhooks/zwroty', ['refund.succeeded', 'refund.failed'])
);

// Własny identyfikator zwrotu (nagłówek DigitalPayments-PSP-Message-ID) - tytuł przelewu "dpay.pl zwrot RET-1234"
$dpay->refunds->create('identyfikator-transakcji', Money::pln(500), null, null, 'RET-1234');

$availability = $dpay->refunds->checkAvailability('identyfikator-transakcji');
if ($availability->isAvailable()) {
    // ...
}
```

`checkAvailability()` zwraca wynik (nie wyjątek) dla każdej odmowy biznesowej z kluczem `refund`: 400, 402,
404 (transakcja nie istnieje), 406, 409, 410, 411, 423 i biznesowego 401 - przyczynę rozróżnia
`getHttpStatus()`. Błędna suma kontrolna (401 "Unauthorized request"), 422, 429 i 5xx rzucają wyjątek.

## Szczegóły transakcji i banki

```php
$transaction = $dpay->payments->details('identyfikator-transakcji');
$transaction->isPaid();
$transaction->getAvailableRefundAmount()->toDecimal();
$transaction->getRefunds();

$transaction->getRate();                 // prowizja, np. "1.49"
$transaction->getUrls();                 // TransactionUrls: getSuccess(), getFail(), getIpn()

$banks = $dpay->banks->forService();
```

## Wypłaty

```php
use DPay\Payout\PayoutState;

foreach ($dpay->payouts->iterate(['date_from' => '2026-08-01', 'state' => PayoutState::PROCESSED]) as $payout) {
    $settlement = $dpay->payouts->detailsFull($payout->getId());
    foreach ($settlement->getPositions() as $position) {
        $position->getPaymentId();   // transakcja z IPN, getCustom(), getNet(), getFee(), getGross()
    }
}
```

`list()` zwraca jedną stronę (`per_page` do 200), `detailsFull()` - sumy całego rozliczenia
(`getSummaryNet()`, `getSummaryCount()`) i stronę pozycji (`per_page` do 500).

## Obsługa błędów

Wszystkie wyjątki SDK implementują `DPay\Exception\ExceptionInterface`.

```php
use DPay\Exception\ApiErrorException;
use DPay\Exception\InvalidRequestException;
use DPay\Exception\RateLimitException;
use DPay\Exception\TransportException;

try {
    $payment = $dpay->payments->register($request);
} catch (InvalidRequestException $exception) {
    $exception->getFieldErrors();
    $exception->getTransactionId();   // transakcja utworzona przed błędem, np. odrzucone pobranie z karty
    $exception->getCardError();       // CardError: getCode(), getDeclineCode() (ISO 8583), getDeclineMessage()
} catch (RateLimitException $exception) {
    $exception->getRetryAfter();      // sekundy; getReset() - czas Unix odnowienia limitu
} catch (ApiErrorException $exception) {
    $exception->getHttpStatus();
    $exception->getErrorCode();
} catch (TransportException $exception) {
    // błąd sieci - status płatności nieznany, użyj payments->details()
}
```

Odmowa karty z HTTP 200 (`success: false`) rzuca `CardPaymentException`: `getErrorCode()` to komunikat API
(np. `DCC_OFFER_EXPIRED`), a `getCardError()` - kod odmowy, gdy wystawca go podał.

## Konfiguracja

| Opcja | Typ | Opis |
|---|---|---|
| `service` | string | Nazwa Punktu Płatności z panel.dpay.pl (wymagane) |
| `secret_hash` | string | Klucz Secret Hash (wymagane) |
| `timeout` | int | Timeout HTTP w sekundach (domyślnie 30) |
| `http_client` | HttpClientInterface | Własny klient HTTP (testy, proxy) |
| `base_urls` | array | Nadpisanie hostów API, klucze: `api_payments`, `panel`, `gateway` |

## Licencja

Apache-2.0
