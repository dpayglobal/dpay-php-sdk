# Changelog

Wszystkie istotne zmiany w tym projekcie są dokumentowane w tym pliku.
Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie zgodne z [SemVer](https://semver.org/lang/pl/).

## [Unreleased]

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
