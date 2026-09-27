# Changelog

Wszystkie istotne zmiany w tym projekcie są dokumentowane w tym pliku.
Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie zgodne z [SemVer](https://semver.org/lang/pl/).

## [Unreleased]

## [0.2.0] - wydanie razem z wdrożeniem API dpay

Wersja wymaga API dpay z tym samym wydaniem (wspólne API płatności cyklicznych, suma kontrolna capture
i anulowania kart). Zmiany łamiące zgodność są oznaczone jako **BREAKING**.

### Added

- `$dpay->recurring` (`RecurringService`): `status()`, `retry()` i `cancel()` płatności cyklicznej
  (`/api/v1_0/payments/recurring/*`), modele `RecurringStatus`, `RecurringRegistrationInfo`, `RecurringRetryResult`.
- `RegisterPaymentRequest::withRecurringRegistration(RecurringRegistration)` - rejestracja płatności cyklicznej
  (modele O, A i M, `terms_url` wymagany) z kodem BLIK klienta.
- `RegisterPaymentRequest::withRecurringAlias()` - obciążenie zapisanej płatności cyklicznej bez kodu BLIK;
  alias wchodzi do sumy kontrolnej. `withClientContext()` - opcjonalne IP i przeglądarka klienta przy obciążeniu.
- Webhooki: `WebhookVerifier::constructEvent()` (Standard Webhooks, podpis `v1`, tolerancja czasu, kilka
  podpisów i sekretów w czasie rotacji), `WebhookEvent`, `WebhookEventType`.
- `$dpay->events` (`EventService`): historia zdarzeń z filtrami, `list()` i `iterate()` po stronach.
- `WebhookTarget` - własny adres zdarzeń w rejestracji płatności (`withWebhook()`), zwrocie (`refunds->create()`)
  i capture karty (`cards->capture()`); `RegisterPaymentRequest::withReference()`.
- `ApiErrorException::getReason()`, `PaymentRejectedException::getErrorDescription()`,
  `RegisteredPayment::getRecurringAlias()` i `getRecurringMethods()`.
- `tests/Fixtures/api_vectors.json` - wspólne wektory sum kontrolnych i podpisów webhooków wszystkich SDK dpay.

### Changed

- **BREAKING** `cards->capture()` i `cards->cancel()` wysyłają `service` i sumę
  `sha256(operacja|service|transaction_id|amount|hash)` - API odrzuca je bez sumy (401).
- **BREAKING** `ReturnUrls`: adres IPN jest opcjonalny (`getIpn(): ?string`); bez niego `url_ipn` nie jest wysyłany,
  a IPN nie przychodzi (wynik przychodzi webhookiem).
- `ErrorMapper` czyta kod błędu z pola `code` (np. `CHECKSUM_REQUIRED`, `WEBHOOK_URL_INVALID`), potem z `errorcode`.
- `ChecksumCalculator::orderedBody()` przyjmuje całe body i spłaszcza obiekty zagnieżdżone (np. `webhook`).

### Removed

- **BREAKING** `BlikService::recurringStatus()`, `BlikRecurringRegistration`, `BlikRecurringStatus`,
  `BlikRecurringRegistrationInfo` i `RegisterPaymentRequest::withRegisterBlikRecurringAlias()` - API usunęło
  te endpointy i pole; użyj `$dpay->recurring` i `withRecurringRegistration()`.
- **BREAKING** `BlikAliasType::PAYID` (aliasy OneClick są tylko `UID`), `TransactionType::BLIK_RECURRING`
  i `TransactionType::BIZUM_DIRECT` (API odrzuca je kodem 422).

### Deprecated

- `IpnType::CAPTURE`, `IpnEvent::isCapture()` i `getCapturePaymentId()` - dpay nie wysyła już IPN typu `capture`;
  użyj zdarzenia `payment.captured`.

## [0.1.1] - 2026-07-22

### Added

- `RefundAvailability::getHttpStatus()` - kod HTTP odpowiedzi jako stabilny wyróżnik przyczyny odmowy
- `Currency::isValid()`

### Changed

- `refunds->checkAvailability()` zwraca wynik biznesowy zamiast wyjątku dla wszystkich
  biznesowych kodów odmowy zwrotu (400, 402, 406, 409, 410, 411 oraz biznesowego 401),
  zgodnie z rzeczywistym zachowaniem API
- Daty w `BlikRecurringRegistration` walidowane w formacie `YYYY-MM-DD`
- Kwoty float w body JSON enkodowane w najkrótszej postaci niezależnie od `serialize_precision`

## [0.1.0] - 2026-07-21

### Added

- Rejestracja płatności (`payments->register`) z pełną macierzą pól, w tym `transactionType`
- Szczegóły transakcji ze zwrotami (`payments->details`)
- Zwroty pełne i częściowe oraz sprawdzanie dostępności zwrotu (`refunds`)
- Lista banków pay-by-link (`banks`)
- BLIK: aliasy OneClick, wyrejestrowanie, status aliasów Recurring, BLIK Level 0 (`blik`)
- Karty S2S: szyfrowanie RSA, płatność OTP/3DS, pre-autoryzacja, capture, anulowanie,
  Google Pay, Apple Pay, DCC (`cards`)
- Szczegóły wypłat 1:1 (`payouts`)
- Weryfikacja podpisów IPN (`IpnVerifier`)
- Hierarchia wyjątków z normalizacją błędów API
