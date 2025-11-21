<?php
namespace App;

class Auth
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function register(string $name, string $email, string $password): array
    {
        // Basic validation (keep simple; extend as needed)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Senha muito curta (mínimo 8 caracteres)');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)');
        $stmt->execute([$name, $email, $hash]);
        $id = (int)$this->pdo->lastInsertId();

        return ['id' => $id, 'name' => $name, 'email' => $email];
    }

    public function login(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) return null;
        if (!password_verify($password, $user['password_hash'])) return null;

        // generate token
        $token = bin2hex(random_bytes(32));
        $upd = $this->pdo->prepare('UPDATE users SET api_token = ? WHERE id = ?');
        $upd->execute([$token, $user['id']]);
        unset($user['password_hash']);
        $user['api_token'] = $token;
        return $user;
    }

    public function findUserByToken(?string $token): ?array
    {
        if (empty($token)) return null;
        $stmt = $this->pdo->prepare('SELECT id, name, email, role, api_token FROM users WHERE api_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}