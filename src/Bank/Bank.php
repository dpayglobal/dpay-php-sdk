<?php

declare(strict_types=1);

namespace DPay\Bank;

final class Bank
{
    /** @var array<mixed> */
    private array $raw;

    private string $id;

    private string $name;

    private ?string $image;

    private int $onFrom;

    private int $onTo;

    private ?int $iterator;

    private bool $test;

    private ?string $type;

    /**
     * @param array<string, mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->id = isset($raw['id']) && is_scalar($raw['id']) ? (string) $raw['id'] : '';
        $this->name = isset($raw['name']) && is_scalar($raw['name']) ? (string) $raw['name'] : '';
        $this->image = isset($raw['image']) && is_string($raw['image']) ? $raw['image'] : null;
        $onFrom = $raw['on_from'] ?? 0;
        $this->onFrom = is_scalar($onFrom) ? (int) $onFrom : 0;
        $onTo = $raw['on_to'] ?? 0;
        $this->onTo = is_scalar($onTo) ? (int) $onTo : 0;
        $iterator = $raw['iterator'] ?? null;
        $this->iterator = isset($iterator) && is_scalar($iterator) ? (int) $iterator : null;
        $test = $raw['test'] ?? false;
        $this->test = is_scalar($test) ? (bool) $test : false;
        $this->type = isset($raw['type']) && is_string($raw['type']) ? $raw['type'] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getOnFrom(): int
    {
        return $this->onFrom;
    }

    public function getOnTo(): int
    {
        return $this->onTo;
    }

    public function getIterator(): ?int
    {
        return $this->iterator;
    }

    public function isTest(): bool
    {
        return $this->test;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}
