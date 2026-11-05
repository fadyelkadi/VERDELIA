<?php
declare(strict_types=1);

final class AdminController
{
    public function __construct(private AdminRepository $repository) {}

    public function handle(string $method,string $path,int $adminId): void
    {
        if ($method!=='POST') { http_response_code(405); header('Allow: GET, POST'); exit('Método no permitido.'); }
        if (!verdelia_csrf_valid($_POST['csrf_token']??null)) {
            http_response_code(419); header('Cache-Control: no-store'); exit('La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.');
        }
        $target='/admin'; $success='Cambios guardados.';
        try {
            if ($path==='/admin/productos/guardar') {
                $id=$this->repository->saveProduct($_POST);
                $target='/admin/productos/'.$id.'/editar'; $success='Producto guardado. El catálogo ya usa los datos actualizados.';
            } elseif (preg_match('#^/admin/productos/(\d+)/estado$#',$path,$match)===1) {
                $active=($_POST['active']??'')==='1'; $this->repository->setProductActive((int)$match[1],$active); $target='/admin/productos'; $success=$active?'Producto activado.':'Producto desactivado sin borrar su historial.';
            } elseif ($path==='/admin/categorias/guardar') {
                $this->repository->saveCategory($_POST); $target='/admin/categorias'; $success='Categoría guardada.';
            } elseif (preg_match('#^/admin/categorias/(\d+)/estado$#',$path,$match)===1) {
                $active=($_POST['active']??'')==='1'; $this->repository->setCategoryActive((int)$match[1],$active); $target='/admin/categorias'; $success=$active?'Categoría activada.':'Categoría desactivada.';
            } elseif ($path==='/admin/inventario/ajustar') {
                $id=$this->repository->positiveId($_POST['product_id']??null); $sku=is_string($_POST['sku']??null)?$_POST['sku']:'';
                $this->repository->adjustStock($id,$_POST['expected_stock']??null,$_POST['delta']??null,$_POST['reason']??null);
                $target='/admin/inventario?q='.rawurlencode($sku); $success='Inventario actualizado y movimiento registrado.';
            } elseif (preg_match('#^/admin/pedidos/([A-Z0-9-]+)/estado$#',$path,$match)===1) {
                $status=is_string($_POST['status']??null)?$_POST['status']:''; $reason=is_string($_POST['reason']??null)?$_POST['reason']:'';
                $this->repository->transitionOrder($match[1],$status,$adminId,$reason); $target='/admin/pedidos/'.$match[1]; $success='Estado del pedido actualizado y cambio auditado.';
            } else {
                http_response_code(404); exit('Operación administrativa no encontrada.');
            }
            verdelia_set_flash(['type'=>'success','message'=>$success]);
        } catch (AdminException $exception) {
            verdelia_set_flash(['type'=>'error','message'=>$exception->getMessage()]);
            if (str_starts_with($path,'/admin/productos/')) $target='/admin/productos';
            elseif (str_starts_with($path,'/admin/categorias/')) $target='/admin/categorias';
            elseif (str_starts_with($path,'/admin/pedidos/')) $target='/admin/pedidos';
            elseif (str_starts_with($path,'/admin/inventario/')) $target='/admin/inventario';
        }
        header('Location: '.$target,true,303); exit;
    }
}
