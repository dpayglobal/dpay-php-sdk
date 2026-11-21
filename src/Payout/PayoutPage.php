<?php

declare(strict_types=1);

namespace DPay\Payout;

/** One page of `payouts->list()`, newest payouts first. */
final class PayoutPage
{
    /** @var array<int, PayoutListItem> */
    private array $items;

    private PageMeta $meta;

    /**
     * @param array<int, PayoutListItem> $items
     */
    private function __construct(array $items, PageMeta $meta)
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @param array<mixed> $response
     */
    public static function fromArray(array $response): self
    {
        $items = [];
        foreach (is_array($response['withdraws'] ?? null) ? $response['withdraws'] : [] as $item) {
            if (is_array($item)) {
                $items[] = PayoutListItem::fromArray($item);
            }
        }

        return new self($items, PageMeta::fromArray(is_array($response['meta'] ?? null) ? $response['meta'] : []));
    }

    /**
     * @return array<int, PayoutListItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getMeta(): PageMeta
    {
        return $this->meta;
    }

    public function hasMore(): bool
    {
        return $this->items !== [] && $this->meta->hasMore();
    }
}
