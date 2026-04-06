# API de cadastro de cedente

## Documentação OpenAPI (Swagger)

A especificação **OpenAPI 3.0** está em:

**[`openapi-cedente.yaml`](./openapi-cedente.yaml)**

Ela pode ser importada em **Swagger UI**, **Postman** (Import → OpenAPI), **Insomnia**, **Stoplight** e em geradores de cliente/servidor.

### Ajustar a URL base

No arquivo YAML, edite a variável `servers[0].variables.baseUrl.default` (ou sobrescreva no gerador) para o host real da API (sem barra no final).

### Autenticação

Todas as operações usam o security scheme **`bearerAuth`** (header `Authorization: Bearer <token>`), alinhado ao middleware `auth.api` + `permission` do `routes/web.php`.

### Gerar cliente (exemplos)

Com [OpenAPI Generator](https://openapi-generator.tech/) (Docker):

```bash
docker run --rm -v "${PWD}:/local" openapitools/openapi-generator-cli generate \
  -i /local/docs/openapi-cedente.yaml \
  -g typescript-axios \
  -o /local/generated/cedente-client
```

Substitua `typescript-axios` pelo gerador desejado (`java`, `csharp`, `php`, `python`, etc.).

Com **Swagger Codegen** (legado):

```bash
swagger-codegen generate -i docs/openapi-cedente.yaml -l java -o ./generated/cedente-client
```

### Visualizar no navegador (Swagger UI)

1. Acesse [https://petstore.swagger.io/](https://petstore.swagger.io/) ou uma instância local do Swagger UI.
2. **File → Import URL** não serve para arquivo local; use **Upload** do `openapi-cedente.yaml` ou sirva o arquivo por HTTP.

Ou com Docker:

```bash
docker run --rm -p 8080:8080 -e SWAGGER_JSON=/docs/openapi-cedente.yaml \
  -v "$(pwd)/docs:/docs" swaggerapi/swagger-ui
```

(Ajuste o caminho da env conforme a imagem; alternativa simples: copiar o conteúdo do YAML para [editor.swagger.io](https://editor.swagger.io/).)

---

## Rotas (resumo)

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/cedente/save` | Cria cedente completo (um JSON) |
| PUT | `/cedente/edit` | Atualiza por `id`; listas substituem as anteriores |
| GET | `/cedentes/get/{id}` | Detalhe com `partes_relacionadas` e `avalistas` separados |
| POST | `/cedentes/all` | Lista paginada (`per_page` opcional) |
| DELETE | `/cedente/remove/{id}` | Exclui cedente e dependências |

Detalhes de schemas, códigos de resposta, `operationId` (útil para codegen) e exemplo completo de body estão no **YAML**.

---

## Diferença entre GET e listagem (`POST /cedentes/all`)

- **GET `/cedentes/get/{id}`** e **`data` do save/edit**: objeto agregado com **`partes_relacionadas`** e **`avalistas`** (o mesmo formato que o front costuma enviar).
- **POST `/cedentes/all`**: resposta padrão de paginação do Laravel; cada item traz **`pessoas_vinculadas`** (modelo cru) e **`contas_desembolso`**, sem separar partes/avalistas em arrays distintos.

---

## Regras rápidas (negócio)

1. **Mesmo CPF** em `partes_relacionadas` e `avalistas` → o backend unifica em **um** registro com as duas flags.
2. **`tipo_conta`**: apenas `conta_corrente`, `conta_poupanca` ou `conta_salario`.
3. **`tipo_parte_relacionada`**: inteiro 1–4 ou `null` (ex.: só avalista).
4. No **PUT**, envie **snapshot completo** das três listas quando possível.
5. **Arquivos:** no **POST /cedente/save** é obrigatório o array **`arquivos`** com **13** objetos (`document_type` 1–13, `original_name`, `content_base64` ou `base64`). Gravação em `FILES_FOLDER/cedente-files`. No **PUT**, se enviar `arquivos`, devem ser os 13 de novo (substitui); se omitir, mantém os já salvos.

O exemplo JSON que existia neste documento foi incorporado em **`components.examples.CedenteCreateBody`** dentro do `openapi-cedente.yaml` (atualize esse exemplo com `arquivos` ao testar cadastro completo).
