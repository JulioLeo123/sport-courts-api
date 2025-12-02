<?php
class AuthMiddleware
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Valida Authorization: Bearer <token>
     * Retorna array com dados do usuário ou lança exceção 401.
     */
    public function requireBearerUser(): array
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$auth || stripos($auth, 'Bearer ') !== 0) {
            $this->unauthorized('AUTH_REQUIRED', 'Token ausente');
        }

        $token = substr($auth, 7); // remove "Bearer "
        if (!$token) {
            $this->unauthorized('AUTH_REQUIRED', 'Token ausente');
        }

        // Exemplo: token armazenado na coluna users.api_token
        $stmt = $this->pdo->prepare('SELECT id, name, email, role FROM users WHERE api_token = :t LIMIT 1');
        $stmt->execute([':t' => $token]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            $this->unauthorized('AUTH_INVALID', 'Token inválido');
        }

        return $user;
    }

    private function unauthorized(string $code, string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'error'  => ['code' => $code, 'message' => $message]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}