<?php

namespace App\Platform\Slots;

/**
 * The slot provider adapters the code has (container tag "slot-adapters"). A provider without one can't be
 * switched on, and its games are never offered.
 */
final class SlotAdapters
{
    /** @var array<string, SlotAdapter> */
    private array $bySlug = [];

    /**
     * @param  iterable<SlotAdapter>  $adapters
     */
    public function __construct(iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $this->bySlug[$adapter->provider()] = $adapter;
        }
    }

    public function for(string $provider): ?SlotAdapter
    {
        return $this->bySlug[$provider] ?? null;
    }

    /** Whether the provider can run: an adapter with its secret configured. */
    public function ready(string $provider): bool
    {
        return ($this->for($provider)?->secret() ?? '') !== '';
    }

    /** @return list<string> */
    public function readyProviders(): array
    {
        return array_values(array_filter(array_keys($this->bySlug), fn (string $slug) => $this->ready($slug)));
    }
}
