<?php
declare(strict_types=1);

final class AdminException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 400)
    {
        parent::__construct($message);
    }
}

final class AdminRepository
{
    public function __construct(private PDO $pdo) {}

    public function dashboard(): array
    {
        $row = $this->pdo->query("SELECT
            (SELECT COUNT(*) FROM products WHERE active=1) AS active_products,
            (SELECT COUNT(*) FROM products WHERE active=1 AND stock=0) AS out_of_stock,
            (SELECT COUNT(*) FROM products WHERE active=1 AND stock BETWEEN 1 AND 15) AS low_stock,
            (SELECT COUNT(*) FROM users WHERE role='CUSTOMER') AS customers,
            (SELECT COUNT(*) FROM orders) AS orders_total,
            (SELECT COUNT(*) FROM orders WHERE status='PENDING_PAYMENT') AS orders_pending,
            (SELECT COUNT(*) FROM orders WHERE status IN ('PAID','FULFILLED')) AS orders_paid,
            (SELECT COALESCE(SUM(o.total_cents),0) FROM orders o WHERE o.status IN ('PAID','FULFILLED') AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.status='APPROVED')) AS confirmed_sales")->fetch();
        return is_array($row) ? $row : [];
    }

    public function products(string $search, string $status, int $page, int $perPage = 15): array
    {
        $where = ['1=1']; $params = [];
        $search = $this->shortText($search, 80);
        if ($search !== '') { $where[]='(p.name LIKE ? OR p.sku LIKE ?)'; $params[]=$search.'%'; $params[]=$search.'%'; }
        if ($status === 'active') $where[]='p.active=1';
        elseif ($status === 'inactive') $where[]='p.active=0';
        $clause=implode(' AND ',$where);
        $count=$this->pdo->prepare('SELECT COUNT(*) FROM products p WHERE '.$clause); $count->execute($params); $total=(int)$count->fetchColumn();
        [$page,$perPage,$pages]=$this->page($page,$perPage,$total);
        $query=$this->pdo->prepare('SELECT p.id,p.sku,p.slug,p.name,p.scientific_name,p.price_cents,p.cost_cents,p.stock,p.featured,p.active,c.name AS category_name,pi.local_path AS image_path,pi.alt_text AS image_alt FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN product_images pi ON pi.id=(SELECT i.id FROM product_images i WHERE i.product_id=p.id ORDER BY i.position_num,i.id LIMIT 1) WHERE '.$clause.' ORDER BY p.active DESC,p.name,p.id LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage));
        $query->execute($params);
        return ['items'=>$query->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$pages,'query'=>$search,'status'=>$status];
    }

    public function product(int $id): ?array
    {
        $query=$this->pdo->prepare('SELECT p.*,c.name AS category_name,pi.local_path AS image_path,pi.alt_text AS image_alt FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN product_images pi ON pi.id=(SELECT i.id FROM product_images i WHERE i.product_id=p.id ORDER BY i.position_num,i.id LIMIT 1) WHERE p.id=? LIMIT 1');
        $query->execute([$id]); $row=$query->fetch(); return is_array($row)?$row:null;
    }

    public function setProductActive(int $id, bool $active): void
    {
        $this->transaction(function() use($id,$active): void {
            $lookup=$this->pdo->prepare('SELECT category_id FROM products WHERE id=?'); $lookup->execute([$id]); $categoryId=$lookup->fetchColumn();
            if ($categoryId===false) throw new AdminException('El producto no existe.',404);
            $category=$this->pdo->prepare('SELECT active FROM categories WHERE id=? FOR UPDATE'); $category->execute([(int)$categoryId]); $categoryActive=$category->fetchColumn();
            if ($active && (int)$categoryActive!==1) throw new AdminException('Activa primero la categoría para publicar este producto.',409);
            $query=$this->pdo->prepare('UPDATE products SET active=? WHERE id=?'); $query->execute([$active?1:0,$id]);
        });
    }

    public function positiveId(mixed $value): int
    {
        $id=$this->positiveInt($value,'Identificador de producto no válido.');
        return (int)$id;
    }

    public function categories(): array
    {
        return $this->pdo->query('SELECT c.id,c.slug,c.name,c.description,c.active,COUNT(p.id) AS product_count,SUM(CASE WHEN p.active=1 THEN 1 ELSE 0 END) AS active_products FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id,c.slug,c.name,c.description,c.active ORDER BY c.active DESC,c.name')->fetchAll();
    }

    public function category(int $id): ?array
    {
        $query=$this->pdo->prepare('SELECT id,slug,name,description,active FROM categories WHERE id=? LIMIT 1');
        $query->execute([$id]); $row=$query->fetch(); return is_array($row)?$row:null;
    }

    public function setCategoryActive(int $id, bool $active): void
    {
        $this->transaction(function() use($id,$active): void {
            $lock=$this->pdo->prepare('SELECT id FROM categories WHERE id=? FOR UPDATE'); $lock->execute([$id]);
            if ($lock->fetchColumn()===false) throw new AdminException('La categoría no existe.',404);
            if (!$active) {
                $used=$this->pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id=? AND active=1'); $used->execute([$id]);
                if ((int)$used->fetchColumn()>0) throw new AdminException('No se puede desactivar: hay productos activos asociados.',409);
            }
            $query=$this->pdo->prepare('UPDATE categories SET active=? WHERE id=?'); $query->execute([$active?1:0,$id]);
        });
    }

    public function imageOptions(): array
    {
        $rows=$this->pdo->query('SELECT DISTINCT local_path,alt_text FROM product_images ORDER BY local_path')->fetchAll();
        $root=dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR;
        return array_values(array_filter($rows,static function(array $row) use ($root): bool {
            $path=(string)$row['local_path'];
            return preg_match('#^assets/images/products/[A-Za-z0-9._-]+\.(?:png|webp|jpe?g|svg)$#i',$path)===1 && is_file($root.str_replace('/',DIRECTORY_SEPARATOR,$path));
        }));
    }

    public function customers(string $search, int $page, int $perPage=15): array
    {
        $search=$this->shortText($search,100); $where="u.role='CUSTOMER'"; $params=[];
        if ($search!=='') { $where.=' AND (u.name LIKE ? OR u.email LIKE ?)'; $params[]=$search.'%'; $params[]=$search.'%'; }
        $count=$this->pdo->prepare('SELECT COUNT(*) FROM users u WHERE '.$where); $count->execute($params); $total=(int)$count->fetchColumn();
        [$page,$perPage,$pages]=$this->page($page,$perPage,$total);
        $query=$this->pdo->prepare('SELECT u.id,u.name,u.email,u.created_at,(SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) AS order_count FROM users u WHERE '.$where.' ORDER BY u.created_at DESC,u.id DESC LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage));
        $query->execute($params);
        return ['items'=>$query->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$pages,'query'=>$search];
    }

    public function orders(string $search, string $status, int $page, int $perPage=15, ?int $customerId=null): array
    {
        $search=$this->shortText($search,80); $where=['1=1']; $params=[];
        if (in_array($status,['PENDING_PAYMENT','PAID','CANCELLED','FULFILLED'],true)) { $where[]='o.status=?'; $params[]=$status; }
        if ($customerId!==null) { $where[]='o.user_id=?'; $params[]=$customerId; }
        if ($search!=='') { $where[]='(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; array_push($params,$search.'%',$search.'%',$search.'%'); }
        $clause=implode(' AND ',$where);
        $count=$this->pdo->prepare('SELECT COUNT(*) FROM orders o JOIN users u ON u.id=o.user_id WHERE '.$clause); $count->execute($params); $total=(int)$count->fetchColumn();
        [$page,$perPage,$pages]=$this->page($page,$perPage,$total);
        $query=$this->pdo->prepare('SELECT o.id,o.order_number,o.status,o.total_cents,o.created_at,o.confirmed_at,u.name AS customer_name,u.email AS customer_email,(SELECT p.status FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS payment_status,(SELECT p.fake_reference FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS fake_reference FROM orders o JOIN users u ON u.id=o.user_id WHERE '.$clause.' ORDER BY o.created_at DESC,o.id DESC LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage));
        $query->execute($params);
        return ['items'=>$query->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$pages,'query'=>$search,'status'=>$status,'customer_id'=>$customerId];
    }

    public function orderHistory(int $orderId): array
    {
        $query=$this->pdo->prepare('SELECT h.from_status,h.to_status,h.reason,h.changed_at,u.name AS admin_name FROM admin_order_status_history h JOIN users u ON u.id=h.admin_user_id WHERE h.order_id=? ORDER BY h.id DESC');
        $query->execute([$orderId]); return $query->fetchAll();
    }

    public function saveProduct(array $input): int
    {
        $id=$this->positiveInt($input['id']??null,'Identificador de producto no válido.',true);
        $sku=strtoupper(trim($this->inputText($input,'sku',30)));
        $name=trim($this->inputText($input,'name',160));
        $scientific=trim($this->inputText($input,'scientific_name',160,true));
        $description=trim($this->inputText($input,'description',5000,true));
        $light=trim($this->inputText($input,'light',100,true));
        $care=trim($this->inputText($input,'care_level',40,true));
        $slug=strtolower(trim($this->inputText($input,'slug',120)));
        $categoryId=$this->positiveInt($input['category_id']??null,'Selecciona una categoría.');
        $price=$this->moneyCents($input['price']??null,'Precio');
        $cost=$this->moneyCents($input['cost']??null,'Costo');
        $initialStock=$id===null?$this->nonnegativeInt($input['initial_stock']??'0','Inventario inicial'):null;
        $active=($input['active']??'')==='1'?1:0; $featured=($input['featured']??'')==='1'?1:0;
        $image=trim($this->inputText($input,'image',255));
        if (preg_match('/^[A-Z0-9][A-Z0-9-]{2,29}$/',$sku)!==1) throw new AdminException('El SKU debe tener entre 3 y 30 letras, números o guiones.');
        if ($name==='' || $this->length($name)>160) throw new AdminException('El nombre del producto es obligatorio y admite hasta 160 caracteres.');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)!==1) throw new AdminException('El enlace del producto no tiene un formato válido.');
        if ($care!=='' && !in_array($care,['Fácil','Media','Avanzada'],true)) throw new AdminException('El nivel de cuidado seleccionado no es válido.');
        if ($active===1 && !$this->categoryIsActive($categoryId)) throw new AdminException('Activa primero la categoría seleccionada.');
        $options=array_column($this->imageOptions(),'local_path');
        if (!in_array('assets/images/products/'.$image,$options,true)) throw new AdminException('Selecciona una imagen local disponible.');
        if ($id!==null && $this->product($id)===null) throw new AdminException('El producto ya no existe.',404);

        try {
            return $this->transaction(function() use($id,$sku,$name,$scientific,$description,$light,$care,$slug,$categoryId,$price,$cost,$active,$featured,$image,$initialStock): int {
                $categoryLock=$this->pdo->prepare('SELECT active FROM categories WHERE id=? FOR UPDATE'); $categoryLock->execute([$categoryId]); $categoryActive=$categoryLock->fetchColumn();
                if ($categoryActive===false) throw new AdminException('La categoría seleccionada ya no existe.',409);
                if ($active===1 && (int)$categoryActive!==1) throw new AdminException('Activa primero la categoría seleccionada.',409);
                $values=[$categoryId,$sku,$slug,$name,$scientific!==''?$scientific:null,$description,$price,$cost,$light!==''?$light:null,$care!==''?$care:null,$featured,$active];
                if ($id===null) {
                    $insert=$this->pdo->prepare('INSERT INTO products(category_id,sku,slug,name,scientific_name,description,price_cents,cost_cents,stock,light,care_level,featured,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $insert->execute([$categoryId,$sku,$slug,$name,$scientific!==''?$scientific:null,$description,$price,$cost,$initialStock,$light!==''?$light:null,$care!==''?$care:null,$featured,$active]);
                    $productId=(int)$this->pdo->lastInsertId();
                    if (($initialStock??0)>0) {
                        $movement=$this->pdo->prepare('INSERT INTO stock_movements(product_id,order_id,delta,reason,created_at) VALUES(?,NULL,?,\'stock inicial admin\',?)');
                        $movement->execute([$productId,$initialStock,date('Y-m-d H:i:s')]);
                    }
                } else {
                    $update=$this->pdo->prepare('UPDATE products SET category_id=?,sku=?,slug=?,name=?,scientific_name=?,description=?,price_cents=?,cost_cents=?,light=?,care_level=?,featured=?,active=? WHERE id=?');
                    $update->execute([...$values,$id]);
                    $productId=$id;
                }
                $path='assets/images/products/'.$image;
                $primary=$this->pdo->prepare('SELECT id FROM product_images WHERE product_id=? ORDER BY position_num,id LIMIT 1 FOR UPDATE'); $primary->execute([$productId]); $imageId=$primary->fetchColumn();
                if ($imageId===false) {
                    $saveImage=$this->pdo->prepare('INSERT INTO product_images(product_id,local_path,alt_text,position_num) VALUES(?,?,?,0)');
                    $saveImage->execute([$productId,$path,'Imagen local de '.$name]);
                } else {
                    $saveImage=$this->pdo->prepare('UPDATE product_images SET local_path=?,alt_text=? WHERE id=?');
                    $saveImage->execute([$path,'Imagen local de '.$name,(int)$imageId]);
                }
                return $productId;
            });
        } catch (AdminException $e) { throw $e; }
        catch (PDOException $e) { if ((string)$e->getCode()==='23000') throw new AdminException('El SKU o enlace ya pertenece a otro producto.',409); throw $e; }
    }

    public function saveCategory(array $input): int
    {
        $id=$this->positiveInt($input['id']??null,'Identificador de categoría no válido.',true);
        $name=trim($this->inputText($input,'name',100)); $description=trim($this->inputText($input,'description',2000,true));
        $slug=strtolower(trim($this->inputText($input,'slug',80)));
        $active=($input['active']??'')==='1'?1:0;
        if ($name==='' || $this->length($name)>100) throw new AdminException('El nombre de la categoría es obligatorio.');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)!==1) throw new AdminException('El enlace de categoría no es válido.');
        $duplicate=$this->pdo->prepare('SELECT id FROM categories WHERE name=? AND (? IS NULL OR id<>?) LIMIT 1'); $duplicate->execute([$name,$id,$id]);
        if ($duplicate->fetchColumn()!==false) throw new AdminException('Ya existe una categoría con ese nombre.',409);
        if ($id!==null && $this->category($id)===null) throw new AdminException('La categoría ya no existe.',404);
        if ($active===0 && $id!==null) {
            $used=$this->pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id=? AND active=1'); $used->execute([$id]);
            if ((int)$used->fetchColumn()>0) throw new AdminException('No se puede desactivar: hay productos activos asociados. Desactívalos o asígnalos a otra categoría primero.',409);
        }
        try {
            if ($id===null) { $query=$this->pdo->prepare('INSERT INTO categories(slug,name,description,active) VALUES(?,?,?,?)'); $query->execute([$slug,$name,$description,$active]); return (int)$this->pdo->lastInsertId(); }
            $query=$this->pdo->prepare('UPDATE categories SET slug=?,name=?,description=?,active=? WHERE id=?'); $query->execute([$slug,$name,$description,$active,$id]); return $id;
        } catch (PDOException $e) { if ((string)$e->getCode()==='23000') throw new AdminException('El nombre o enlace de categoría ya está en uso.',409); throw $e; }
    }

    public function adjustStock(int $productId, mixed $expectedValue, mixed $deltaValue, mixed $reasonValue): int
    {
        $expected=$this->nonnegativeInt($expectedValue,'Existencia esperada');
        $delta=$this->signedInt($deltaValue,-4294967295,4294967295,'Ajuste');
        $reason=trim(is_string($reasonValue)?$reasonValue:'');
        if ($delta===0) throw new AdminException('El ajuste debe cambiar al menos una unidad.');
        if ($reason==='' || $this->length($reason)>80) throw new AdminException('Indica el motivo del ajuste (máximo 80 caracteres).');
        return $this->transaction(function() use($productId,$expected,$delta,$reason): int {
            $lock=$this->pdo->prepare('SELECT stock FROM products WHERE id=? FOR UPDATE'); $lock->execute([$productId]); $value=$lock->fetchColumn();
            if ($value===false) throw new AdminException('El producto no existe.',404);
            if ((int)$value!==$expected) throw new AdminException('El inventario cambió mientras editabas. Recarga el listado y confirma el nuevo valor.',409);
            $next=$expected+$delta;
            if ($next<0 || $next>4294967295) throw new AdminException('El ajuste dejaría el inventario fuera del rango permitido.');
            $update=$this->pdo->prepare('UPDATE products SET stock=? WHERE id=? AND stock=?'); $update->execute([$next,$productId,$expected]);
            if ($update->rowCount()!==1) throw new AdminException('El inventario cambió durante el ajuste. Recarga e inténtalo de nuevo.',409);
            $movement=$this->pdo->prepare('INSERT INTO stock_movements(product_id,order_id,delta,reason,created_at) VALUES(?,NULL,?,?,?)'); $movement->execute([$productId,$delta,$reason,date('Y-m-d H:i:s')]);
            return $next;
        });
    }

    public function movements(int $productId, int $limit=30): array
    {
        $query=$this->pdo->prepare('SELECT delta,reason,created_at,order_id FROM stock_movements WHERE product_id=? ORDER BY created_at DESC,id DESC LIMIT '.max(1,min(50,$limit)));
        $query->execute([$productId]); return $query->fetchAll();
    }

    public function transitionOrder(string $orderNumber, string $toStatus, int $adminId, string $reason): void
    {
        if (!in_array($toStatus,['CANCELLED','FULFILLED'],true)) throw new AdminException('El estado solicitado no es válido.');
        $reason=trim($reason);
        if ($reason==='' || $this->length($reason)>255) throw new AdminException('Indica el motivo del cambio (máximo 255 caracteres).');
        $this->transaction(function() use($orderNumber,$toStatus,$adminId,$reason): void {
            $query=$this->pdo->prepare('SELECT id,status FROM orders WHERE order_number=? FOR UPDATE'); $query->execute([$orderNumber]); $order=$query->fetch();
            if (!is_array($order)) throw new AdminException('El pedido no existe.',404);
            $payment=$this->pdo->prepare('SELECT status FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1'); $payment->execute([(int)$order['id']]); $paymentStatus=$payment->fetchColumn();
            $from=(string)$order['status'];
            $allowed=($from==='PAID' && $toStatus==='FULFILLED' && $paymentStatus==='APPROVED')
                || ($from==='PENDING_PAYMENT' && $toStatus==='CANCELLED' && $paymentStatus!=='APPROVED');
            if (!$allowed) throw new AdminException('Transición no permitida. Solo se puede entregar un pedido pagado o cancelar un pedido aún no pagado.',409);
            $update=$this->pdo->prepare('UPDATE orders SET status=? WHERE id=? AND status=?'); $update->execute([$toStatus,(int)$order['id'],$from]);
            if ($update->rowCount()!==1) throw new AdminException('El pedido cambió durante la operación. Recarga e inténtalo de nuevo.',409);
            $history=$this->pdo->prepare('INSERT INTO admin_order_status_history(order_id,admin_user_id,from_status,to_status,reason,changed_at) VALUES(?,?,?,?,?,?)');
            $history->execute([(int)$order['id'],$adminId,$from,$toStatus,$reason,date('Y-m-d H:i:s')]);
        });
    }

    private function categoryIsActive(int $id): bool
    {
        $query=$this->pdo->prepare('SELECT active FROM categories WHERE id=? LIMIT 1'); $query->execute([$id]); return (int)$query->fetchColumn()===1;
    }

    private function transaction(callable $operation): mixed
    {
        $owns=!$this->pdo->inTransaction(); if ($owns) $this->pdo->beginTransaction();
        try { $result=$operation(); if ($owns) $this->pdo->commit(); return $result; }
        catch(Throwable $exception) { if ($owns && $this->pdo->inTransaction()) $this->pdo->rollBack(); throw $exception; }
    }

    private function page(int $page,int $perPage,int $total): array
    {
        $perPage=max(1,min(30,$perPage)); $pages=max(1,(int)ceil($total/$perPage)); $page=max(1,min($pages,$page)); return [$page,$perPage,$pages];
    }
    private function inputText(array $input,string $key,int $max,bool $optional=false): string
    {
        $value=$input[$key]??''; if (!is_string($value)) throw new AdminException('El campo '.$key.' no es válido.');
        if (preg_match('//u',$value)!==1) throw new AdminException('El campo '.$key.' contiene texto no válido.');
        if (!$optional && trim($value)==='') throw new AdminException('Completa el campo obligatorio: '.$key.'.');
        if (strlen($value)>$max*4) throw new AdminException('El campo '.$key.' es demasiado largo.'); return $value;
    }
    private function shortText(string $value,int $max): string
    {
        if (!preg_match('//u',$value)) return ''; return function_exists('mb_substr')?mb_substr(trim($value),0,$max,'UTF-8'):substr(trim($value),0,$max*4);
    }
    private function length(string $value): int { return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value); }
    private function positiveInt(mixed $value,string $message,bool $nullable=false): ?int
    {
        if ($nullable && ($value===null || $value==='')) return null;
        if (!is_string($value) && !is_int($value)) throw new AdminException($message);
        $text=(string)$value; if (preg_match('/^[1-9]\d{0,9}$/',$text)!==1 || (int)$text>4294967295) throw new AdminException($message); return (int)$text;
    }
    private function nonnegativeInt(mixed $value,string $label): int
    {
        if (!is_string($value) && !is_int($value)) throw new AdminException($label.' debe ser un entero válido.');
        $text=(string)$value; if (preg_match('/^(?:0|[1-9]\d{0,9})$/',$text)!==1 || (int)$text>4294967295) throw new AdminException($label.' debe ser un entero no negativo válido.'); return (int)$text;
    }
    private function signedInt(mixed $value,int $min,int $max,string $label): int
    {
        if (!is_string($value) && !is_int($value)) throw new AdminException($label.' debe ser entero.');
        $text=(string)$value; if (preg_match('/^-?(?:0|[1-9]\d{0,9})$/',$text)!==1 || (int)$text<$min || (int)$text>$max) throw new AdminException($label.' debe ser un entero dentro del rango permitido.'); return (int)$text;
    }
    private function moneyCents(mixed $value,string $label): int
    {
        if (!is_string($value) && !is_int($value)) throw new AdminException($label.' debe ser un importe válido.');
        $text=trim((string)$value); if (preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/',$text)!==1) throw new AdminException($label.' debe ser un importe positivo con hasta dos decimales.');
        [$whole,$fraction]=array_pad(explode('.',$text,2),2,''); $cents=((int)$whole*100)+(int)str_pad($fraction,2,'0');
        if ($cents>4294967295) throw new AdminException($label.' supera el límite de la base de datos.'); return $cents;
    }
}
