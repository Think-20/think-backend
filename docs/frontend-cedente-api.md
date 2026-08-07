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

No arquivo YAML, edite `servers[0].url` (ou sobrescreva no Swagger UI) para o host real da API (sem barra no final). Deve ser a **mesma origem** usada para `POST /login` e para as rotas de cedente.

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

### Fundo (`fund_id`) — **obrigatório**

Todo cedente pertence a um **fundo** (`cedente.fund_id` → tabela `fund`). O front **deve sempre enviar `fund_id`** — é campo obrigatório no contrato da API (mesmo critério dos schemas OpenAPI com `required: [fund_id]`). Sem `fund_id` a API responde **400** (`fund_id e obrigatorio`).

Além de existir, o fundo precisa ser **permitido** para o usuário logado (`fund_employee`):

| Situação do employee | Acesso |
|----------------------|--------|
| Tem um ou mais fundos em `fund_employee` | Só pode operar nesses fundos |
| **Não** tem nenhum fundo atrelado | Pode operar em **todos** os fundos |
| Fundo fora da lista permitida | HTTP 400: `Fundo nao permitido para este usuario` |

Isso vale para **todas** as rotas de cedente (CRUD, avaliação, validação de arquivo, listagem, histórico e **download ZIP**).

1. Selecionar ou criar o fundo (rotas `/funds/*`, `/fund/save`, etc.).
2. Enviar `fund_id` (inteiro ≥ 1, fundo existente **e permitido**) em **toda** requisição do fluxo de cedente:

| Operação | Rota | Onde enviar `fund_id` |
|----------|------|------------------------|
| Criar | `POST /cedente/save` | JSON (body) |
| Atualizar (snapshot) | `PUT /cedente/edit` | JSON (body) |
| Atualizar (parcial) | `PATCH /cedente/patch` | JSON (body), junto com `id` |
| Validar arquivo | `PATCH /cedente/arquivo/validacao` | JSON (body) |
| Avaliar cedente | `PATCH /cedente/avaliacao` | JSON (body) |
| Listar | `POST /cedentes/all` | JSON (body) |
| Resumo por status | `POST /cedentes/status-resumo` | JSON (body) |
| Detalhe | `GET /cedentes/get/{id}` | Query: `?fund_id=1` |
| Histórico | `GET /cedentes/historico/{id}` | Query: `?fund_id=1` |
| Download ZIP arquivos | `GET /cedentes/arquivos/download-all/{id}` | Query: `?fund_id=1` |
| Excluir | `DELETE /cedente/remove/{id}` | Query: `?fund_id=1` |

**Validação (HTTP 400):** `fund_id invalido` (valor inválido, por exemplo menor que 1) ou `Fundo nao encontrado` (id inexistente em `fund`). Consultas com `id` de cedente de **outro** fundo retornam **404**.

Listagens e `cadastro_status_resumo` consideram **apenas cedentes do fundo informado**.

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

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| POST | `/login` | Login (`email`, `password`) | — |
| GET | `/check-token` | Valida token | — |
| POST | `/logout` | Encerra sessão | — |
| POST | `/cedente/save` | Cria cedente (`fund_id` no JSON) | Preenchimento / Admin |
| PUT | `/cedente/edit` | Atualiza por `id` (snapshot) | Preenchimento* / Admin |
| PATCH | `/cedente/patch` | Atualização parcial | Preenchimento* / Admin |
| PATCH | `/cedente/arquivo/validacao` | Aprovar/recusar arquivo | Avalista / Admin |
| PATCH | `/cedente/avaliacao` | Aprovar / inconsistente / rejeitar | Avalista / Admin |
| GET | `/cedentes/get/{id}` | Detalhe | Todos os papéis |
| GET | `/cedentes/historico/{id}` | Histórico de status | Todos os papéis |
| GET | `/cedentes/arquivos/download-all/{id}` | ZIP dos arquivos ativos | Todos os papéis |
| POST | `/cedentes/all` | Lista paginada | Todos os papéis |
| POST | `/cedentes/status-resumo` | Contagens por status | Todos os papéis |
| DELETE | `/cedente/remove/{id}` | Exclui cedente | Admin |

\* Preenchimento só edita cedente em **`rascunho`** ou **`inconsistente`**.

Detalhes de schemas, códigos de resposta, `operationId` (útil para codegen) e exemplos estão no **YAML**.

---

## Papéis e regras de negócio

Employee autenticado pode ter um papel em `cedente_role`: `preenchimento`, `avalista` ou `administrador`.

### Preenchimento

- Pode criar e editar cadastro (formulário completo) em `rascunho` e `inconsistente`.
- Pode enviar status `rascunho` / `pendente` / `inconsistente` (com regras de transição).
- **Não** pode: excluir cedente, avaliar, aprovar/recusar arquivos.

### Avalista

- Pode **ver** cedente em qualquer status.
- **Não** edita formulário (`PUT`/`PATCH`/`POST save` bloqueados).
- Avalia via `PATCH /cedente/avaliacao` (exceto `rascunho`).
- Valida arquivos via `PATCH /cedente/arquivo/validacao` (exceto `rascunho`).
- Se o cadastro precisa de correção de dados: observação + `solicitar_correcoes` → status `inconsistente`; o preenchimento corrige os campos.

### Administrador

- Sem as restrições acima (CRUD + avaliação + validação de arquivos).

### Status automático

| Situação | Status |
|----------|--------|
| Cadastro incompleto | `rascunho` |
| Cadastro completo | `pendente` (+ SLA dias úteis) |
| Arquivo recusado pelo avalista/admin | `inconsistente` + soft delete do arquivo |
| Arquivo reenviado e sem inconsistências | volta para `pendente` |
| Avaliação `aprovado` | `aprovado` + `sla` = hoje + meses |
| Avaliação `solicitar_correcoes` | `inconsistente` (`observacao` opcional) |
| Avaliação `rejeitado` / `recusado` | `rejeitado` (`observacao` opcional) |

Validação **SERPRO** e **Vadu** automática após promoção a `pendente` está **desligada temporariamente** (procure `//Reativar validações Serpro e Vadu` no código).

### Arquivos

- **Nenhum arquivo é obrigatório** para o cedente sair de `rascunho` e ir para `pendente` — só os campos preenchíveis obrigatórios.
- Recusa → soft delete (some da lista `arquivos` na API; permanece no banco/histórico).
- Download: `GET /cedentes/arquivos/download-all/{id}?fund_id=` → ZIP só com arquivos ativos.

---

## Diferença entre GET e listagem (`POST /cedentes/all`)

- **GET `/cedentes/get/{id}`** e **`data` do save/edit**: objeto agregado com **`partes_relacionadas`** e **`avalistas`** (o mesmo formato que o front costuma enviar).
- **POST `/cedentes/all`**: resposta padrão de paginação do Laravel; cada item traz **`pessoas_vinculadas`** (modelo cru) e **`contas_desembolso`**, sem separar partes/avalistas em arrays distintos.

---

## Regras rápidas (negócio)

1. **`fund_id` obrigatório** em todas as rotas de cedente (body ou query conforme a tabela acima).
2. **Mesmo CPF** em `partes_relacionadas` e `avalistas` → o backend unifica em **um** registro com as duas flags.
3. **`tipo_conta`**: apenas `conta_corrente`, `conta_poupanca` ou `conta_salario`.
4. **`tipo_parte_relacionada`**: inteiro 1–4 ou `null` (ex.: só avalista).
5. No **PUT**, envie **snapshot completo** das três listas quando possível.
6. **Arquivos:** opcionais no **POST /cedente/save**, **PUT** e **PATCH** — **não** entram na regra de completude para `pendente`. Se enviados, use `document_type` 1–13, `original_name` e `content_base64`/`base64`. Gravação em `FILES_FOLDER/cedente-files`. No **PUT**, se enviar `arquivos`, substitui; se omitir, mantém. No **PATCH**, upsert parcial por `document_type`.
7. **Aprovar/recusar arquivo e avaliar cedente:** somente **avalista** ou **administrador**.

Exemplo de payload (incluindo itens opcionais de `arquivos`) em **`components.examples.CedenteCreateBody`** no `openapi-cedente.yaml`.
