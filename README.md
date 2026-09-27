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

## Webhooki

Zdarzenia (`payment.succeeded`, `refund.failed`, `recurring_payment.canceled` i inne) są podpisane.
Weryfikuj je na surowym body, przed parsowaniem JSON:

```php
use DPay\Exception\SignatureVerificationException;
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

if ($event->getType() === 'payment.succeeded') {
    $payment = $event->getObject();   // kwoty w groszach
}
http_response_code(200);
```

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
    }

    http_response_code(200);
    echo IpnEvent::ACK;
} catch (SignatureVerificationException $exception) {
    http_response_code(400);
    echo 'Invalid signature';
}
```

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

$availability = $dpay->refunds->checkAvailability('identyfikator-transakcji');
if ($availability->isAvailable()) {
    // ...
}
```

## Szczegóły transakcji i banki

```php
$transaction = $dpay->payments->details('identyfikator-transakcji');
$transaction->isPaid();
$transaction->getAvailableRefundAmount()->toDecimal();
$transaction->getRefunds();

$banks = $dpay->banks->forService();
```

## Obsługa błędów

Wszystkie wyjątki SDK implementują `DPay\Exception\ExceptionInterface`.

```php
use DPay\Exception\ApiErrorException;
use DPay\Exception\InvalidRequestException;
use DPay\Exception\TransportException;

try {
    $payment = $dpay->payments->register($request);
} catch (InvalidRequestException $exception) {
    $exception->getFieldErrors();
} catch (ApiErrorException $exception) {
    $exception->getHttpStatus();
    $exception->getErrorCode();
} catch (TransportException $exception) {
    // błąd sieci - status płatności nieznany, użyj payments->details()
}
```

## Konfiguracja

| Opcja | Typ | Opis |
|---|---|---|
| `service` | string | Nazwa Punktu Płatności z panel.dpay.pl (wymagane) |
| `secret_hash` | string | Klucz Secret Hash (wymagane) |
| `timeout` | int | Timeout HTTP w sekundach (domyślnie 30) |
| `http_client` | HttpClientInterface | Własny klient HTTP (testy, proxy) |
| `base_urls` | array | Nadpisanie hostów API |

## Licencja

Apache-2.0
