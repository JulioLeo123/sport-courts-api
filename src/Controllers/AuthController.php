<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;

class AuthController
{
    public function __construct(private PDO $pdo) {}

    public function register(array $input): array
    {
        $name  = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $pass  = $input['password'] ?? '';

        if ($name === '' || $email === '' || $pass === '') {
            http_response_code(422);
            return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Campos obrigatórios']];
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, created_at, updated_at)
             VALUES (?, ?, ?, 'user', NOW(), NOW())"
        );
        try {
            $stmt->execute([$name, $email, $hash]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                http_response_code(409);
                return ['status'=>'error','error'=>['code'=>'DUPLICATE_EMAIL','message'=>'Email já existe']];
            }
            throw $e;
        }

        return ['status'=>'success','data'=>['id'=>(int)$this->pdo->lastInsertId()]];
    }

    public function login(array $input): array
    {
        $email = trim($input['email'] ?? '');
        $pass  = $input['password'] ?? '';

        if ($email === '' || $pass === '') {
            http_response_code(422);
            return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Email e senha são obrigatórios']];
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password_hash'])) {
            http_response_code(401);
            return ['status'=>'error','error'=>['code'=>'INVALID_CREDENTIALS','message'=>'Credenciais inválidas']];
        }

        $token = base64_encode('UID:' . $user['id'] . '|' . time());

        return [
            'status'=>'success',
            'data'=>[
                'user'=>[
                    'id'=>(int)$user['id'],
                    'name'=>$user['name'],
                    'email'=>$user['email'],
                    'role'=>$user['role']
                ],
                'token'=>$token
            ]
        ];
    }

    public static function userIdFromAuthHeader(?string $authHeader): ?int
    {
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) return null;
        $raw = base64_decode(substr($authHeader, 7), true);
        if (!$raw || !str_starts_with($raw, 'UID:')) return null;
        $parts = explode('|', substr($raw, 4));
        $id = (int)($parts[0] ?? 0);
        return $id > 0 ? $id : null;
    }
}