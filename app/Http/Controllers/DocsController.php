<?php

namespace App\Http\Controllers;

class DocsController extends Controller
{
    /**
     * Serve a especificação OpenAPI da API de cedente (Swagger / clientes).
     */
    public function openapiCedenteYaml()
    {
        $path = base_path('docs/openapi-cedente.yaml');

        return response()->file($path, [
            'Content-Type' => 'text/yaml; charset=UTF-8',
        ]);
    }

    /**
     * Página Swagger UI apontando para o YAML acima.
     */
    public function swaggerCedenteUi()
    {
        return view('swagger-cedente');
    }
}
