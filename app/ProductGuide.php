<?php
declare(strict_types=1);

final class ProductGuide
{
    public static function forProduct(array $product): array
    {
        static $guides;
        $guides ??= require __DIR__.'/data/product-guides.php';
        $guide = $guides[$product['sku']] ?? [];
        return ($guide['slug'] ?? null) === $product['slug'] ? $guide : [];
    }

    public static function scientificName(array $product, array $guide): string
    {
        return trim((string)($product['scientific_name'] ?? '')) ?: ($guide['scientific'] ?? '');
    }
}
