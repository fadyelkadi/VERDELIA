<?php
declare(strict_types=1);

final class ProductImageStorage
{
    private const MAX_BYTES = 5242880;
    private const MAX_PIXELS = 25000000;

    public function store(mixed $upload): ?string
    {
        if ($upload === null) return null;
        if (!is_array($upload) || !array_key_exists('error', $upload)) {
            throw new AdminException('No se pudo leer el archivo de imagen. Vuelve a seleccionarlo.');
        }

        $error = $upload['error'];
        if (!is_int($error) && !(is_string($error) && ctype_digit($error))) {
            throw new AdminException('El archivo de imagen no es válido.');
        }
        $error = (int)$error;
        if ($error === UPLOAD_ERR_NO_FILE) return null;
        if ($error !== UPLOAD_ERR_OK) {
            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el límite de carga permitido (5 MB).',
                UPLOAD_ERR_PARTIAL => 'La imagen se cargó incompleta. Inténtalo de nuevo.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar la imagen. Revisa los permisos de la carpeta de cargas.',
                default => 'No se pudo cargar la imagen. Vuelve a intentarlo.',
            };
            throw new AdminException($message);
        }

        $temporaryPath = $upload['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new AdminException('No se pudo validar la imagen seleccionada.');
        }
        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new AdminException('La imagen debe pesar entre 1 byte y 5 MB.');
        }

        $imageInfo = @getimagesize($temporaryPath);
        if (!is_array($imageInfo) || !isset($imageInfo[0], $imageInfo[1], $imageInfo['mime'])) {
            throw new AdminException('El archivo no contiene una imagen válida.');
        }
        $mime = strtolower((string)$imageInfo['mime']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new AdminException('Usa una imagen JPG, PNG o WebP.');
        }
        if (function_exists('finfo_open')) {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $fileInfo === false ? false : finfo_file($fileInfo, $temporaryPath);
            if ($fileInfo !== false) finfo_close($fileInfo);
            if (!is_string($detectedMime) || strtolower($detectedMime) !== $mime) {
                throw new AdminException('El contenido del archivo no coincide con su formato de imagen.');
            }
        }

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];
        if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000 || $width * $height > self::MAX_PIXELS) {
            throw new AdminException('La imagen tiene dimensiones demasiado grandes. Reduce su resolución e inténtalo de nuevo.');
        }

        $directory = $this->directory();
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new AdminException('No se pudo preparar la carpeta de imágenes. Revisa los permisos de public/uploads/products.');
        }
        if (!is_writable($directory)) {
            throw new AdminException('No se pueden guardar imágenes en public/uploads/products. Revisa los permisos de esa carpeta.');
        }

        $filename = bin2hex(random_bytes(16)).'.'.$allowed[$mime];
        $destination = $directory.DIRECTORY_SEPARATOR.$filename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new AdminException('No se pudo guardar la imagen. Revisa los permisos de la carpeta de cargas.');
        }
        return 'uploads/products/'.$filename;
    }

    public function deleteIfUnreferenced(string $path, AdminRepository $repository): void
    {
        if (!$this->isManagedPath($path)) return;
        try {
            if ($repository->imagePathReferenceCount($path) > 0) return;
        } catch (Throwable) {
            return;
        }
        $this->deleteManagedFile($path);
    }

    private function isManagedPath(string $path): bool
    {
        return preg_match('#^uploads/products/[a-f0-9]{32}\.(?:jpg|png|webp)$#D', $path) === 1;
    }

    private function deleteManagedFile(string $path): void
    {
        if (!$this->isManagedPath($path)) return;
        $directory = realpath($this->directory());
        $target = realpath($this->directory().DIRECTORY_SEPARATOR.basename($path));
        if ($directory === false || $target === false || dirname($target) !== $directory) return;
        @unlink($target);
    }

    private function directory(): string
    {
        return dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'products';
    }
}
