# Metas gamificadas — guia para o frontend

Documentação do endpoint que expõe o progresso das metas gamificadas por período. O cálculo é feito em `App\Http\Services\GamifiedGoalsService` e exposto por `GoalController::userGoalProgress`.

## Objetivo na tela

Permitir que o usuário escolha um **intervalo de datas** e veja, para um **atendente** (`attendance_id`), o progresso de cada meta: valor atual, meta, percentual de avanço e se a meta foi **atingida** (`achieved`).

---

## Autenticação e rota

- **Middleware:** `auth.api` e `checkDepartment` (mesmo padrão das demais rotas autenticadas do `web.php`).
- **Métodos:** `GET` ou `POST`.
- **Caminho:** `/user-goal/progress`  
  Use o mesmo prefixo/base URL que o app já utiliza para chamadas autenticadas.

---

## Parâmetros da requisição

Todos obrigatórios. Podem ir na **query string** (GET) ou no **body** (POST, JSON ou form).

| Campo             | Tipo   | Formato    | Descrição |
|-------------------|--------|------------|-----------|
| `attendance_id`   | number | inteiro    | ID do atendente cujas metas serão calculadas. |
| `date_init`       | string | `YYYY-MM-DD` | Início do período (inclusivo). |
| `date_end`        | string | `YYYY-MM-DD` | Fim do período (inclusivo). |

**Exemplo GET**

```http
GET /user-goal/progress?attendance_id=15&date_init=2025-10-01&date_end=2025-12-31
```

**Exemplo POST (JSON)**

```json
{
  "attendance_id": 15,
  "date_init": "2025-10-01",
  "date_end": "2025-12-31"
}
```

---

## Resposta de sucesso (HTTP 200)

```json
{
  "error": false,
  "data": {
    "period": {
      "date_init": "2025-10-01",
      "date_end": "2025-12-31",
      "months": 3,
      "quarters": 1
    },
    "attendance_id": 15,
    "jobs_count": 42,
    "goals": []
  }
}
```

### Campos de `data`

| Campo           | Descrição |
|-----------------|-----------|
| `period`        | Ecoa as datas normalizadas; inclui `months` (meses no intervalo, mínimo 1) e `quarters` (`ceil(months / 3)`). |
| `attendance_id` | Mesmo ID enviado. |
| `jobs_count`    | Quantidade de jobs do usuário no período (como `attendance_id` ou `attendance_comission_id`, filtrado por data de criação). |
| `goals`         | Lista ordenada de metas (ver abaixo). |

### Erros (HTTP 400)

```json
{ "error": true, "message": "attendance_id é obrigatório." }
```

```json
{ "error": true, "message": "date_init e date_end são obrigatórios (formato Y-m-d)." }
```

```json
{ "error": true, "message": "date_init não pode ser maior que date_end." }
```

Sempre verificar `error === true` antes de usar `data`.

---

## Estrutura de cada meta (`goals[]`)

Campos comuns (quando aplicável):

| Campo           | Tipo    | Uso na UI |
|-----------------|---------|-----------|
| `key`           | string  | Identificador estável para ícones, cores, i18n ou testes. |
| `label`         | string  | Texto da meta (pt-BR vindo do backend). |
| `target`        | number, string ou `null` | Meta numérica ou string tipo `"15%"`; `null` se não avaliada. |
| `target_label`  | string ou `null` | Texto pronto para exibir a meta (ex.: `"6,0M (3 meses)"`). |
| `current`       | number, string ou `null` | Valor atual; em conversão vem como string com `%`. |
| `percentage`    | number  | Progresso em relação à meta (0–100 nas metas numéricas com teto; conversão pode passar de 100). |
| `achieved`      | boolean | Se a meta foi cumprida. |

Campos opcionais por meta: `current_label`, `current_raw`, `total_jobs`, `approved_jobs`, `not_evaluated`, `message`.

### Recomendações de UI

1. **Lista ou cards:** iterar `data.goals` e usar `label` + `target_label` / `current` (ou `current_label` quando existir).
2. **Barra de progresso:** usar `percentage`; limitar visualmente a 100% se quiser evitar barra “estourada”, ou mostrar acima de 100% nas metas de conversão quando `percentage > 100`.
3. **Badge “Concluída”:** `achieved === true`.
4. **Meta presencial:** se `not_evaluated === true`, mostrar estado “Em breve” / “Não disponível” e opcionalmente `message`.
5. **Cabeçalho do período:** exibir `period.date_init`–`period.date_end` e, se útil, `jobs_count`.
6. **Filtro de datas:** garantir `date_init <= date_end` no cliente para evitar 400; alinhar com produto se o período for “mês atual”, “trimestre”, etc.

---

## Metas (`key`) — referência rápida

| `key` | O que mede | Observação para UI |
|-------|------------|---------------------|
| `internal_value_per_month` | Soma (R$) de jobs internos **aprovados** vs 2M × meses | Usar `current_label` para valor formatado. |
| `internal_projects_above_150k` | Contagem de internos aprovados ≥ R$ 150k vs 2 × meses | Meta é no **período inteiro**, proporcional a `months`. |
| `internal_projects_above_300k` | Idem, ≥ 300k vs 1 × meses | |
| `internal_projects_above_600k` | Idem, ≥ 600k vs 1 × meses | |
| `internal_projects_above_1500k` | Idem, ≥ 1,5M vs 1 × meses | |
| `presencial_2x_week` | Presencial 2×/semana | Não calculado; tratar como placeholder. |
| `conversion_external_15` | % de jobs **externos** aprovados / total externos | `current` é string; `current_raw` é número; `total_jobs` / `approved_jobs` para tooltip. |
| `conversion_internal_25` | % de jobs **internos** aprovados / total internos | Idem. |
| `min_approvals_per_quarter` | Total de aprovados (qualquer tipo) vs 6 × trimestres | `quarters` vem em `data.period.quarters`. |

**Ordem:** o array segue a ordem acima (índices 0 a 8). Pode-se reordenar na UI por `key` se necessário.

---

## Exemplo mínimo de integração (pseudo-fluxo)

1. Usuário seleciona atendente e intervalo de datas.
2. Chamar `GET` ou `POST` `/user-goal/progress` com os três parâmetros.
3. Se `error`, exibir `message`.
4. Se sucesso, renderizar `data.goals` com progresso e `achieved`.
5. Para `presencial_2x_week`, não tratar `percentage` como dado real até o backend implementar.

---

## Semântica importante (evitar interpretação errada)

- **“Por mês” no rótulo:** o backend **multiplica** a meta pela quantidade de meses do intervalo (`period.months`), mas os **jobs são todos** do intervalo de uma vez — não há quebra calendário mês a mês na API.
- **Interno vs externo:** definido pela descrição da atividade do job (`job_activity.description`): se contém `"externo"` → externo; caso contrário → interno.
- **Aprovado:** `status_id === 3`.
- **Valor do job:** `final_value` ou, se vazio, `budget_value`.

---

## Manutenção

Alterações nas regras ou no formato devem ser refletidas aqui e alinhadas com `GamifiedGoalsService.php` e `GoalController::userGoalProgress`.
