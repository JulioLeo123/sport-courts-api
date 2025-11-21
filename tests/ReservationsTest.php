<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Database;

final class ReservationsTest extends TestCase
{
    private static ?\PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$pdo === null) {
            $db = new Database();
            self::$pdo = $db->getConnection();
        }
    }

    public function testCreateReservation(): void
    {
        // Precondition: there must be at least one court from seed
        $stmt = self::$pdo->query('SELECT id, slot_minutes, price_per_slot, open_time FROM courts LIMIT 1');
        $court = $stmt->fetch();
        $this->assertNotEmpty($court);

        // create or reuse a test user
        $email = 'testres+' . time() . '@example.com';
        $auth = new \App\Auth(self::$pdo);
        $user = $auth->register('Res Tester', $email, 'Password123!');
        $login = $auth->login($email, 'Password123!');
        $this->assertArrayHasKey('api_token', $login);

        // Choose a slot: today at open_time
        $date = date('Y-m-d');
        $start = $date . ' ' . $court['open_time'];
        $slot = (int)$court['slot_minutes'];
        $end = (new \DateTimeImmutable($start))->modify("+{$slot} minutes")->format('Y-m-d H:i:s');

        $service = new \App\Services\ReservationService(self::$pdo);
        $id = $service->createReservation((int)$user['id'], (int)$court['id'], $start, $end);
        $this->assertIsInt($id);
    }
}