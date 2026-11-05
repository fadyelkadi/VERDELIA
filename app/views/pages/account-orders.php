<?php
declare(strict_types=1);
$ordersResult = is_array($accountOrdersResult ?? null) ? $accountOrdersResult : ['items'=>[],'page'=>1,'total_pages'=>1,'total'=>0];
?>
<section class="simple-page-head page-gutter"><p class="eyebrow">Espacio privado</p><h1>Mis<br><em>pedidos.</em></h1><p><?= (int)$ordersResult['total'] ?> operaciones ficticias vinculadas a tu cuenta.</p></section>
<section class="order-history page-gutter"><div class="admin-panel-heading"><div><p class="eyebrow">Historial personal</p><h2>Compras de demostración</h2></div><a class="text-link" href="/cuenta">Volver a mi cuenta</a></div>
  <?php if (($ordersResult['items'] ?? []) === []): ?><div class="empty-cart"><span class="empty-cart-mark">✳</span><h2>Aún no tienes pedidos.</h2><p>Cuando completes una compra demo, aparecerá aquí.</p><a class="button button-outline" href="/tienda">Explorar la colección</a></div>
  <?php else: ?><div class="table-scroll"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Estado</th><th>Pago más reciente</th><th>Total</th><th></th></tr></thead><tbody>
    <?php foreach ($ordersResult['items'] as $order): ?><tr><td><?= h($order['order_number']) ?></td><td><?= h($order['created_at']) ?></td><td><?= h(match ($order['status']) {'PAID'=>'Pagado','FULFILLED'=>'Entregado','CANCELLED'=>'Cancelado',default=>'Pendiente'}) ?></td><td><?= h(match ($order['payment_status']) {'APPROVED'=>'Aprobado','REJECTED'=>'Rechazado','CANCELLED'=>'Cancelado','PENDING'=>'Pendiente',default=>'Sin intentos'}) ?></td><td><?= h(money((int)$order['total_cents'])) ?></td><td><a class="text-link" href="/pedido/<?= rawurlencode((string)$order['order_number']) ?><?= in_array($order['status'],['PAID','FULFILLED'],true) ? '/recibo' : '' ?>">Consultar</a></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
  <?php if ((int)$ordersResult['total_pages'] > 1): ?><nav class="pagination" aria-label="Paginación de pedidos"><?php for($p=1;$p<=(int)$ordersResult['total_pages'];$p++): ?><a class="<?= (int)$ordersResult['page']===$p?'is-current':'' ?>" href="/cuenta/pedidos?page=<?= $p ?>" aria-current="<?= (int)$ordersResult['page']===$p?'page':'false' ?>"><?= $p ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
