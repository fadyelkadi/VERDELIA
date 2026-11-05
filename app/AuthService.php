<?php
declare(strict_types=1);

final class AuthValidationException extends RuntimeException
{
    /** @var array<string, string> */
    public array $errors;

    /** @param array<string, string> $errors */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('La información enviada requiere correcciones.');
    }
}

final class AuthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array{id:int,name:string,email:string,role:string,created_at:string} */
    public function register(string $name, string $email, string $password, string $confirmation): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        $errors = self::validateName($name);
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Escribe un correo electrónico válido.';
        }
        $passwordError = self::passwordError($password);
        if ($passwordError !== null) $errors['password'] = $passwordError;
        if ($password !== $confirmation) $errors['confirmation'] = 'Las contraseñas no coinciden.';
        if ($errors !== []) throw new AuthValidationException($errors);

        $exists = $this->pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn() !== false) {
            throw new AuthValidationException(['email' => 'Ya existe una cuenta con ese correo.']);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) throw new RuntimeException('No se pudo proteger la contraseña.');
        try {
            // El rol es deliberadamente constante; no se acepta del formulario ni de la petición.
            $insert = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'CUSTOMER')");
            $insert->execute([$name, $email, $hash]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? '') === '23000') {
                throw new AuthValidationException(['email' => 'Ya existe una cuenta con ese correo.']);
            }
            throw $exception;
        }

        return $this->findById((int)$this->pdo->lastInsertId()) ?? throw new RuntimeException('No se pudo cargar la cuenta recién creada.');
    }

    /** @return array{id:int,name:string,email:string,role:string,created_at:string}|null */
    public function authenticate(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '' || strlen($password) > 72) return null;
        $query = $this->pdo->prepare('SELECT id, name, email, password_hash, role, created_at FROM users WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        $row = $query->fetch();
        if (!is_array($row) || !password_verify($password, (string)$row['password_hash'])) return null;
        unset($row['password_hash']);
        return self::publicUser($row);
    }

    /** @return array{id:int,name:string,email:string,role:string,created_at:string}|null */
    public function findById(int $id): ?array
    {
        if ($id < 1) return null;
        $query = $this->pdo->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1');
        $query->execute([$id]);
        $row = $query->fetch();
        return is_array($row) ? self::publicUser($row) : null;
    }

    public function updateName(int $id, string $name): string
    {
        $name = trim($name);
        $errors = self::validateName($name);
        if ($errors !== []) throw new AuthValidationException($errors);
        $query = $this->pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
        $query->execute([$name, $id]);
        return $name;
    }

    public function changePassword(int $id, string $current, string $password, string $confirmation): void
    {
        $errors = [];
        $passwordError = self::passwordError($password);
        if ($passwordError !== null) $errors['password'] = $passwordError;
        if ($password !== $confirmation) $errors['confirmation'] = 'Las contraseñas no coinciden.';
        if ($errors !== []) throw new AuthValidationException($errors);

        $query = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $query->execute([$id]);
        $hash = $query->fetchColumn();
        if (!is_string($hash) || !password_verify($current, $hash)) {
            throw new AuthValidationException(['current_password' => 'La contraseña actual no es correcta.']);
        }
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($newHash)) throw new RuntimeException('No se pudo proteger la contraseña.');
        $update = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([$newHash, $id]);
    }

    /** @return array<string, string> */
    private static function validateName(string $name): array
    {
        $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($length < 2 || $length > 120 || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
            return ['name' => 'El nombre debe tener entre 2 y 120 caracteres.'];
        }
        return [];
    }

    private static function passwordError(string $password): ?string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
        if ($length < 8) return 'Usa al menos 8 caracteres.';
        if (strlen($password) > 72) return 'La contraseña no puede superar 72 bytes.';
        return null;
    }

    /** @param array<string, mixed> $row
     *  @return array{id:int,name:string,email:string,role:string,created_at:string}
     */
    private static function publicUser(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'email' => (string)$row['email'],
            'role' => (string)$row['role'],
            'created_at' => (string)$row['created_at'],
        ];
    }
}
