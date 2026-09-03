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
  - Inclui **`cedente_role`**: `{ id, code, name }` se o employee tiver papel em `cedente_role_employee`; **`null`** se não tiver (o front deve tratar como **admin** do fluxo de cedente).
  - O mesmo objeto também vem em `user.employee.cedente_role`.
  - `code` possível: `preenchimento` | `avalista` | `administrador`.
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
| Importar XML em lote | `POST /cedente/import/xml` | Form/query (`fund_id`) + XML (multipart, JSON ou corpo raw) |
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
| GET | `/cedentes/roles` | Lista funções (`id`, `code`, `name`) | **Admin** (id 3) |
| POST | `/cedente/employee/save` | Cria usuário do módulo (email, senha, função, fundos) | **Admin** (id 3) |
| PUT | `/cedente/employee/edit` | Altera usuário do módulo (nome, email, senha, função, fundos) | **Admin** (id 3) |
| POST | `/cedente/save` | Cria cedente (`fund_id` no JSON) | Preenchimento / Admin |
| POST | `/cedente/import/xml` | Importa cedentes a partir de XML Daycoval/Fromtis | Preenchimento / Admin |
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

### Importação em lote via XML (`POST /cedente/import/xml`)

Recebe o XML no formato **`cadastroCedente` → `cedentes` → `cedente[]`** (Daycoval/Fromtis) e cria um cedente por nó `<cedente>`, reutilizando a mesma lógica de `POST /cedente/save`.

**Entrada**

| Campo | Onde | Obrigatório |
|-------|------|-------------|
| `fund_id` | form, JSON ou query | Sim |
| XML | multipart (`xml` ou `file`), JSON (`xml`) ou corpo `application/xml` | Sim |

**Conferência de fundo:** se o XML tiver `fundo/cnpjFundo`, o backend compara com o CNPJ do `fund_id` selecionado na tela. Divergência → erro naquele item.

**Mapeamento XML → API interna**

| XML (`cedente/...`) | Campo interno |
|---------------------|---------------|
| `dadosContato/contato/nomeContato` (fallback `nome`) | `nome` |
| `cnpjCpf` | `documento` |
| `email`, `dadosContato/contato/emailContato` | `email` |
| `telefone`, `dadosContato/contato/telContato` | `telefone` |
| `faturamentoAnual` | `faturamento_anual` |
| `minAprovacao` | `minimo_assinantes` |
| `endereco`, `numEndereco`, `compEndereco`, `cep`, `bairro`, `uf`, `cidade` | `endereco.*` (`logradouro` ← `endereco`, `numero` ← `numEndereco`, `estado` ← `uf`) |
| `partesRelacionadas/parteRelacionada` | `partes_relacionadas[]` (`nome` ← `nomeParteRelacionada`, `cpf` ← `cnpjCpfParteRelacionada`) |
| `representantes/representante` | fallback de partes se não houver `partesRelacionadas` |
| `avalistas/avalista` | `avalistas[]` |
| `contasCorrente/contaCorrente` | `contas_desembolso[]` (`tipo_conta: conta_corrente`, `codigo_banco` ← `banco`, `numero_conta` ← `contaCorrente`) |
| `padrao` (conta) | ignorado |

**Resposta (HTTP 200):** `{ error, message, data: { fund_id, total, created, failed, results[] } }`. Cada item em `results` traz `success`, `documento`, `nome` e, se ok, `cedente_id` + objeto completo; se falhou, `message`. Importação parcial retorna `error: "true"` com `created > 0`.

**Exemplo multipart (curl):**

```bash
curl -X POST 'http://localhost:8000/cedente/import/xml' \
  -H 'User: 1' -H 'Authorization: SEU_TOKEN' \
  -F 'fund_id=1' \
  -F 'xml=@cadastro-cedente.xml'
```

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
- **Somente o papel id `3`** (não o nome `Administrador`) pode cadastrar e alterar usuários do módulo (`POST /cedente/employee/save`, `PUT /cedente/employee/edit` e `GET /cedentes/roles`).

### Cadastro e edição de usuário do módulo (do zero)

Este fluxo **não** usa o `POST /employee/save` legado nem gera e-mail `@thinkideias.com.br` / `@carmel`. O admin cadastra a pessoa **neste endpoint**, com e-mail e senha escolhidos, função e fundos — sem chamado interno.

**Quem pode chamar:** `cedente_role.id === 3`. Outro papel → HTTP **403**.

| Método | Rota | Uso |
|--------|------|-----|
| POST | `/cedente/employee/save` | Criar employee + user + função + fundos |
| PUT | `/cedente/employee/edit` | Alterar nome, e-mail, senha, função e/ou fundos |
| GET | `/cedentes/roles` | Combo de funções (`id`, `code`, `name`) |

**Campos do POST (criar) — todos no JSON:**

| Campo | Obrigatório | Descrição |
|-------|-------------|-----------|
| `name` | sim | Nome do employee |
| `email` | sim | E-mail de login (qualquer domínio válido; **não** é gerado) |
| `password` | sim | Senha de login (mínimo 6 caracteres). Aceita alias `senha` |
| `cedente_role_id` | sim | **Id** da função: 1 preenchimento, 2 avalista/aprovador, 3 administrador |
| `fund_ids` / `all_funds` | não | Ver tabela de fundos abaixo. Omitir = todos os fundos |
| `department` / `position` | não | Só se o front enviar |

**Campos do PUT (editar):** `id` obrigatório. Os demais só atualizam se enviados. Fundos só mudam se vier `all_funds`, `fund_ids` ou `fundos`. Se o employee ainda não tiver user, envie `email` e `password` juntos.

**Funções canônicas (use o id, nunca o name):**

| id | code | name na API |
|----|------|-------------|
| 1 | `preenchimento` | Preenchimento de formulario |
| 2 | `avalista` | Aprovador |
| 3 | `administrador` | Administrador |

**Fundos:**

| Payload | Efeito |
|---------|--------|
| `"all_funds": true` | Acesso a **todos** os fundos (nenhuma linha em `fund_employee`) |
| `"fund_ids": "todos"` ou `"fundos": "todos"` | Idem |
| `"fund_ids": []` ou omitir fundos no **POST** | Idem |
| `"fund_ids": [1, 2]` | Só esses fundos |
| `"fundos": [{ "id": 1 }, { "id": 2 }]` | Equivalente |

#### POST — exemplo (fundos específicos)

```json
{
  "name": "Maria Silva",
  "email": "maria.silva@empresa.com",
  "password": "SenhaForte1",
  "cedente_role_id": 1,
  "fund_ids": [1, 2]
}
```

#### POST — exemplo (todos os fundos)

```json
{
  "name": "João Pereira",
  "email": "joao.pereira@empresa.com",
  "password": "SenhaForte1",
  "cedente_role_id": 2,
  "all_funds": true
}
```

#### PUT — exemplo (troca função, e-mail, senha e fundos)

```json
{
  "id": 42,
  "name": "Maria Silva Souza",
  "email": "maria.souza@empresa.com",
  "password": "NovaSenha2",
  "cedente_role_id": 3,
  "fund_ids": [1]
}
```

#### PUT — exemplo (só nome e todos os fundos)

```json
{
  "id": 42,
  "name": "Maria Silva Souza",
  "all_funds": true
}
```

**Resposta de sucesso (HTTP 200):**

```json
{
  "error": "false",
  "message": "Funcionario cadastrado com sucesso",
  "data": {
    "id": 42,
    "name": "Maria Silva",
    "image": "sem-foto.jpg",
    "department_id": null,
    "position_id": null,
    "user": {
      "id": 55,
      "email": "maria.silva@empresa.com"
    },
    "cedente_role": {
      "id": 1,
      "code": "preenchimento",
      "name": "Preenchimento de formulario"
    },
    "funds": [
      { "id": 1, "name": "Fundo Alpha", "code": "FA", "type": "FIDC" },
      { "id": 2, "name": "Fundo Beta", "code": "FB", "type": "FIDC" }
    ],
    "all_funds": false
  }
}
```

Quando `all_funds` é `true`, `funds` vem `[]`. A senha **não** volta na resposta.

O login desses usuários é o mesmo `POST /login` (`email` + `password` enviados no cadastro).

### Status automático

| Situação | Status |
|----------|--------|
| Cadastro incompleto | `rascunho` |
| Cadastro completo | `pendente` (+ SLA dias úteis) |
| Arquivo recusado pelo avalista/admin | `inconsistente` + soft delete do arquivo |
| Arquivo reenviado e sem inconsistências | volta para `pendente` |
| Avaliação `aprovado` | `aprovado` + `sla` = hoje + meses |
| Avaliação `solicitar_correcoes` | `inconsistente` (`observacao` obrigatória + inconsistência `aprovador`) |
| Avaliação `rejeitado` / `recusado` | `rejeitado` (`observacao` opcional) |

Validação **SERPRO** (e, se limpa, **Vadu**) roda automaticamente após promoção a `pendente`. Flags: `SERPRO_ENABLED` e `VADU_ENABLED`.

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
