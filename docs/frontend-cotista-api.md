# API de cadastro de cotista

Mesmo grupo de usuários do cedente (`cedente_role_employee`). Não há fundo: todos os cotistas ficam na mesma lista. Serpro e Vadu **não** são chamados nesta versão. Nenhum campo do formulário é obrigatório. Na aprovação, o aprovador informa `prazo_atualizacao_cadastral` (meses).

## Documentação OpenAPI (Swagger)

**[`openapi-cotista.yaml`](./openapi-cotista.yaml)**

Com a aplicação rodando: **`/docs/cotista`** (YAML em **`/docs/cotista/openapi.yaml`**).

Login e headers `User` e `Authorization` (token sem `Bearer`) são os mesmos do cedente. Papéis: `preenchimento`, `avalista` (aprovador) e `administrador`. A lista não filtra por fundo.

## Rotas

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| POST | `/cotista/save` | Cria cotista | Preenchimento / Admin |
| PUT | `/cotista/edit` | Atualiza por `id` (snapshot das listas) | Preenchimento* / Admin |
| PATCH | `/cotista/patch` | Atualização parcial | Preenchimento* / Admin |
| PATCH | `/cotista/arquivo/validacao` | Aprovar/recusar arquivo | Avalista / Admin |
| PATCH | `/cotista/avaliacao` | Aprovar / solicitar correções / rejeitar | Avalista / Admin |
| GET | `/cotistas/get/{id}` | Detalhe | Todos os papéis |
| GET | `/cotistas/historico/{id}` | Histórico | Todos os papéis |
| GET | `/cotistas/arquivos/download/{id}` | Download de um arquivo | Todos os papéis |
| GET | `/cotistas/arquivos/download-all/{id}` | ZIP dos arquivos ativos | Todos os papéis |
| POST | `/cotistas/all` | Lista paginada | Todos os papéis |
| POST | `/cotistas/status-resumo` | Contagem por status | Todos os papéis |
| DELETE | `/cotista/remove/{id}` | Exclui | Admin |

\* Preenchimento só edita `rascunho` ou `inconsistente`.

## Status

| Status | Significado |
|--------|-------------|
| `rascunho` | Gravado sem envio para análise |
| `pendente` | Enviado (`enviar: true` ou `status: pendente`). SLA de dias úteis, a mesma env do cedente |
| `inconsistente` | Correção pedida pelo aprovador ou arquivo recusado. Preenchimento corrige e reenvia |
| `aprovado` | Aprovado. `sla` = hoje + `prazo_atualizacao_cadastral` meses |
| `rejeitado` | Recusado |
| `vencido` | Aprovado com `sla` anterior a hoje |
| `inconsistencia_juridica` | Reservado para Vadu. Nada grava esse valor agora |

Não há `limite_aprovado`. Serpro e Vadu não rodam; o método `runAutomaticValidationsAfterPendente` existe e está vazio.

## Objeto de cadastro (`POST /cotista/save`)

O mesmo JSON aparece no Swagger em **`/docs/cotista`**, exemplo **Objeto de cadastro de cotista** da rota `POST /cotista/save`. Nenhum campo é obrigatório. Não envie `fund_id`. `enviar: true` manda para `pendente`; sem isso o cadastro fica `rascunho`.

```json
{
  "enviar": true,
  "nome_completo": "Ana Paula Costa",
  "cpf": "12345678901",
  "tin": null,
  "nome_mae": "Maria Costa",
  "sexo": "Feminino",
  "local_nascimento": "São Paulo/SP",
  "data_nascimento": "1990-05-15",
  "estado_civil": "Casada",
  "regime_casamento": "Comunhao parcial de bens",
  "nome_conjuge": "Carlos Costa",
  "cpf_conjuge": "98765432100",
  "numero_dependentes": 2,
  "nacionalidade": "Brasileira",
  "possui_outra_nacionalidade": true,
  "outras_nacionalidades": "Italiana, Portuguesa",
  "tipo_documento": "RG",
  "numero_documento": "123456789",
  "data_emissao_documento": "2015-03-20",
  "orgao_emissor": "SSP",
  "uf_pais_emissor": "SP",
  "tempo_residencia": "5 anos",
  "endereco_correspondencia": "residencial",
  "endereco_residencial": {
    "cep": "01310100",
    "logradouro": "Av. Paulista",
    "numero": "1000",
    "complemento": "Apto 12",
    "bairro": "Bela Vista",
    "cidade": "São Paulo",
    "estado": "SP",
    "pais": "Brasil"
  },
  "endereco_comercial": {
    "cep": "04538133",
    "logradouro": "Av. Brigadeiro Faria Lima",
    "numero": "3477",
    "complemento": "Conjunto 101",
    "bairro": "Itaim Bibi",
    "cidade": "São Paulo",
    "estado": "SP",
    "pais": "Brasil"
  },
  "autorizacao_residencia_outros_paises": false,
  "paises_residencia": null,
  "possui_outro_domicilio_fiscal": false,
  "outros_domicilios_fiscais": null,
  "telefone": "+55 11 3333-4444",
  "celular": "+55 11 98888-7777",
  "email": "ana.costa@exemplo.com",
  "us_person": false,
  "natureza_ocupacao": "Assalariada",
  "instituicao_trabalho": "Empresa Exemplo LTDA",
  "cnpj_empregador": "12345678000199",
  "telefone_comercial": "+55 11 3333-5555",
  "profissao": "Economista",
  "cargo": "Analista",
  "email_comercial": "ana.costa@empresa.com",
  "referencias": [
    {
      "nome_completo": "João da Silva",
      "nacionalidade": "Brasileira",
      "telefone": "+55 11 97777-6666"
    }
  ],
  "contas": [
    {
      "banco": "001 - Banco do Brasil",
      "agencia": "1234",
      "numero_conta": "56789-0",
      "tipo_conta": "poupanca"
    },
    {
      "banco": "341 - Itaú",
      "agencia": "4321",
      "numero_conta": "998877-6",
      "tipo_conta": "investimentos"
    }
  ],
  "itens_patrimoniais": [
    {
      "categoria": "Imovel",
      "descricao": "Apartamento residencial",
      "valor_atual": 850000.0
    }
  ],
  "codigo_operacional_cvm": "12345",
  "rde_cademp": "987654",
  "pais_origem": "Brasil",
  "representante_brasil": "Pedro Almeida",
  "representante_coresponsavel": "Lucia Almeida",
  "custodiante": "Custodiante Exemplo",
  "administrador_carteira": "Administrador Exemplo",
  "situacao": "nao_isento",
  "participacoes": [
    {
      "razao_social": "Participacao Exemplo LTDA",
      "cnpj": "11222333000181",
      "nacionalidade": "Brasileira",
      "percentual_capital": 25.5,
      "instituicao_financeira": false,
      "giin": null
    }
  ],
  "proposito_cambio": false,
  "proposito_aplicacoes": true,
  "proposito_intermediacao": false,
  "proposito_credito": false,
  "proposito_acessorias": false,
  "proposito_outros": null,
  "e_ppe": false,
  "ppe_cargo": null,
  "relacionamento_ppe": false,
  "ppe_relacionada_cargo": null,
  "ppe_relacionada_nome": null,
  "ppe_relacionada_cpf": null,
  "ppe_relacionada_nacionalidade": null,
  "operador": {
    "opera_conta_propria": true,
    "autoriza_ordens_procurador": false,
    "nome_completo": null,
    "cpf": null,
    "data_nascimento": null,
    "descricao_poderes": null,
    "e_ppe": false,
    "ppe_cargo": null,
    "relacionamento_ppe": false,
    "ppe_relacionada_cargo": null,
    "ppe_relacionada_nome": null,
    "ppe_relacionada_cpf": null,
    "ppe_relacionada_nacionalidade": null,
    "endereco": null
  },
  "suitability": {
    "perfil": null,
    "respostas": [
      { "pergunta": 1, "opcao": 2 },
      { "pergunta": 2, "opcao": 1 },
      { "pergunta": 3, "opcao": 4 },
      { "pergunta": 4, "opcao": 3 },
      { "pergunta": 5, "opcao": 2 },
      { "pergunta": 6, "opcao": 1 },
      { "pergunta": 7, "opcao": 3 }
    ]
  },
  "arquivos": [
    {
      "document_type": 1,
      "original_name": "documento-identificacao.pdf",
      "type": "pdf",
      "content_base64": "aGVsbG8="
    },
    {
      "document_type": 2,
      "original_name": "comprovante-endereco.pdf",
      "type": "pdf",
      "content_base64": "aGVsbG8="
    }
  ]
}
```

## Payload

`PUT /cotista/edit` substitui referências, contas, itens patrimoniais, participações e suitability. Lista omitida vira lista vazia. `operador: null` remove o operador; omitir `operador` mantém o que já está salvo. `PATCH` só altera as chaves enviadas.

Endereços reutilizam a tabela `address`. Chaves: `endereco_residencial` e `endereco_comercial`, com `cep`, `logradouro` (alias `endereco`), `numero` (alias `n`), `complemento`, `bairro`, `cidade`, `estado` (alias `uf`), `pais`. Correspondência é o radio `endereco_correspondencia`: `residencial` ou `comercial`. `tempo_residencia` fica no cotista.

Contas (`contas[]`): `banco`, `agencia`, `numero_conta`, `tipo_conta` `poupanca` ou `investimentos`. Não usa `conta_desembolso` nem `bank_account`.

Documentos (`arquivos[]`, base64, até 20MB): `document_type` **1** documento de identificação, **2** comprovante de endereço. Não são obrigatórios para enviar.

Suitability: `suitability.respostas[]` com `pergunta` 1–7 e `opcao` 1–4. `perfil` pode ser enviado pelo cliente. Sem `perfil` e sem regra de pontuação, o perfil fica nulo (`CotistaSuitability::atribuirPerfil`).

Operador é 1:1 e opcional, com `endereco` do procurador.

## Avaliação

`PATCH /cotista/avaliacao`:

- `resultado: aprovado` exige `prazo_atualizacao_cadastral` (inteiro, mínimo 1 mês)
- `resultado: solicitar_correcoes` exige `observacao` e grava status `inconsistente` mais uma linha em `inconsistencias` (`campo_inconsistente: aprovador`)
- `resultado: rejeitado` — `observacao` opcional

E-mails de status usam `COTISTA_STATUS_EMAIL_ENABLED`, com fallback para `CEDENTE_STATUS_EMAIL_ENABLED`. O link do portal usa `COTISTA_EMAIL_PORTAL_PATH` (padrão `/cotistas/{id}`).
