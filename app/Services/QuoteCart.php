<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Unit;
use App\Support\ComposedUnit;
use App\Support\UnitConfiguration;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The "Mijn offerte" list: units and products a visitor wants a quote for, and units they put together in the unit
 * composer, kept in the session.
 */
class QuoteCart
{
    private const SESSION_KEY = 'quote.items';

    private const COMPOSITIONS_KEY = 'quote.compositions';

    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'unit' => Unit::class,
        'product' => Product::class,
    ];

    public function __construct(private Session $session) {}

    public function add(string $type, int $id, int $quantity = 1): void
    {
        $items = $this->raw();
        $key = $this->key($type, $id);
        $items[$key] = max(1, ($items[$key] ?? 0) + $quantity);

        $this->session->put(self::SESSION_KEY, $items);
    }

    /**
     * Add a composition from the unit composer; adding the same composition again raises its quantity.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function addComposition(array $configuration, int $quantity = 1): void
    {
        $composition = new ComposedUnit(UnitConfiguration::normalize($configuration));
        $compositions = $this->compositions();
        $key = $composition->key();

        $compositions[$key] = [
            'configuration' => $composition->configuration,
            'quantity' => max(1, ($compositions[$key]['quantity'] ?? 0) + $quantity),
        ];

        $this->session->put(self::COMPOSITIONS_KEY, $compositions);
    }

    public function update(string $type, string $id, int $quantity): void
    {
        if ($type === ComposedUnit::TYPE) {
            $this->updateComposition($id, $quantity);

            return;
        }

        $items = $this->raw();
        $key = $this->key($type, $id);

        if (! array_key_exists($key, $items)) {
            return;
        }

        if ($quantity < 1) {
            unset($items[$key]);
        } else {
            $items[$key] = $quantity;
        }

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function remove(string $type, string $id): void
    {
        if ($type === ComposedUnit::TYPE) {
            $this->updateComposition($id, 0);

            return;
        }

        $items = $this->raw();
        unset($items[$this->key($type, $id)]);

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget([self::SESSION_KEY, self::COMPOSITIONS_KEY]);
    }

    public function count(): int
    {
        return count($this->raw()) + count($this->compositions());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Resolved items, compositions last; entries whose model no longer exists are dropped.
     *
     * @return Collection<int, array{key: string, type: string, model: Model|ComposedUnit, quantity: int}>
     */
    public function items(): Collection
    {
        $compositions = collect($this->compositions())->map(fn (array $composition, string $key): array => [
            'key' => ComposedUnit::TYPE.':'.$key,
            'type' => ComposedUnit::TYPE,
            'model' => new ComposedUnit(UnitConfiguration::normalize($composition['configuration'])),
            'quantity' => $composition['quantity'],
        ]);

        return collect($this->raw())
            ->map(function (int $quantity, string $key): ?array {
                [$type, $id] = explode(':', $key) + [null, null];
                $class = self::TYPES[$type] ?? null;
                $model = $class ? $class::query()->published()->find($id) : null;

                return $model ? ['key' => $key, 'type' => $type, 'model' => $model, 'quantity' => $quantity] : null;
            })
            ->filter()
            ->values()
            ->concat($compositions->values());
    }

    private function updateComposition(string $key, int $quantity): void
    {
        $compositions = $this->compositions();

        if (! array_key_exists($key, $compositions)) {
            return;
        }

        if ($quantity < 1) {
            unset($compositions[$key]);
        } else {
            $compositions[$key]['quantity'] = $quantity;
        }

        $this->session->put(self::COMPOSITIONS_KEY, $compositions);
    }

    /**
     * @return array<string, array{configuration: array<string, mixed>, quantity: int}>
     */
    private function compositions(): array
    {
        return $this->session->get(self::COMPOSITIONS_KEY, []);
    }

    /**
     * @return array<string, int>
     */
    private function raw(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }

    private function key(string $type, int|string $id): string
    {
        return "{$type}:{$id}";
    }
}
