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

## Obsługa IPN

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

    if ($event->isTransfer() || $event->isCapture()) {
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
