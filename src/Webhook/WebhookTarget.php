<?php

declare(strict_types=1);

namespace DPay\Webhook;

use InvalidArgumentException;

/**
 * Per-request webhook address (the `webhook` object of a payment registration, refund or card capture). Events for
 * this payment go to this URL, signed with the service's webhook secret, on top of the endpoints set in the panel.
 */
final class WebhookTarget
{
    private string $url;

    /** @var array<int, string> */
    private array $events;

    /**
     * @param array<int, string> $events
     */
    private function __construct(string $url, array $events)
    {
        if (strlen($url) > 500) {
            throw new InvalidArgumentException('Webhook URL must be at most 500 characters');
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false || strncasecmp($url, 'https://', 8) !== 0) {
            throw new InvalidArgumentException(sprintf('Webhook URL "%s" must be a valid https:// URL', $url));
        }
        if (count($events) !== count(array_unique($events))) {
            throw new InvalidArgumentException('Webhook events must be distinct');
        }
        WebhookEventType::assertAllowed($events, WebhookEventType::MERCHANT, 'a request');

        $this->url = $url;
        $this->events = array_values($events);
    }

    /**
     * @param array<int, string> $events empty = every event the request allows
     */
    public static function create(string $url, array $events = []): self
    {
        return new self($url, $events);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return array<int, string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param array<int, string> $allowed
     */
    public function assertEventsAllowed(array $allowed, string $context): void
    {
        WebhookEventType::assertAllowed($this->events, $allowed, $context);
    }

    /**
     * `url` first, then `events` - the refund checksum hashes the values in this order.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['url' => $this->url];
        if ($this->events !== []) {
            $data['events'] = $this->events;
        }

        return $data;
    }
}
