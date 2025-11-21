<?php
namespace App\Controllers;

use App\Repositories\SportsRepository;

class SportsController
{
    private \PDO $pdo;
    private SportsRepository $repo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->repo = new SportsRepository($pdo);
    }

    public function index(): array
    {
        return $this->repo->all();
    }
}