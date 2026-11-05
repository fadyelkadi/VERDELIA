<?php
declare(strict_types=1);

function h(string|int|float|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int $cents): string
{
    return '$' . number_format($cents / 100, 2, '.', ',');
}

function lowercase(string $text): string
{
    if (function_exists('mb_strtolower')) return mb_strtolower($text, 'UTF-8');
    return strtr(strtolower($text), ['Á'=>'á','É'=>'é','Í'=>'í','Ó'=>'ó','Ú'=>'ú','Ü'=>'ü','Ñ'=>'ñ']);
}

function status_slug(string $status): string
{
    return match ($status) {
        'Pagado' => 'pagado',
        'En preparación' => 'preparacion',
        'Cancelado' => 'cancelado',
        default => 'muted',
    };
}

function query_url(array $changes = []): string
{
    $query = $_GET;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '' || $value === []) unset($query[$key]);
        else $query[$key] = $value;
    }
    return '/tienda' . ($query ? '?' . http_build_query($query) : '');
}

function icon(string $name, int $size = 20): string
{
    $paths = [
        'bag' => '<path d="M5 8h14l1 12H4L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/>',
        'arrow' => '<path d="M4 12h15M13 5l7 7-7 7"/>',
        'leaf' => '<path d="M20 4c-7.5 0-13 2.5-13 8.4A5.6 5.6 0 0 0 12.6 18C18.5 18 20 11 20 4Z"/><path d="M4 21c2.7-5.3 6.8-8.8 12-11"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'truck' => '<path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="18" r="1.5"/><circle cx="17.5" cy="18" r="1.5"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        'drop' => '<path d="M12 3c-3 4.2-6.5 7.3-6.5 11.2a6.5 6.5 0 0 0 13 0C18.5 10.3 15 7.2 12 3Z"/>',
    ];
    $content = $paths[$name] ?? $paths['leaf'];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $content . '</svg>';
}
