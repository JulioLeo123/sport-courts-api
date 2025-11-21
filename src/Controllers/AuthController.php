<?php
namespace App\Controllers;

use App\Auth;

class AuthController
{
    private \PDO $pdo;
    private Auth $auth;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auth = new Auth($pdo);
    }

    public function register(array $input): array
    {
        if (empty($input['name']) || empty($input['email']) || empty($input['password'])) {
            http_response_code(422);
            return ['error' => 'Missing fields'];
        }
        try {
            $user = $this->auth->register($input['name'], $input['email'], $input['password']);
            http_response_code(201);
            return $user;
        } catch (\InvalidArgumentException $ex) {
            http_response_code(422);
            return ['error' => $ex->getMessage()];
        }
    }

    public function login(array $input): array
    {
        if (empty($input['email']) || empty($input['password'])) {
            http_response_code(422);
            return ['error' => 'Missing fields'];
        }
        $user = $this->auth->login($input['email'], $input['password']);
        if (!$user) {
            http_response_code(401);
            return ['error' => 'Invalid credentials'];
        }
        return $user;
    }
}