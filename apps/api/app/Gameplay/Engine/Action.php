<?php

namespace App\Gameplay\Engine;

/**
 * A move a seat wants to make: a type plus game-specific data, e.g. ["type" => "place", "cell" => 4].
 */
final readonly class Action
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $type,
        public array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  {type: string, ...payload}
     */
    public static function fromArray(array $data): self
    {
        $type = (string) ($data['type'] ?? '');
        unset($data['type']);
        ksort($data);

        return new self($type, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['type' => $this->type, ...$this->payload];
    }

    /** Same type and same data (key order doesn't matter). */
    public function equals(self $other): bool
    {
        $a = $this->payload;
        $b = $other->payload;
        ksort($a);
        ksort($b);

        return $this->type === $other->type && $a == $b;
    }
}
