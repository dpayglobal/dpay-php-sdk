<?php

declare(strict_types=1);

namespace DPay\Payout;

/** `meta` of a paginated payout response: the page returned, its size and the totals of the whole result. */
final class PageMeta
{
    private int $page;

    private int $perPage;

    private int $total;

    private int $totalPages;

    private function __construct(int $page, int $perPage, int $total, int $totalPages)
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->total = $total;
        $this->totalPages = $totalPages;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(self::int($data, 'page', 1), self::int($data, 'per_page', 0), self::int($data, 'total', 0), self::int($data, 'total_pages', 0));
    }

    /** Page that was returned - echoed back even beyond the last page. */
    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /** Number of items in the whole result. */
    public function getTotal(): int
    {
        return $this->total;
    }

    /** 0 when the result is empty. */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function hasMore(): bool
    {
        return $this->page < $this->totalPages;
    }

    /**
     * @param array<mixed> $data
     */
    private static function int(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
