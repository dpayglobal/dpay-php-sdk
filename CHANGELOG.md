# Changelog

Wszystkie istotne zmiany w tym projekcie są dokumentowane w tym pliku.
Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie zgodne z [SemVer](https://semver.org/lang/pl/).

## [Unreleased]

## [0.3.0] - 2026-11-19

Wersja dogania dokumentację API dpay (OpenAPI 1.6.0) i działa z obecnym API - nie wymaga nowego wydania API.

### Breaking

- **BREAKING** `refunds->checkAvailability()` zwraca `RefundAvailability` (niedostępny) zamiast rzucać wyjątek
  dla każdej odpowiedzi 4xx z kluczem `refund`, w tym 404 (transakcja nie istnieje, wcześniej
  `NotFoundException`) i 423 (zwrot przez BOK dpay). Przyczynę rozróżnia `getHttpStatus()`. Nadal rzucają:
  401 "Unauthorized request" (`AuthenticationException`), 422, 429 i 5xx.
- **BREAKING** Waluta kwoty trafia do `currency_code`: `Money` w walucie innej niż PLN wysyła `currency_code`
  bez `withCurrencyCode()` (wcześniej np. `Money::of(49900, 'CZK')` rejestrowało 499.00 w walucie domyślnej
  serwisu). `withCurrencyCode()` i `withPhoneNumber()` rzucają `InvalidArgumentException`, gdy waluta różni się
  od waluty kwoty - dla MB WAY użyj `Money::of($grosze, Currency::EUR)` zamiast `Money::pln()`. Kwota w PLN
  bez jawnego kodu wysyła body jak dotąd (bez `currency_code`).

### Added

- IPN `alias_update` (BLIK OneClick i płatności cykliczne): `IpnVerifier::constructEvent()` weryfikuje podpis
  `sha256(id . hash . type . version)`, `IpnType::ALIAS_UPDATE`, `IpnEvent::isAliasUpdate()`
  i `getAliasUpdate()` (`AliasUpdate` ze stałymi `change_type`, `alias_type` i `alias_status`).
- `CardError` (code, declineCode, declineMessage): `CardPaymentException::getCardError()` z obiektu `error`
  odmowy karty oraz `ApiErrorException::getCardError()` i `getTransactionId()` z pól `cardError`
  i `transactionId` błędu Payments API (np. odrzucone pobranie na zapisanej karcie).
- `$dpay->payouts->list()` i `iterate()` (`POST /api/v1/pbl/withdraws`, filtry `date_from`, `date_to`,
  `state`, `type`, strony do 200) oraz `detailsFull()` (`POST /api/v1/pbl/withdraws/details/full`: nagłówek,
  sumy całego rozliczenia i pozycje z `payment_id`, `custom`, `ipksef`, strony do 500); modele `PayoutPage`,
  `PayoutListItem`, `PayoutSettlement`, `SettlementPosition`, `PageMeta`, `PayoutState`, `PayoutType`.
- `PayoutDetails::getCurrency()` (z `currency_code`, PLN gdy go brak), `getExportDate()` i `isPaysafecard()`.
- `refunds->create()`: opcjonalny piąty argument `$messageId` - nagłówek `DigitalPayments-PSP-Message-ID`
  (tytuł przelewu `dpay.pl zwrot {messageId}`, poza sumą kontrolną).
- `RegisteredPayment::getMultibanco()` (`MultibancoReference`: encja, referencja, kwota, ważność)
  i `getPaidTransaction()` (`RegisteredTransaction` z odpowiedzi "Transaction paid", statusy pending, paid,
  captured, canceled).
- Typowane obiekty webhooków: `WebhookEvent::getPayment()`, `getRefund()`, `getRecurringPayment()`,
  `getPayout()` (`PaymentObject`, `RefundObject`, `RecurringPaymentObject`, `PayoutObject`), `Failure`,
  `DeclineCode` (22 kody odmów dpay) i `FailureCategory`.
- `RecurringRetryResult::isSuccess()` i `RetryRefusal` (kody `errors.retry` odmowy ponowienia).
- `Transaction::getRate()`, `getMinimalFee()`, `getPermanentFee()` i `getUrls()` (`TransactionUrls`).
- `ApplePayRequest::withEmail()` i `CardPaymentResult::getApplePayMerchantSession()` (sesja ApplePayMerchantSession
  z `redirectText` odpowiedzi APPLE_PAY_INIT).
- `RateLimitException::getReset()` (nagłówek `X-RateLimit-Reset`), `Currency::USD` i `Currency::RON`,
  `DeviceInfo::getBrowserJavaEnabled()`.
- `tests/Fixtures/api_vectors.json`: wektory IPN transfer, dcb i alias_update oraz sum list i szczegółów
  wypłat.

### Changed

- Walidacja przed wysłaniem tego, co API i tak odrzuca (422 lub 400):
  - etykieta `register_blik_alias` (`BlikAliasRegistration`) i `recurring_registration`
    (`RecurringRegistration`) do 35 znaków (limit BLIK, wcześniej 50);
  - `mb_way_direct` wymaga kanału (`withChannel('373')`) i `DeviceInfo` z `withBrowserJavaEnabled()`;
  - kanał bezpośredni wymaga emaila, imienia i nazwiska płatnika oraz `withAcceptTos()`; kanały `tester`,
    `paypal`, `installment` i `paysafecard` są odrzucane;
  - `phone_number` 9-15 znaków, `alias_ipn_url` do 500 znaków;
  - `efaktura` nie łączy się z `blik_code`, `blik_alias`, `register_blik_alias`, płatnościami cyklicznymi
    ani aliasami kart;
  - `PayoutPosition`: IBAN `PL` + 26 cyfr z poprawną sumą kontrolną mod 97 (spacje i małe litery dozwolone)
    i kwota powyżej 0.
- `ApiErrorException::getTransactionId()` przeniesione z `PaymentRejectedException` do klasy bazowej
  (bez zmiany zachowania).

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
