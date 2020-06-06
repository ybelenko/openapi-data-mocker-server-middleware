<?php

// OAS3.0 definition of GET /mocker operation responses
return [
    'default' => [
        'description' => 'Success Response',
        'content' => [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status_code' => [
                            'type' => 'integer'
                        ],
                        'message' => [
                            'type' => 'string'
                        ],
                    ],
                ],
            ],
        ],
    ],
];
