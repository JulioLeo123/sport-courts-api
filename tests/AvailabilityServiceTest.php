<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Services\AvailabilityService;

final class AvailabilityServiceTest extends TestCase
{
    private static ?\PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$pdo === null) {
            $db = new Database();
            self::$pdo = $db->getConnection();
        }
    }

    public function testAvailabilityReturnsArray(): void
    {
        $svc = new AvailabilityService(self::$pdo);
        $res = $svc->getAvailabilityForDate(date('Y-m-d'), null, null);
        $this->assertIsArray($res);
    }
}