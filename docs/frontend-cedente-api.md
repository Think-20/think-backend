# API de cadastro de cedente

## Documentação OpenAPI (Swagger)

A especificação **OpenAPI 3.0** está em:

**[`openapi-cedente.yaml`](./openapi-cedente.yaml)**

### Swagger no navegador (recomendado para o cliente)

Com a aplicação rodando, abra:

**`/docs/cedente`**

Ex.: `https://seu-dominio/docs/cedente` ou `http://IP:8000/docs/cedente` — a página **Swagger UI** carrega o YAML servido em **`/docs/cedente/openapi.yaml`**.

Em ambiente sem rota pública (só VPN/rede interna), use a mesma URL dentro da rede permitida.

A especificação também pode ser importada em **Postman** (Import → OpenAPI), **Insomnia**, **Stoplight** e geradores de cliente.

### Ajustar a URL base

No arquivo YAML, edite a variável `servers[0].variables.baseUrl.default` (ou sobrescreva no Swagger UI) para o host real da API (sem barra no final). Deve ser a **mesma origem** usada para `POST /login` e para as rotas de cedente.

### Login (`POST /login`)

Autenticação antes das rotas de cedente (definida em `routes/web.php`, `UserController@login`):

- **Método:** `POST`
- **Caminho:** `/login` (não há prefixo `/api`)
- **Corpo:** JSON `application/json` **ou** `application/x-www-form-urlencoded` com:
  - `email` — e-mail do usuário
  - `password` — senha

**Resposta HTTP 200** em todos os casos:

- **Sucesso:** JSON com `token` (string) e `user` (objeto do usuário, com permissões/telas carregadas pelo backend).
- **Falha (credenciais inválidas):** `token` e `user` vêm **`null`** (o controller não devolve 401).

### Fundo (`fund_id`)

Todo cedente pertence a um **fundo** (`cedente.fund_id` → tabela `fund`). O front deve:

1. Selecionar ou criar o fundo (rotas `/funds/*`, `/fund/save`, etc.).
2. Enviar **`fund_id` em todas as operações de cedente**:
   - **Cadastro/edição:** `fund_id` no JSON (`POST /cedente/save`, `PUT /cedente/edit`, `PATCH /cedente/patch`).
   - **Listagem/resumo:** `fund_id` no corpo do `POST /cedentes/all` e `POST /cedentes/status-resumo`.
   - **Consulta/exclusão:** `fund_id` na query (`GET /cedentes/get/{id}?fund_id=1`) ou no corpo.

Listagens e `cadastro_status_resumo` consideram **apenas cedentes daquele fundo**.

### Autenticação nas demais rotas

O middleware `auth.api` exige **dois** headers em cada requisição protegida (incluindo cedente):

| Header | Valor |
|--------|--------|
| `User` | ID numérico do usuário (`user.id` retornado no login) |
| `Authorization` | Token **exatamente** como retornado no campo `token` do login — **sem** prefixo `Bearer ` |

Alternativa aceita pelo middleware: `user_id` e `access_token` no corpo da requisição; o padrão documentado no OpenAPI e no Swagger é usar os headers.

No **Swagger UI**, use **Authorize** e preencha os dois esquemas (`userIdHeader` e `authTokenHeader`) após o login.

### Rotas auxiliares (também no OpenAPI)

| Método | Rota | Uso |
|--------|------|-----|
| GET | `/check-token` | Confere token; resposta `{ "status": true \| false }` |
| POST | `/logout` | Invalida o token no servidor (mesmos headers `User` e `Authorization`) |

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

### Outras formas de abrir o Swagger

- **Integrado:** `/docs/cedente` neste projeto (preferencial).
- **Externo:** [Swagger Editor](https://editor.swagger.io/) — cole ou faça upload do `openapi-cedente.yaml`.
- **Docker** (YAML local como volume):

```bash
docker run --rm -p 8080:8080 -e SWAGGER_JSON=/docs/openapi-cedente.yaml \
  -v "$(pwd)/docs:/docs" swaggerapi/swagger-ui
```

---

## Rotas (resumo)

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/login` | Login (`email`, `password`); ver OpenAPI tag **Autenticação** |
| GET | `/check-token` | Valida token (headers `User` + `Authorization`) |
| POST | `/logout` | Encerra sessão |
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

O exemplo completo (incluindo os **13** itens de `arquivos` com base64 mínimo de teste) está em **`components.examples.CedenteCreateBody`** no `openapi-cedente.yaml`; troque `content_base64` pelos PDFs reais em produção.
