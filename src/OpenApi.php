<?php
declare(strict_types=1);

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *   version="1.0.0",
 *   title="Sport Courts API",
 *   description="API para gestão de esportes, disponibilidade e reservas"
 * )
 * @OA\Server(
 *   url="http://localhost/sport-courts-api/public",
 *   description="Apache XAMPP"
 * )
 * @OA\Server(
 *   url="http://127.0.0.1:8080",
 *   description="Servidor embutido PHP"
 * )
 */

/**
 * @OA\Get(
 *   path="/sports",
 *   tags={"Sports"},
 *   summary="Listar esportes",
 *   @OA\Response(
 *     response=200,
 *     description="Lista de esportes",
 *     @OA\JsonContent(
 *       type="object",
 *       @OA\Property(property="status", type="string", example="success"),
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(
 *           type="object",
 *           @OA\Property(property="id", type="integer", example=1),
 *           @OA\Property(property="name", type="string", example="Futebol")
 *         )
 *       )
 *     )
 *   )
 * )
 */

/**
 * @OA\Get(
 *   path="/availability",
 *   tags={"Availability"},
 *   summary="Consultar disponibilidade",
 *   @OA\Parameter(name="date", in="query", required=false, @OA\Schema(type="string", example="2025-12-01")),
 *   @OA\Parameter(name="club_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
 *   @OA\Parameter(name="sport_id", in="query", required=false, @OA\Schema(type="integer", example=3)),
 *   @OA\Response(
 *     response=200,
 *     description="Slots disponíveis",
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="status", type="string", example="success"),
 *       @OA\Property(property="data", type="array",
 *         @OA\Items(type="object")
 *       )
 *     )
 *   )
 * )
 */

/**
 * @OA\Post(
 *   path="/auth/register",
 *   tags={"Auth"},
 *   summary="Registrar usuário",
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="name", type="string", example="John Doe"),
 *       @OA\Property(property="email", type="string", example="john@example.com"),
 *       @OA\Property(property="password", type="string", example="secret")
 *     )
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Registro efetuado",
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="status", type="string", example="success")
 *     )
 *   )
 * )
 *
 * @OA\Post(
 *   path="/auth/login",
 *   tags={"Auth"},
 *   summary="Login",
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="email", type="string", example="john@example.com"),
 *       @OA\Property(property="password", type="string", example="secret")
 *     )
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Login efetuado",
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="status", type="string", example="success"),
 *       @OA\Property(property="data", type="object",
 *         @OA\Property(property="token", type="string", example="BearerTokenExemplo")
 *       )
 *     )
 *   )
 * )
 */

/**
 * @OA\Post(
 *   path="/reservations",
 *   tags={"Reservations"},
 *   summary="Criar reserva",
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="user_id", type="integer", example=1),
 *       @OA\Property(property="court_id", type="integer", example=1),
 *       @OA\Property(property="start_datetime", type="string", example="2025-12-01 10:00:00"),
 *       @OA\Property(property="end_datetime", type="string", example="2025-12-01 11:00:00")
 *     )
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Reserva criada",
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="status", type="string", example="success"),
 *       @OA\Property(property="data", type="object",
 *         @OA\Property(property="id", type="integer", example=10)
 *       )
 *     )
 *   )
 * )
 *
 * @OA\Get(
 *   path="/reservations",
 *   tags={"Reservations"},
 *   summary="Listar reservas",
 *   @OA\Parameter(name="mine", in="query", required=false, @OA\Schema(type="string", example="true")),
 *   @OA\Response(
 *     response=200,
 *     description="Lista de reservas",
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="status", type="string", example="success"),
 *       @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *     )
 *   )
 * )
 *
 * @OA\Put(
 *   path="/reservations/{id}/cancel",
 *   tags={"Reservations"},
 *   summary="Cancelar reserva",
 *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
 *   @OA\Response(
 *     response=200,
 *     description="Cancelada",
 *     @OA\JsonContent(type="object",
 *       @OA\Property(property="status", type="string", example="success")
 *     )
 *   )
 * )
 */
class OpenApiBootstrap {}