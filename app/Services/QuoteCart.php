<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The "Mijn offerte" list: units and products a visitor wants a quote for, kept in the session.
 */
class QuoteCart
{
    private const SESSION_KEY = 'quote.items';

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

    public function update(string $type, int $id, int $quantity): void
    {
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

    public function remove(string $type, int $id): void
    {
        $items = $this->raw();
        unset($items[$this->key($type, $id)]);

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return count($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Resolved items; entries whose model no longer exists are dropped.
     *
     * @return Collection<int, array{key: string, type: string, model: Model, quantity: int}>
     */
    public function items(): Collection
    {
        return collect($this->raw())
            ->map(function (int $quantity, string $key): ?array {
                [$type, $id] = explode(':', $key) + [null, null];
                $class = self::TYPES[$type] ?? null;
                $model = $class ? $class::query()->published()->find($id) : null;

                return $model ? ['key' => $key, 'type' => $type, 'model' => $model, 'quantity' => $quantity] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<string, int>
     */
    private function raw(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }

    private function key(string $type, int $id): string
    {
        return "{$type}:{$id}";
    }
}
