<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "API Documentation for Zhikgold Project",
    title: "Zhikgold Project API",
    contact: new OA\Contact(
        name: "Contact Technical Support",
        url: "tel:09124848784",
    ),
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "Main API Server"
)]
abstract class OpenApi
{
}
