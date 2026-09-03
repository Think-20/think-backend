# Faturamento financeiro — guia para o frontend

Documentação das rotas de **receitas/despesas**, **extrato**, **anexos de faturamento** e relação com o **orçamento legado**.

Swagger (OpenAPI 3): [`docs/openapi-financeiro.yaml`](./openapi-financeiro.yaml)

```bash
docker run --rm -p 8080:8080 -e SWAGGER_JSON=/docs/openapi-financeiro.yaml \
  -v "$(pwd)/docs:/docs" swaggerapi/swagger-ui
```

Ou cole o YAML em [Swagger Editor](https://editor.swagger.io/).

---

## Resumo do fluxo preparado (orçamento → financeiro)

O sistema já tinha **orçamento** há tempo (valores no Job/Task, tabela `budget`, atividades de orçamentista). O módulo financeiro **não substitui** isso: ele opera **em cima do mesmo Job**, registrando movimento de caixa (entradas/saídas).

```
Job (budget_value / final_value)
  └─ Task de Orçamento
       ├─ breakdown em /budget/save|edit (gross_value, BV, equipamentos…)
       └─ task.final_value + campos de custo da planilha
            │
            ▼ (após aprovação / check-in)
       Módulo financeiro (mesmo idjob)
            ├─ POST /financeiro/transacao  → receitas (1) e despesas (2)
            ├─ GET  /financeiro/transacao/{jobId}/2  → comparar despesas
            ├─ GET  /financeiro/extrato/{jobId}      → entradas x saídas
            └─ financeiro-files na task de faturamento (NF, boletos…)
```

### O que reaproveitar do orçamento

| Origem | Campos | Uso no financeiro |
|--------|--------|-------------------|
| `Job` | `budget_value`, `final_value` | Contexto / teto de venda — **não** viram `transaction` automaticamente |
| `Task` | `final_value` + custos da planilha | Referência para o time financeiro |
| Tabela `budget` | `gross_value`, `bv_value`, equipamentos, logística, impostos, markup… | CRUD `/budget/*` — separado das transações |
| `job_id` | — | **Único elo forte**: toda transação pertence a um Job |

Não existe endpoint “gerar despesas a partir do orçamento”. O front usa os valores de orçamento como referência e cadastra as despesas/receitas no financeiro.

---

## Autenticação

- Middleware `auth.api` (mesmo token do app).
- Paths `/financeiro/` e `/financeiro-files/` passam pelo bypass de `permission`, mas **continuam exigindo login**.

---

## Enums

| Campo | Valores |
|-------|---------|
| `tipotransacao` | `1` receita · `2` despesa |
| `status` | `1` pendente · `2` confirmado · `3` conciliado |
| `formapagamento` | `1` dinheiro · `2` depósito · `3` pix · `4` cartão crédito · `5` boleto |
| extrato `periodo` | `7` · `15` · `30` · `todas` |

---

## Cadastrar despesa

`POST /financeiro/transacao`

```json
{
  "idjob": 123,
  "tipotransacao": 2,
  "descricao": "Fornecedor X — material",
  "observacao": "NF a conferir",
  "status": 1,
  "datavencimento": "2026-03-01T00:00:00",
  "idcategoria": 4,
  "idcontabancaria": 1,
  "formapagamento": 3,
  "numparcelas": 2,
  "valortotal": 1000,
  "periodo": 1,
  "parcelas": [
    { "valor": 500, "data": "2026-03-01T00:00:00", "ordem": 1 },
    { "valor": 500, "data": "2026-04-01T00:00:00", "ordem": 2 }
  ],
  "tags": [{ "descricao": "urgente" }]
}
```

**Obrigatórios na prática:** `idjob`, `tipotransacao`, `idcontabancaria`, `valortotal` (≥ 0).  
`status` default `1`; `formapagamento` default `1`; `numparcelas`/`periodo` ≥ 1.

Receita: mesmo payload com `"tipotransacao": 1` e preferencialmente `datarecebimento`.

Resposta: objeto da transação (campos em PT-BR: `idtransacao`, `valortotal`, `parcelas`, `tags`, `categoria`, `contabancaria`…).

---

## Editar / status / excluir

| Ação | Método | Rota | Body |
|------|--------|------|------|
| Editar | `PUT` | `/financeiro/transacao` | mesmo do create + `idtransacao` |
| Só status | `PUT` | `/financeiro/transacao/status` | `{ "idtransacao": 10, "status": 2 }` |
| Excluir | `DELETE` | `/financeiro/tag/transacao/{id}` | — (remove a **transação inteira**) |

Em edit, se enviar `parcelas` ou `tags`, o backend faz **sync** (mantém/atualiza os enviados e apaga os omitidos).

---

## Comparar despesas (e receitas) no Job

### 1) Lista + totais de um tipo

`GET /financeiro/transacao/{jobId}/2` — despesas  
`GET /financeiro/transacao/{jobId}/1` — receitas  

Query opcional: `?date=2026-03-01&contaBancariaId=1`

```json
{
  "totalRealizado": 500.0,
  "totalReceber": 1500.0,
  "totalPrevisto": 2000.0,
  "transacoes": [ /* ... */ ]
}
```

- `totalPrevisto` = soma de todos os `valortotal`
- `totalRealizado` = soma onde `datarealizado` está preenchida
- `totalReceber` = previsto − realizado

Só o total: `GET /financeiro/transacao/total/{jobId}/2`

### 2) Extrato (entradas x saídas por categoria)

`GET /financeiro/extrato/{jobId}?periodo=30`

Cada linha: `entradas` (receitas), `saidas` (despesas), `resultado`, `saldo` acumulado + `saldoTotal` no fim.

Use isso para comparar o resultado financeiro do Job como um todo.

---

## Anexos de faturamento (`financeiro-files`)

Ligados à **task** (não à transação).

1. `POST /upload-file` (multipart) → arquivo no temp do servidor  
2. `POST /financeiro-files/save-multiple`:

```json
[
  {
    "original_name": "nf-fornecedor.pdf",
    "task": { "id": 456 }
  }
]
```

| Ação | Rota |
|------|------|
| Remover | `DELETE /financeiro-files/remove/{id}` |
| Download 1 | `GET /financeiro-files/download/{id}` |
| Download ZIP | `GET /financeiro-files/download-all/{taskId}` |
| View (público) | `GET /financeiro-files/view/{id}` |

---

## Orçamento legado (referência)

| Método | Rota | Uso |
|--------|------|-----|
| `POST` | `/budget/save` | Cria breakdown na task |
| `PUT` | `/budget/edit` | Atualiza (`id` + `task.id` + valores) |

Campos típicos: `gross_value`, `optional_value`, `bv_value`, `equipments_value`, `logistics_value`, `sales_commission_value`, `tax_aliquot`, `others_value`, `markup_aliquot` (aceitam `"10000,00"`).

`GET /reprocessOrcamento` apenas alinha `job.final_value` a partir das tasks — **não** cria transações.

---

## Cadastros auxiliares (usados no form de transação)

| Recurso | Rotas principais |
|---------|------------------|
| Categorias | `POST /categories/all`, `/category/save\|edit`, etc. |
| Contas | `POST /bank-accounts/all`, `/bank-account/save\|edit`, etc. |
| Tags | `POST /tags/all`, `/tag/save\|edit`, etc. |
| Tipos de conta | `GET /bank-account-types/all` |

---

## Checklist rápido para o front

1. Abrir Job → carregar contexto de orçamento (`final_value` / budget) se a tela precisar mostrar referência.  
2. Listar despesas: `GET /financeiro/transacao/{jobId}/2`.  
3. Cadastrar despesa: `POST /financeiro/transacao` com `tipotransacao: 2`.  
4. Comparar resultado: `GET /financeiro/extrato/{jobId}?periodo=30`.  
5. Anexar NF: `/upload-file` → `/financeiro-files/save-multiple`.  
6. Confirmar pagamento: `PUT /financeiro/transacao/status` (`status: 2` ou `3`) e/ou preencher `datarealizado` no edit.
