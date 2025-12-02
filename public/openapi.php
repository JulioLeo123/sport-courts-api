<?php
// Gera o documento OpenAPI 3.0 em JSON para o Swagger UI

header('Content-Type: application/json; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir    = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'); // ex.: /sport-courts-api/public
$base   = $scheme . '://' . $host . ($dir ?: '/');

// Ajuste o IP abaixo para seu IP da LAN se quiser mostrar no combo de Servers
$lanIp  = '192.168.1.15'; // troque se necessário
$servers = [
  ['url' => $base,                         'description' => 'Servidor local (auto)'],
  ['url' => $scheme.'://'.$lanIp.$dir,     'description' => 'LAN (acesso pelo celular)']
];

$components = [
  'securitySchemes' => [
    'bearerAuth' => [
      'type' => 'http',
      'scheme' => 'bearer',
      'bearerFormat' => 'JWT'
    ]
  ],
  'schemas' => [
    'ApiError' => [
      'type' => 'object',
      'properties' => [
        'code' => ['type' => 'string', 'example' => 'NOT_AVAILABLE'],
        'message' => ['type' => 'string', 'example' => 'Slot indisponível']
      ]
    ],
    'ApiEnvelope' => [
      'type' => 'object',
      'properties' => [
        'status' => ['type' => 'string', 'enum' => ['success','error']],
        'data'   => ['nullable' => true],
        'error'  => ['$ref' => '#/components/schemas/ApiError']
      ]
    ],
    'UserInput' => [
      'type' => 'object',
      'required' => ['name','email','password'],
      'properties' => [
        'name'     => ['type'=>'string','example'=>'João'],
        'email'    => ['type'=>'string','example'=>'joao@exemplo.com'],
        'password' => ['type'=>'string','example'=>'123456']
      ]
    ],
    'LoginInput' => [
      'type' => 'object',
      'required' => ['email','password'],
      'properties' => [
        'email'    => ['type'=>'string','example'=>'joao@exemplo.com'],
        'password' => ['type'=>'string','example'=>'123456']
      ]
    ],
    'LoginResponse' => [
      'allOf' => [
        ['$ref' => '#/components/schemas/ApiEnvelope'],
        [
          'type' => 'object',
          'properties' => [
            'data' => [
              'type' => 'object',
              'properties' => [
                'user' => [
                  'type' => 'object',
                  'properties' => [
                    'id'    => ['type'=>'integer','example'=>10],
                    'name'  => ['type'=>'string','example'=>'João'],
                    'email' => ['type'=>'string','example'=>'joao@exemplo.com'],
                    'role'  => ['type'=>'string','example'=>'user']
                  ]
                ],
                'token' => ['type'=>'string','example'=>'YmFzZTY0LXVpZDoxMjM0fDE3MDAwMDAwMDA=']
              ]
            ]
          ]
        ]
      ]
    ],
    'RegisterResponse' => [
      'allOf' => [
        ['$ref' => '#/components/schemas/ApiEnvelope'],
        [
          'type' => 'object',
          'properties' => [
            'data' => [
              'type' => 'object',
              'properties' => [
                'id' => ['type'=>'integer','example'=>123]
              ]
            ]
          ]
        ]
      ]
    ],
    'AvailabilitySlot' => [
      'type' => 'object',
      'properties' => [
        'court_id'   => ['type'=>'integer','example'=>1],
        'court_name' => ['type'=>'string','example'=>'Quadra 1'],
        'start'      => ['type'=>'string','example'=>'16:00:00'],
        'end'        => ['type'=>'string','example'=>'17:00:00'],
        'price'      => ['type'=>'string','example'=>'50.00']
      ]
    ],
    'CreateReservationInput' => [
      'type' => 'object',
      'required' => ['court_id','start_datetime','end_datetime'],
      'properties' => [
        'court_id'       => ['type'=>'integer','example'=>1],
        'start_datetime' => ['type'=>'string','example'=>'2025-12-07 16:00:00'],
        'end_datetime'   => ['type'=>'string','example'=>'2025-12-07 17:00:00']
      ]
    ],
    'CreateReservationResponse' => [
      'allOf' => [
        ['$ref' => '#/components/schemas/ApiEnvelope'],
        [
          'type' => 'object',
          'properties' => [
            'data' => [
              'type' => 'object',
              'properties' => [
                'id' => ['type'=>'integer','example'=>321]
              ]
            ]
          ]
        ]
      ]
    ],
    'ReservationItem' => [
      'type' => 'object',
      'properties' => [
        'id'             => ['type'=>'integer','example'=>321],
        'court_id'       => ['type'=>'integer','example'=>1],
        'start_datetime' => ['type'=>'string','example'=>'2025-12-07 16:00:00'],
        'end_datetime'   => ['type'=>'string','example'=>'2025-12-07 17:00:00'],
        'status'         => ['type'=>'string','example'=>'pending'],
        'total_price'    => ['type'=>'string','example'=>'50.00'],
        'court_name'     => ['type'=>'string','example'=>'Quadra 1'],
        'created_at'     => ['type'=>'string','example'=>'2025-12-01 10:00:00'],
        'updated_at'     => ['type'=>'string','example'=>'2025-12-01 10:00:00']
      ]
    ],
    'ReservationsListResponse' => [
      'allOf' => [
        ['$ref' => '#/components/schemas/ApiEnvelope'],
        [
          'type' => 'object',
          'properties' => [
            'data' => [
              'type' => 'array',
              'items' => ['$ref' => '#/components/schemas/ReservationItem']
            ]
          ]
        ]
      ]
    ],
    'CancelResponse' => [
      'allOf' => [
        ['$ref' => '#/components/schemas/ApiEnvelope'],
        [
          'type' => 'object',
          'properties' => [
            'data' => [
              'type' => 'object',
              'properties' => [
                'cancelled_id' => ['type'=>'integer','example'=>321]
              ]
            ]
          ]
        ]
      ]
    ]
  ]
];

$paths = [
  '/' => [
    'get' => [
      'summary' => 'Healthcheck',
      'responses' => [
        '200' => [
          'description' => 'OK',
          'content' => ['application/json' => ['schema' => ['type'=>'object']]]
        ]
      ]
    ]
  ],
  '/sports' => [
    'get' => [
      'summary' => 'Listar esportes/quadras',
      'responses' => [
        '200' => [
          'description' => 'Lista',
          'content' => [
            'application/json' => [
              'schema' => [
                'type' => 'array',
                'items' => [
                  'type' => 'object',
                  'properties' => [
                    'id' => ['type'=>'integer','example'=>1],
                    'name' => ['type'=>'string','example'=>'Quadra 1']
                  ]
                ]
              ]
            ]
          ]
        ]
      ]
    ]
  ],
  '/availability' => [
    'get' => [
      'summary' => 'Disponibilidade (por data)',
      'parameters' => [
        ['in'=>'query','name'=>'date','required'=>true, 'schema'=>['type'=>'string','example'=>'2025-12-07']],
        ['in'=>'query','name'=>'sport_id','required'=>false, 'schema'=>['type'=>'integer','example'=>1]]
      ],
      'responses' => [
        '200' => [
          'description' => 'Slots',
          'content' => [
            'application/json' => [
              'schema' => [
                'allOf' => [
                  ['$ref' => '#/components/schemas/ApiEnvelope'],
                  [
                    'type'=>'object',
                    'properties'=>[
                      'data'=>[
                        'type'=>'array',
                        'items'=>['$ref'=>'#/components/schemas/AvailabilitySlot']
                      ]
                    ]
                  ]
                ]
              ]
            ]
          ]
        ]
      ]
    ]
  ],
  '/auth/register' => [
    'post' => [
      'summary' => 'Registrar usuário',
      'requestBody' => [
        'required' => true,
        'content' => [
          'application/json' => ['schema' => ['$ref' => '#/components/schemas/UserInput']]
        ]
      ],
      'responses' => [
        '201' => [
          'description' => 'Criado',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RegisterResponse']]]
        ],
        '409' => [
          'description' => 'E-mail já existe',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiEnvelope']]]
        ]
      ]
    ]
  ],
  '/auth/login' => [
    'post' => [
      'summary' => 'Login',
      'requestBody' => [
        'required' => true,
        'content' => [
          'application/json' => ['schema' => ['$ref' => '#/components/schemas/LoginInput']]
        ]
      ],
      'responses' => [
        '200' => [
          'description' => 'Token',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/LoginResponse']]]
        ],
        '401' => [
          'description' => 'Credenciais inválidas',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiEnvelope']]]
        ]
      ]
    ]
  ],
  '/reservations' => [
    'get' => [
      'summary' => 'Listar reservas (use ?mine=1 para as minhas)',
      'security' => [['bearerAuth' => []]],
      'parameters' => [
        ['in'=>'query','name'=>'mine','required'=>false,'schema'=>['type'=>'integer','example'=>1]]
      ],
      'responses' => [
        '200' => [
          'description' => 'Lista de reservas',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ReservationsListResponse']]]
        ],
        '401' => [
          'description' => 'Não autorizado',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiEnvelope']]]
        ]
      ]
    ],
    'post' => [
      'summary' => 'Criar reserva',
      'security' => [['bearerAuth' => []]],
      'requestBody' => [
        'required' => true,
        'content' => [
          'application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateReservationInput']]
        ]
      ],
      'responses' => [
        '201' => [
          'description' => 'Reserva criada',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateReservationResponse']]]
        ],
        '401' => [
          'description' => 'Token ausente/ inválido',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiEnvelope']]]
        ],
        '422' => [
          'description' => 'Validação',
          'content' => [
            'application/json' => [
              'schema' => ['$ref' => '#/components/schemas/ApiEnvelope'],
              'examples' => [
                'validation' => ['value' => ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Campos obrigatórios']]]
              ]
            ]
          ]
        ],
        '409' => [
          'description' => 'Conflito ou indisponível',
          'content' => [
            'application/json' => [
              'schema' => ['$ref' => '#/components/schemas/ApiEnvelope'],
              'examples' => [
                'not_available' => ['value' => ['status'=>'error','error'=>['code'=>'NOT_AVAILABLE','message'=>'Slot indisponível']]],
                'conflict'      => ['value' => ['status'=>'error','error'=>['code'=>'CONFLICT','message'=>'Horário já reservado']]]
              ]
            ]
          ]
        ]
      ]
    ]
  ],
  '/reservations/{id}' => [
    'get' => [
      'summary' => 'Detalhar reserva',
      'security' => [['bearerAuth' => []]],
      'parameters' => [
        ['in'=>'path','name'=>'id','required'=>true,'schema'=>['type'=>'integer','example'=>321]]
      ],
      'responses' => [
        '200' => [
          'description' => 'Detalhe',
          'content' => ['application/json' => ['schema' => [
            'allOf' => [
              ['$ref' => '#/components/schemas/ApiEnvelope'],
              ['type'=>'object','properties'=>['data' => ['$ref' => '#/components/schemas/ReservationItem']]]
            ]
          ]]]
        ],
        '404' => [
          'description' => 'Não encontrada',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiEnvelope']]]
        ]
      ]
    ],
    'put' => [
      'summary' => 'Atualização completa da reserva',
      'security' => [['bearerAuth' => []]],
      'parameters' => [
        ['in'=>'path','name'=>'id','required'=>true,'schema'=>['type'=>'integer','example'=>321]]
      ],
      'requestBody' => [
        'required' => true,
        'content' => [
          'application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateReservationInput']]
        ]
      ],
      'responses' => [
        '200' => ['description' => 'Atualizado'],
        '422' => ['description' => 'Validação','content'=>['application/json'=>['schema'=>['$ref'=>'#/components/schemas/ApiEnvelope']]]],
        '409' => ['description' => 'Conflito','content'=>['application/json'=>['schema'=>['$ref'=>'#/components/schemas/ApiEnvelope']]]]
      ]
    ],
    'patch' => [
      'summary' => 'Atualização parcial',
      'security' => [['bearerAuth' => []]],
      'parameters' => [
        ['in'=>'path','name'=>'id','required'=>true,'schema'=>['type'=>'integer','example'=>321]]
      ],
      'requestBody' => [
        'required' => true,
        'content' => [
          'application/json' => ['schema' => ['type'=>'object','example'=>['status'=>'confirmed']]]
        ]
      ],
      'responses' => [
        '200' => ['description' => 'Atualizado']
      ]
    ],
    'delete' => [
      'summary' => 'Remover reserva',
      'security' => [['bearerAuth' => []]],
      'parameters' => [
        ['in'=>'path','name'=>'id','required'=>true,'schema'=>['type'=>'integer','example'=>321]]
      ],
      'responses' => [
        '204' => ['description' => 'Deletado']
      ]
    ]
  ],
  '/reservations/{id}/cancel' => [
    'put' => [
      'summary' => 'Cancelar reserva',
      'security' => [['bearerAuth' => []]],
      'parameters' => [
        ['in'=>'path','name'=>'id','required'=>true,'schema'=>['type'=>'integer','example'=>321]]
      ],
      'responses' => [
        '200' => [
          'description' => 'Cancelado',
          'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CancelResponse']]]
        ]
      ]
    ]
  ]
];

$openapi = [
  'openapi' => '3.0.3',
  'info' => [
    'title' => 'Sport Courts API',
    'version' => '1.0.0',
    'description' => 'API para reservas de quadras — spec gerado por openapi.php'
  ],
  'servers' => $servers,
  'components' => $components,
  'paths' => $paths
];

echo json_encode($openapi, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);