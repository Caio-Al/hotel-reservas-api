<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_pagina_do_swagger_esta_disponivel(): void
    {
        $this->get('/docs')->assertOk()->assertSee('swagger-ui', false);
    }

    public function test_contrato_openapi_e_servido_e_declara_a_versao_3_0_0(): void
    {
        $response = $this->get('/docs/openapi.yaml')->assertOk();

        $this->assertStringContainsString('openapi: 3.0.0', file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    public function test_contrato_descreve_todas_as_rotas_da_api(): void
    {
        $spec = file_get_contents(base_path('docs/openapi.yaml'));

        foreach ([
            '/api/login', '/api/logout', '/api/me',
            '/api/rooms:', '/api/rooms/available:', '/api/rooms/{room}:',
            '/api/reserves:', '/api/reserves/{reserve}:', '/api/reserves/{reserve}/payments:',
        ] as $path) {
            $this->assertStringContainsString($path, $spec, "Rota não documentada: {$path}");
        }
    }
}
