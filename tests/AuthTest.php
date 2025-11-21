<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Database;

final class AuthTest extends TestCase
{
    private static ?\PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$pdo === null) {
            $db = new Database();
            self::$pdo = $db->getConnection();
        }
    }

    public function testRegisterAndLogin(): void
    {
        $email = 'test+' . time() . '@example.com';
        $name = 'Test User';
        $password = 'Password123!';

        // Register
        $auth = new \App\Auth(self::$pdo);
        $user = $auth->register($name, $email, $password);
        $this->assertArrayHasKey('id', $user);

        // Login
        $logged = $auth->login($email, $password);
        $this->assertIsArray($logged);
        $this->assertArrayHasKey('api_token', $logged);
    }
}