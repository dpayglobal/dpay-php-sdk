# Changelog

Wszystkie istotne zmiany w tym projekcie są dokumentowane w tym pliku.
Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie zgodne z [SemVer](https://semver.org/lang/pl/).

## [Unreleased]

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
