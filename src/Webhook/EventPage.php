<?php

declare(strict_types=1);

namespace DPay\Webhook;

final class EventPage
{
    /** @var array<int, WebhookEvent> */
    private array $data;

    private bool $hasMore;

    private ?string $nextStartingAfter;

    /**
     * @param array<int, WebhookEvent> $data
     */
    private function __construct(array $data, bool $hasMore, ?string $nextStartingAfter)
    {
        $this->data = $data;
        $this->hasMore = $hasMore;
        $this->nextStartingAfter = $nextStartingAfter;
    }

    /**
     * @param array<mixed> $response
     */
    public static function fromArray(array $response): self
    {
        $events = [];
        foreach (is_array($response['data'] ?? null) ? $response['data'] : [] as $item) {
            if (is_array($item)) {
                $events[] = WebhookEvent::fromArray($item);
            }
        }
        $next = $response['next_starting_after'] ?? null;

        return new self($events, ($response['has_more'] ?? false) === true, is_string($next) ? $next : null);
    }

    /**
     * Events, newest first. Re-encoded by the API - do not verify webhook signatures on them.
     *
     * @return array<int, WebhookEvent>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function getNextStartingAfter(): ?string
    {
        return $this->nextStartingAfter;
    }
}
