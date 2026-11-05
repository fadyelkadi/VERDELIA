<?php
declare(strict_types=1);

final class CatalogRepository
{
    public function __construct(private PDO $pdo) {}

    public function categories(): array
    {
        return $this->pdo->query('SELECT c.id,c.slug,c.name,c.description,COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.active=1 WHERE c.active=1 GROUP BY c.id,c.slug,c.name,c.description ORDER BY c.id')->fetchAll();
    }

    public function categoryBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT id,slug,name,description FROM categories WHERE slug=:slug AND active=1 LIMIT 1');
        $statement->execute(['slug'=>$slug]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function listing(array $filters, int $page, int $perPage = 8): array
    {
        $where = ['p.active=1','c.active=1'];
        $params = [];
        if ($filters['category'] !== null) { $where[]='c.slug=:category'; $params['category']=$filters['category']; }
        if ($filters['query'] !== '') {
            $where[]='(p.name LIKE :query_name OR p.sku LIKE :query_sku OR p.scientific_name LIKE :query_scientific OR p.description LIKE :query_description)';
            $needle='%'.$filters['query'].'%';
            $params['query_name']=$needle; $params['query_sku']=$needle; $params['query_scientific']=$needle; $params['query_description']=$needle;
        }
        if ($filters['min_price'] !== null) { $where[]='p.price_cents>=:min_price'; $params['min_price']=$filters['min_price']; }
        if ($filters['max_price'] !== null) { $where[]='p.price_cents<=:max_price'; $params['max_price']=$filters['max_price']; }
        if ($filters['in_stock']) $where[]='p.stock>0';
        if ($filters['care'] !== []) {
            $slots=[];
            foreach ($filters['care'] as $i=>$value) { $key='care_'.$i; $slots[]=':'.$key; $params[$key]=$value; }
            $where[]='p.care_level IN ('.implode(',', $slots).')';
        }
        if ($filters['light'] !== []) {
            $slots=[];
            foreach ($filters['light'] as $i=>$value) { $key='light_'.$i; $slots[]='p.light LIKE :'.$key; $params[$key]='%'.$value.'%'; }
            $where[]='('.implode(' OR ', $slots).')';
        }
        $from=' FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN product_images pi ON pi.id=(SELECT pi2.id FROM product_images pi2 WHERE pi2.product_id=p.id ORDER BY pi2.position_num,pi2.id LIMIT 1) WHERE '.implode(' AND ',$where);
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$from);
        $count->execute($params);
        $total=(int)$count->fetchColumn();
        $totalPages=max(1,(int)ceil($total/$perPage));
        $page=min(max(1,$page),$totalPages);
        $sorts=['featured'=>'p.featured DESC,p.name ASC','price-asc'=>'p.price_cents ASC,p.id ASC','price-desc'=>'p.price_cents DESC,p.id ASC','name'=>'p.name ASC,p.id ASC','recent'=>'p.created_at DESC,p.id DESC'];
        $orderBy=$sorts[$filters['sort']]??$sorts['featured'];
        $sql='SELECT p.id,p.sku,p.slug,p.name,p.scientific_name,p.description,p.price_cents,p.stock,p.light,p.care_level,p.featured,p.created_at,c.slug AS category,c.name AS category_name,pi.local_path AS image_path,pi.alt_text AS image_alt'.$from.' ORDER BY '.$orderBy.' LIMIT :limit OFFSET :offset';
        $statement=$this->pdo->prepare($sql);
        foreach ($params as $key=>$value) $statement->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $statement->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);
        $statement->execute();
        return ['items'=>array_map([$this,'normalizeProduct'],$statement->fetchAll()),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>$totalPages];
    }

    public function featured(int $limit=4): array
    {
        $statement=$this->pdo->prepare($this->productSelect().' WHERE p.active=1 AND c.active=1 AND p.featured=1 ORDER BY p.name LIMIT :limit');
        $statement->bindValue(':limit',max(1,min(12,$limit)),PDO::PARAM_INT);
        $statement->execute();
        return array_map([$this,'normalizeProduct'],$statement->fetchAll());
    }

    public function bySlug(string $slug): ?array
    {
        $statement=$this->pdo->prepare($this->productSelect().' WHERE p.slug=:slug AND p.active=1 AND c.active=1 LIMIT 1');
        $statement->execute(['slug'=>$slug]);
        $row=$statement->fetch();
        return $row?$this->normalizeProduct($row):null;
    }

    public function related(string $category,int $excludeId,int $limit=3): array
    {
        $statement=$this->pdo->prepare($this->productSelect().' WHERE p.active=1 AND c.active=1 AND c.slug=:category AND p.id<>:exclude_id ORDER BY p.featured DESC,p.name LIMIT :limit');
        $statement->bindValue(':category',$category,PDO::PARAM_STR);
        $statement->bindValue(':exclude_id',$excludeId,PDO::PARAM_INT);
        $statement->bindValue(':limit',max(1,min(6,$limit)),PDO::PARAM_INT);
        $statement->execute();
        return array_map([$this,'normalizeProduct'],$statement->fetchAll());
    }

    public function bySkus(array $skus): array
    {
        $skus=array_values(array_unique(array_filter($skus,static fn($sku):bool=>is_string($sku)&&preg_match('/^[A-Z]{3}-\d{3}$/',$sku)===1)));
        if ($skus===[]) return [];
        $slots=implode(',',array_fill(0,count($skus),'?'));
        $statement=$this->pdo->prepare($this->productSelect()." WHERE p.active=1 AND c.active=1 AND p.sku IN ($slots)");
        $statement->execute($skus);
        return array_map([$this,'normalizeProduct'],$statement->fetchAll());
    }

    private function productSelect(): string
    {
        return 'SELECT p.id,p.sku,p.slug,p.name,p.scientific_name,p.description,p.price_cents,p.stock,p.light,p.care_level,p.featured,p.created_at,c.slug AS category,c.name AS category_name,pi.local_path AS image_path,pi.alt_text AS image_alt FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN product_images pi ON pi.id=(SELECT pi2.id FROM product_images pi2 WHERE pi2.product_id=p.id ORDER BY pi2.position_num,pi2.id LIMIT 1)';
    }

    private function normalizeProduct(array $row): array
    {
        $path=(string)($row['image_path']??'');
        $row['image']=preg_match('#^assets/images/products/([A-Za-z0-9._-]+\.(?:png|webp|jpg|jpeg|svg))$#i',$path,$match)===1?$match[1]:'product-placeholder.svg';
        $row['image_alt']=(string)($row['image_alt']??'')?:('Imagen ilustrativa de '.$row['name']);
        $row['price']=(int)$row['price_cents'];
        $row['care']=(string)($row['care_level']??'');
        $row['available']=(int)$row['stock']>0;
        return $row;
    }
}
