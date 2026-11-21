<?php

declare(strict_types=1);

namespace DPay\Card;

use stdClass;

final class CardPaymentResult
{
    /** @var array<mixed> */
    private array $raw;

    private ?string $redirectType;

    private ?string $redirectText;

    private ?DccOffer $dccOffer;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $message = is_array($raw['message'] ?? null) ? $raw['message'] : [];
        $this->redirectType = isset($message['redirectType']) && is_string($message['redirectType'])
            ? $message['redirectType']
            : null;
        $this->redirectText = isset($message['redirectText']) && is_string($message['redirectText']) && $message['redirectText'] !== ''
            ? $message['redirectText']
            : null;
        $this->dccOffer = is_array($message['dccOffer'] ?? null) ? DccOffer::fromArray($message['dccOffer']) : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getRedirectType(): ?string
    {
        return $this->redirectType;
    }

    public function isSuccess(): bool
    {
        return $this->redirectType === RedirectType::SUCCESS;
    }

    public function requiresThreeDsForm(): bool
    {
        return $this->redirectType === RedirectType::FORM;
    }

    public function requiresRedirect(): bool
    {
        return $this->redirectType === RedirectType::URL;
    }

    public function hasDccOffer(): bool
    {
        return $this->redirectType === RedirectType::DCC_OFFER;
    }

    public function getThreeDsFormHtml(): ?string
    {
        if (!$this->requiresThreeDsForm() || $this->redirectText === null) {
            return null;
        }
        $decoded = base64_decode($this->redirectText, true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * Apple Pay merchant session from an APPLE_PAY_INIT call: `redirectType` FORM with the Base64-encoded
     * ApplePayMerchantSession JSON in `redirectText`. Pass it to `completeMerchantValidation()` on the device.
     * Null when the response does not carry a JSON object (e.g. a 3-D Secure form).
     *
     * @return array<mixed>|null
     */
    public function getApplePayMerchantSession(): ?array
    {
        $decoded = $this->getThreeDsFormHtml();
        if ($decoded === null) {
            return null;
        }
        // Only a JSON object is a merchant session
        if (!json_decode($decoded) instanceof stdClass) {
            return null;
        }
        $session = json_decode($decoded, true);

        return is_array($session) ? $session : null;
    }

    public function getRedirectUrl(): ?string
    {
        if (!$this->requiresRedirect() || $this->redirectText === null) {
            return null;
        }
        $decoded = base64_decode($this->redirectText, true);

        return $decoded === false ? null : $decoded;
    }

    public function getDccOffer(): ?DccOffer
    {
        return $this->dccOffer;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}
