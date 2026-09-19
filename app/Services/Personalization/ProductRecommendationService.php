<?php

namespace App\Services\Personalization;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    private const COMPLETED_ORDER_WEIGHT = 5;

    private const WISHLIST_WEIGHT = 3;

    private const PRODUCT_VIEW_WEIGHT = 1;

    /**
     * @return Collection<int, Product>
     */
    public function recommendFor(User $user, int $limit = 8): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        /** @var Collection<int, array{product: Product, weight: int}> $sources */
        $sources = collect();

        $completedOrderProducts = $user->orders()
            ->where('status', 'completed')
            ->with('items.variant.product')
            ->get()
            ->flatMap(fn (Order $order) => $order->items)
            ->map(fn ($item) => $item->variant?->product)
            ->filter();

        $wishlistProducts = $user->wishlistProducts()->get();

        $viewedProducts = $user->productViews()
            ->with('product')
            ->get()
            ->pluck('product')
            ->filter();

        $this->addSources($sources, $completedOrderProducts, self::COMPLETED_ORDER_WEIGHT);
        $this->addSources($sources, $wishlistProducts, self::WISHLIST_WEIGHT);
        $this->addSources($sources, $viewedProducts, self::PRODUCT_VIEW_WEIGHT);

        $excludedIds = $sources->keys()->all();

        if ($sources->isEmpty()) {
            return $this->newestProducts($excludedIds, $limit);
        }

        $scoredProducts = Product::query()
            ->with('variants')
            ->whereNotIn('id', $excludedIds)
            ->get()
            ->map(fn (Product $product): array => [
                'product' => $product,
                'score' => $this->score($product, $sources),
            ])
            ->filter(fn (array $candidate): bool => $candidate['score'] > 0)
            ->sort(function (array $left, array $right): int {
                $scoreOrder = $right['score'] <=> $left['score'];
                if ($scoreOrder !== 0) {
                    return $scoreOrder;
                }

                $leftCreatedAt = $left['product']->created_at?->getTimestamp() ?? PHP_INT_MIN;
                $rightCreatedAt = $right['product']->created_at?->getTimestamp() ?? PHP_INT_MIN;
                $createdAtOrder = $rightCreatedAt <=> $leftCreatedAt;

                return $createdAtOrder !== 0
                    ? $createdAtOrder
                    : $right['product']->id <=> $left['product']->id;
            });

        if ($scoredProducts->isEmpty()) {
            return $this->newestProducts($excludedIds, $limit);
        }

        return $scoredProducts
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    /**
     * @param  Collection<int, array{product: Product, weight: int}>  $sources
     * @param  Collection<int, Product>  $products
     */
    private function addSources(Collection $sources, Collection $products, int $weight): void
    {
        foreach ($products->unique('id') as $product) {
            if (! $product instanceof Product) {
                continue;
            }

            $existing = $sources->get($product->id);

            $sources->put($product->id, [
                'product' => $product,
                'weight' => ($existing['weight'] ?? 0) + $weight,
            ]);
        }
    }

    /**
     * @param  Collection<int, array{product: Product, weight: int}>  $sources
     */
    private function score(Product $candidate, Collection $sources): int
    {
        return $sources->sum(function (array $source) use ($candidate): int {
            $score = 0;
            $sourceProduct = $source['product'];

            if ($candidate->category_id !== null && $candidate->category_id === $sourceProduct->category_id) {
                $score += 2 * $source['weight'];
            }

            if ($this->gendersAreCompatible($candidate->gender, $sourceProduct->gender)) {
                $score += $source['weight'];
            }

            return $score;
        });
    }

    private function gendersAreCompatible(?string $candidateGender, ?string $sourceGender): bool
    {
        return $candidateGender === $sourceGender
            || $candidateGender === 'unisex'
            || $sourceGender === 'unisex';
    }

    /**
     * @param  array<int, int>  $excludedIds
     * @return Collection<int, Product>
     */
    private function newestProducts(array $excludedIds, int $limit): Collection
    {
        return Product::query()
            ->with('variants')
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
