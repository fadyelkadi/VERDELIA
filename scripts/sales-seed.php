<?php
declare(strict_types=1);

/** Initial sample orders. The catalog stock is the opening stock at installation. */
function verdelia_seed_sales(PDO $pdo, array $catalog, array $customers, DateTimeImmutable $now): void
{
    // Thirteen closed months provide both the recent history and its comparison period.
    $monthlyDemand = [3, 3, 4, 4, 4, 5, 5, 6, 7, 7, 8, 9, 11, 12];
    $currentMonth = $now->modify('first day of this month')->setTime(0, 0);
    $orderInsert = $pdo->prepare('INSERT INTO orders(order_number,user_id,status,subtotal_cents,shipping_cents,total_cents,
        delivery_address,delivery_name,delivery_email,is_demo,created_at,confirmed_at) VALUES(?,?,?,?,0,?,?,?,?,1,?,?)');
    $lineInsert = $pdo->prepare('INSERT INTO order_items(order_id,product_id,sku_snapshot,name_snapshot,category_name_snapshot,
        unit_price_cents,unit_cost_cents,quantity,line_total_cents) VALUES(?,?,?,?,?,?,?,?,?)');
    $paymentInsert = $pdo->prepare('INSERT INTO payments(order_id,status,fake_reference,created_at) VALUES(?,?,?,?)');
    $sequence = 0;

    $addOrder = static function (array $product, int $quantity, DateTimeImmutable $date, string $status) use (
        $pdo, $customers, $orderInsert, $lineInsert, $paymentInsert, &$sequence
    ): void {
        $customer = $customers[$sequence % count($customers)];
        $dateTime = $date->format('Y-m-d H:i:s');
        $serial = strtoupper(substr(hash('sha256', 'verdelia-sample-v2/'.$dateTime.'/'.$sequence), 0, 10));
        $sequence++;
        $number = 'VD-'.$date->format('Ymd').'-'.$serial;
        $subtotal = (int)$product['price_cents'] * $quantity;
        $approved = $status !== 'CANCELLED';
        $orderInsert->execute([
            $number, (int)$customer['id'], $status, $subtotal, $subtotal, 'Dirección no registrada',
            $customer['name'], $customer['email'], $dateTime, $approved ? $dateTime : null,
        ]);
        $orderId = (int)$pdo->lastInsertId();
        $lineInsert->execute([
            $orderId, (int)$product['id'], $product['sku'], $product['name'], $product['category_name'],
            (int)$product['price_cents'], (int)$product['cost_cents'], $quantity, $subtotal,
        ]);
        $paymentInsert->execute([$orderId, $approved ? 'APPROVED' : 'REJECTED', 'SIM-'.$serial, $dateTime]);
    };

    foreach ($monthlyDemand as $index => $base) {
        $monthsAgo = count($monthlyDemand) - 1 - $index;
        $month = $currentMonth->modify("-{$monthsAgo} months");
        $monthDays = (int)$month->format('t');
        $days = $monthsAgo === 0 ? (int)$now->format('j') : $monthDays;
        foreach ($catalog as $productIndex => $product) {
            $plannedUnits = $base + ($productIndex % 4) + (($productIndex + 2 * $index) % 3) - 1;
            $remaining = intdiv($plannedUnits * $days, $monthDays);
            for ($basket = 0; $remaining > 0; $basket++) {
                $quantity = min($remaining, 1 + (($productIndex + $index + $basket) % 4));
                $remaining -= $quantity;
                $dayOffset = ($productIndex * 5 + $index * 3 + $basket * 7) % $days;
                $date = $month->modify("+{$dayOffset} days")->setTime(10 + $productIndex % 8, 20);
                if ($date > $now) $date = $now;
                $addOrder($product, $quantity, $date, ($monthsAgo > 0 && $sequence % 4 !== 0) ? 'FULFILLED' : 'PAID');
            }
        }
        if ($days >= 8) {
            $addOrder($catalog[$index % count($catalog)], 1, $month->modify('+6 days')->setTime(14, 15), 'CANCELLED');
        }
    }
}
