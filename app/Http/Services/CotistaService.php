<?php

namespace App\Http\Services;

use App\Address;
use App\Cotista;
use App\CotistaAudit;
use App\CotistaConta;
use App\CotistaFile;
use App\CotistaInconsistencia;
use App\CotistaItemPatrimonial;
use App\CotistaOperador;
use App\CotistaParticipacao;
use App\CotistaReferencia;
use App\CotistaSuitability;
use App\CotistaSuitabilityResposta;
use App\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CotistaService
{
    /**
     * @return array
     */
    public static function normalizePayload(array $data)
    {
        if (isset($data['payload']) && is_array($data['payload'])) {
            $inner = $data['payload'];
            unset($data['payload']);
            $data = array_merge($inner, $data);
        }

        if (isset($data['cotista']) && is_array($data['cotista'])) {
            $nested = $data['cotista'];
            unset($data['cotista']);
            $data = array_merge($nested, $data);
        }

        if (isset($data['nome_completo']) && is_string($data['nome_completo'])) {
            $data['nome_completo'] = trim($data['nome_completo']);
        }
        if (isset($data['documento'])) {
            $data['documento'] = is_string($data['documento']) ? trim($data['documento']) : trim((string) $data['documento']);
        }

        return $data;
    }

    /**
     * Lista unica de cotistas. Nao ha fundo neste modulo.
     *
     * @return array<string, int>
     */
    public static function statusResumo()
    {
        return Cotista::cadastroStatusResumo();
    }

    /**
     * @param int $cotistaId
     * @return Cotista
     */
    public static function findCotista($cotistaId)
    {
        $cotista = Cotista::query()->find((int) $cotistaId);
        if (! $cotista) {
            throw new InvalidArgumentException('Cotista nao encontrado');
        }

        return $cotista;
    }

    /**
     * @param array $data
     * @return Cotista
     */
    public static function create(array $data)
    {
        $data = self::normalizePayload($data);
        CotistaPermissionService::assertCanCreate();

        return DB::transaction(function () use ($data) {
            $cotista = new Cotista();
            $cotista->status = Cotista::STATUS_RASCUNHO;
            self::applyScalars($cotista, $data, false);
            $cotista->save();

            if (array_key_exists('endereco_residencial', $data)) {
                self::syncOwnedAddress($cotista, 'endereco_residencial_id', $data['endereco_residencial']);
            }
            if (array_key_exists('endereco_comercial', $data)) {
                self::syncOwnedAddress($cotista, 'endereco_comercial_id', $data['endereco_comercial']);
            }

            self::replaceReferencias($cotista, isset($data['referencias']) ? $data['referencias'] : []);
            self::replaceContas($cotista, isset($data['contas']) ? $data['contas'] : []);
            self::replaceItensPatrimoniais($cotista, isset($data['itens_patrimoniais']) ? $data['itens_patrimoniais'] : []);
            self::replaceParticipacoes($cotista, isset($data['participacoes']) ? $data['participacoes'] : []);
            self::syncSuitability($cotista, array_key_exists('suitability', $data) ? $data['suitability'] : null);

            if (array_key_exists('operador', $data)) {
                self::syncOperador($cotista, $data['operador'], false);
            }

            if (array_key_exists('arquivos', $data) && is_array($data['arquivos'])) {
                self::validateArquivos($data['arquivos']);
                self::syncArquivos($cotista, $data['arquivos']);
            }

            $cotista->refresh();

            if (self::wantsEnviar($data)) {
                if (! self::isCadastroCompleto($cotista)) {
                    throw new InvalidArgumentException('Cadastro incompleto: nao foi possivel enviar para analise');
                }
                self::promoteToPendente($cotista);
                self::recordStatusAudit(
                    $cotista->id,
                    CotistaAudit::EVENT_CADASTRO_CRIADO,
                    Cotista::STATUS_PENDENTE,
                    null,
                    [
                        'descricao' => 'Cadastro criado e enviado para analise',
                        'nome_completo' => $cotista->nome_completo,
                        'tipo_pessoa' => $cotista->tipo_pessoa,
                        'documento' => $cotista->documento,
                    ]
                );
                self::runAutomaticValidationsAfterPendente($cotista, null);
            } else {
                self::enforceDraftStatus($cotista);
                self::recordStatusAudit(
                    $cotista->id,
                    CotistaAudit::EVENT_CADASTRO_CRIADO,
                    Cotista::STATUS_RASCUNHO,
                    null,
                    [
                        'descricao' => 'Cadastro criado em rascunho',
                        'nome_completo' => $cotista->nome_completo,
                        'tipo_pessoa' => $cotista->tipo_pessoa,
                        'documento' => $cotista->documento,
                    ]
                );
            }

            return $cotista->fresh(Cotista::apiRelations());
        });
    }

    /**
     * Atualizacao completa. Listas sao substituidas. Operador so e removido com `operador: null`.
     *
     * @param array $data
     * @return Cotista
     */
    public static function update(array $data)
    {
        $data = self::normalizePayload($data);

        return DB::transaction(function () use ($data) {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('ID do cotista e obrigatorio para atualizacao');
            }

            $cotista = self::findCotista($data['id']);
            CotistaPermissionService::assertCanUpdate($cotista, $data);
            $cotista->load(Cotista::apiRelations());

            $statusAntes = $cotista->status ?: Cotista::STATUS_RASCUNHO;
            $snapshotAntes = self::snapshotForAudit($cotista);

            self::applyScalars($cotista, $data, false);
            $cotista->save();

            if (array_key_exists('endereco_residencial', $data)) {
                self::syncOwnedAddress($cotista, 'endereco_residencial_id', $data['endereco_residencial']);
            }
            if (array_key_exists('endereco_comercial', $data)) {
                self::syncOwnedAddress($cotista, 'endereco_comercial_id', $data['endereco_comercial']);
            }

            self::replaceReferencias($cotista, isset($data['referencias']) ? $data['referencias'] : []);
            self::replaceContas($cotista, isset($data['contas']) ? $data['contas'] : []);
            self::replaceItensPatrimoniais($cotista, isset($data['itens_patrimoniais']) ? $data['itens_patrimoniais'] : []);
            self::replaceParticipacoes($cotista, isset($data['participacoes']) ? $data['participacoes'] : []);
            self::syncSuitability($cotista, array_key_exists('suitability', $data) ? $data['suitability'] : null);

            if (array_key_exists('operador', $data)) {
                self::syncOperador($cotista, $data['operador'], false);
            }

            if (array_key_exists('arquivos', $data)) {
                self::validateArquivos($data['arquivos']);
                self::syncArquivos($cotista, is_array($data['arquivos']) ? $data['arquivos'] : []);
            }

            $promoted = self::applyStatusAfterSave($cotista, $data, $statusAntes);
            self::finalizeUpdate($cotista, $data, $snapshotAntes, $statusAntes, 'edit');

            if ($promoted) {
                self::runAutomaticValidationsAfterPendente($cotista, $statusAntes);
            }

            return $cotista->fresh(Cotista::apiRelations());
        });
    }

    /**
     * Atualizacao parcial: so altera chaves presentes.
     *
     * @param array $data
     * @return Cotista
     */
    public static function patchPartial(array $data)
    {
        $data = self::normalizePayload($data);

        return DB::transaction(function () use ($data) {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('ID do cotista e obrigatorio para atualizacao');
            }

            $cotista = self::findCotista($data['id']);
            CotistaPermissionService::assertCanUpdate($cotista, $data);
            $cotista->load(Cotista::apiRelations());

            $statusAntes = $cotista->status ?: Cotista::STATUS_RASCUNHO;
            $snapshotAntes = self::snapshotForAudit($cotista);

            self::applyScalars($cotista, $data, true);
            $cotista->save();

            if (array_key_exists('endereco_residencial', $data)) {
                self::syncOwnedAddress($cotista, 'endereco_residencial_id', $data['endereco_residencial']);
            }
            if (array_key_exists('endereco_comercial', $data)) {
                self::syncOwnedAddress($cotista, 'endereco_comercial_id', $data['endereco_comercial']);
            }

            if (array_key_exists('referencias', $data)) {
                self::replaceReferencias($cotista, $data['referencias']);
            }
            if (array_key_exists('contas', $data)) {
                self::replaceContas($cotista, $data['contas']);
            }
            if (array_key_exists('itens_patrimoniais', $data)) {
                self::replaceItensPatrimoniais($cotista, $data['itens_patrimoniais']);
            }
            if (array_key_exists('participacoes', $data)) {
                self::replaceParticipacoes($cotista, $data['participacoes']);
            }
            if (array_key_exists('suitability', $data)) {
                self::syncSuitability($cotista, $data['suitability']);
            }
            if (array_key_exists('operador', $data)) {
                self::syncOperador($cotista, $data['operador'], true);
            }
            if (array_key_exists('arquivos', $data)) {
                self::validateArquivos($data['arquivos']);
                self::syncArquivos($cotista, is_array($data['arquivos']) ? $data['arquivos'] : []);
            }

            $promoted = self::applyStatusAfterSave($cotista, $data, $statusAntes);
            self::finalizeUpdate($cotista, $data, $snapshotAntes, $statusAntes, 'patch');

            if ($promoted) {
                self::runAutomaticValidationsAfterPendente($cotista, $statusAntes);
            }

            return $cotista->fresh(Cotista::apiRelations());
        });
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function deleteById($id)
    {
        $cotista = self::findCotista($id);
        CotistaPermissionService::assertCanDelete($cotista);

        return DB::transaction(function () use ($cotista) {
            $cotista->load(['operador']);
            $addressIds = [];
            if ($cotista->endereco_residencial_id) {
                $addressIds[] = $cotista->endereco_residencial_id;
            }
            if ($cotista->endereco_comercial_id) {
                $addressIds[] = $cotista->endereco_comercial_id;
            }
            if ($cotista->operador && $cotista->operador->address_id) {
                $addressIds[] = $cotista->operador->address_id;
            }

            $cotista->delete();

            if (! empty($addressIds)) {
                Address::whereIn('id', array_unique($addressIds))->delete();
            }

            return true;
        });
    }

    /**
     * Nenhum campo do formulario e obrigatorio nesta versao.
     * Os requisitos futuros ficam concentrados aqui.
     *
     * @param Cotista $cotista
     * @return bool
     */
    public static function isCadastroCompleto(Cotista $cotista)
    {
        return true;
    }

    /**
     * @return int
     */
    public static function markExpiredApprovedAsVencido()
    {
        $hoje = date('Y-m-d');
        $query = Cotista::query()
            ->where('status', Cotista::STATUS_APROVADO)
            ->whereNotNull('sla')
            ->where('sla', '<', $hoje);

        $count = 0;
        foreach ($query->get() as $cotista) {
            $statusAntes = $cotista->status;
            $cotista->status = Cotista::STATUS_VENCIDO;
            $cotista->save();
            $count++;

            self::recordStatusAudit(
                $cotista->id,
                CotistaAudit::EVENT_STATUS_ALTERADO,
                Cotista::STATUS_VENCIDO,
                $statusAntes,
                [
                    'descricao' => 'Cadastro vencido — prazo de atualizacao cadastral expirado',
                    'sla' => self::formatDate($cotista->sla),
                ],
                true
            );
        }

        return $count;
    }

    /**
     * @param Cotista $cotista
     * @return array
     */
    public static function toApiArray(Cotista $cotista)
    {
        $cotista->loadMissing(Cotista::apiRelations());
        $labels = CotistaFile::documentTypeLabels();
        $perguntas = CotistaSuitability::perguntas();
        $status = $cotista->status ?: Cotista::STATUS_RASCUNHO;

        return [
            'id' => $cotista->id,
            'nome_completo' => $cotista->nome_completo,
            'tipo_pessoa' => $cotista->tipo_pessoa,
            'documento' => $cotista->documento,
            'tin' => $cotista->tin,
            'nome_mae' => $cotista->nome_mae,
            'sexo' => $cotista->sexo,
            'local_nascimento' => $cotista->local_nascimento,
            'data_nascimento' => self::formatDate($cotista->data_nascimento),
            'estado_civil' => $cotista->estado_civil,
            'regime_casamento' => $cotista->regime_casamento,
            'nome_conjuge' => $cotista->nome_conjuge,
            'cpf_conjuge' => $cotista->cpf_conjuge,
            'numero_dependentes' => $cotista->numero_dependentes !== null ? (int) $cotista->numero_dependentes : null,
            'nacionalidade' => $cotista->nacionalidade,
            'possui_outra_nacionalidade' => (bool) $cotista->possui_outra_nacionalidade,
            'outras_nacionalidades' => $cotista->outras_nacionalidades,
            'tipo_documento' => $cotista->tipo_documento,
            'numero_documento' => $cotista->numero_documento,
            'data_emissao_documento' => self::formatDate($cotista->data_emissao_documento),
            'orgao_emissor' => $cotista->orgao_emissor,
            'uf_pais_emissor' => $cotista->uf_pais_emissor,
            'tempo_residencia' => $cotista->tempo_residencia,
            'endereco_correspondencia' => $cotista->endereco_correspondencia,
            'endereco_residencial' => $cotista->enderecoResidencial ? $cotista->enderecoResidencial->toArray() : null,
            'endereco_comercial' => $cotista->enderecoComercial ? $cotista->enderecoComercial->toArray() : null,
            'autorizacao_residencia_outros_paises' => (bool) $cotista->autorizacao_residencia_outros_paises,
            'paises_residencia' => $cotista->paises_residencia,
            'possui_outro_domicilio_fiscal' => (bool) $cotista->possui_outro_domicilio_fiscal,
            'outros_domicilios_fiscais' => $cotista->outros_domicilios_fiscais,
            'telefone' => $cotista->telefone,
            'celular' => $cotista->celular,
            'email' => $cotista->email,
            'us_person' => (bool) $cotista->us_person,
            'natureza_ocupacao' => $cotista->natureza_ocupacao,
            'instituicao_trabalho' => $cotista->instituicao_trabalho,
            'cnpj_empregador' => $cotista->cnpj_empregador,
            'telefone_comercial' => $cotista->telefone_comercial,
            'profissao' => $cotista->profissao,
            'cargo' => $cotista->cargo,
            'email_comercial' => $cotista->email_comercial,
            'codigo_operacional_cvm' => $cotista->codigo_operacional_cvm,
            'rde_cademp' => $cotista->rde_cademp,
            'pais_origem' => $cotista->pais_origem,
            'representante_brasil' => $cotista->representante_brasil,
            'representante_coresponsavel' => $cotista->representante_coresponsavel,
            'custodiante' => $cotista->custodiante,
            'administrador_carteira' => $cotista->administrador_carteira,
            'situacao' => $cotista->situacao,
            'proposito_cambio' => (bool) $cotista->proposito_cambio,
            'proposito_aplicacoes' => (bool) $cotista->proposito_aplicacoes,
            'proposito_intermediacao' => (bool) $cotista->proposito_intermediacao,
            'proposito_credito' => (bool) $cotista->proposito_credito,
            'proposito_acessorias' => (bool) $cotista->proposito_acessorias,
            'proposito_outros' => $cotista->proposito_outros,
            'e_ppe' => (bool) $cotista->e_ppe,
            'ppe_cargo' => $cotista->ppe_cargo,
            'relacionamento_ppe' => (bool) $cotista->relacionamento_ppe,
            'ppe_relacionada_cargo' => $cotista->ppe_relacionada_cargo,
            'ppe_relacionada_nome' => $cotista->ppe_relacionada_nome,
            'ppe_relacionada_cpf' => $cotista->ppe_relacionada_cpf,
            'ppe_relacionada_nacionalidade' => $cotista->ppe_relacionada_nacionalidade,
            'status' => $status,
            'status_label' => Cotista::statusLabel($status),
            'sla' => self::formatDate($cotista->sla),
            'prazo_atualizacao_cadastral' => $cotista->prazo_atualizacao_cadastral !== null ? (int) $cotista->prazo_atualizacao_cadastral : null,
            'referencias' => $cotista->referencias->map(function ($row) {
                return $row->toArray();
            })->values()->all(),
            'contas' => $cotista->contas->map(function ($row) {
                return $row->toArray();
            })->values()->all(),
            'itens_patrimoniais' => $cotista->itensPatrimoniais->map(function ($row) {
                $item = $row->toArray();
                $item['valor_atual'] = $row->valor_atual !== null ? (string) $row->valor_atual : null;

                return $item;
            })->values()->all(),
            'participacoes' => $cotista->participacoes->map(function ($row) {
                $item = $row->toArray();
                $item['percentual_capital'] = $row->percentual_capital !== null ? (string) $row->percentual_capital : null;
                $item['instituicao_financeira'] = (bool) $row->instituicao_financeira;

                return $item;
            })->values()->all(),
            'operador' => self::operadorToApi($cotista->operador),
            'suitability' => self::suitabilityToApi($cotista->suitability, $perguntas),
            'arquivos' => $cotista->cotistaFiles->sortBy('document_type')->values()->map(function ($f) use ($labels) {
                return [
                    'id' => $f->id,
                    'document_type' => (int) $f->document_type,
                    'document_type_label' => isset($labels[$f->document_type]) ? $labels[$f->document_type] : null,
                    'original_name' => $f->original_name,
                    'name' => $f->name,
                    'type' => $f->type,
                    'valido' => (bool) $f->valido,
                    'status_arquivo' => CotistaFile::statusFromValido((bool) $f->valido),
                    'created_at' => $f->created_at,
                    'updated_at' => $f->updated_at,
                ];
            })->all(),
            'inconsistencias' => self::inconsistenciasToApiArray($cotista),
            'historico' => self::historicoToApiArray($cotista),
        ];
    }

    /**
     * @param Cotista $cotista
     * @return array
     */
    public static function inconsistenciasToApiArray(Cotista $cotista)
    {
        if (! $cotista->id) {
            return [];
        }

        return CotistaInconsistencia::where('cotista_id', (int) $cotista->id)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($i) {
                return [
                    'id' => (int) $i->id,
                    'campo_inconsistente' => $i->campo_inconsistente,
                    'valor_serpro' => $i->valor_serpro,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Cotista $cotista
     * @return array
     */
    public static function historicoToApiArray(Cotista $cotista)
    {
        $cotista->loadMissing(['audits.user.employee']);

        return $cotista->audits
            ->sortByDesc('id')
            ->values()
            ->map(function ($audit) {
                $audit->loadMissing(['user.employee']);
                $user = $audit->user;

                return [
                    'id' => $audit->id,
                    'event' => $audit->event,
                    'old_status' => $audit->old_status,
                    'new_status' => $audit->new_status,
                    'changes' => $audit->changes,
                    'user_id' => $audit->user_id,
                    'user_name' => self::resolveAuditUserName($audit),
                    'usuario_email' => $user ? $user->email : null,
                    'created_at' => $audit->created_at ? $audit->created_at->toDateTimeString() : null,
                ];
            })
            ->all();
    }

    /**
     * Ponto unico reservado para Serpro e Vadu. Nao dispara nesta versao.
     *
     * @param Cotista $cotista
     * @param string|null $statusInicial
     */
    private static function runAutomaticValidationsAfterPendente(Cotista $cotista, $statusInicial)
    {
        unset($cotista, $statusInicial);
    }

    /**
     * @param Cotista $cotista
     */
    private static function promoteToPendente(Cotista $cotista)
    {
        $cotista->status = Cotista::STATUS_PENDENTE;
        self::applySlaForStatus($cotista, Cotista::STATUS_PENDENTE);
        $cotista->save();
    }

    /**
     * @param Cotista $cotista
     * @param array $data
     * @param string|null $statusAntes
     * @return bool true quando o cadastro acabou de ir para pendente
     */
    private static function applyStatusAfterSave(Cotista $cotista, array $data, $statusAntes)
    {
        $cotista->refresh();
        $enviar = self::wantsEnviar($data);
        $requested = null;
        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '' && ! $enviar) {
            $requested = self::resolveStatusForUpdate($data['status']);
        }
        $podePromover = in_array($statusAntes, [
            Cotista::STATUS_RASCUNHO,
            Cotista::STATUS_INCONSISTENTE,
            null,
            '',
        ], true);

        if ($enviar && $podePromover) {
            if (! self::isCadastroCompleto($cotista)) {
                throw new InvalidArgumentException('Cadastro incompleto: nao foi possivel enviar para analise');
            }
            self::promoteToPendente($cotista);

            return true;
        }

        if (($statusAntes === Cotista::STATUS_RASCUNHO || $statusAntes === null || $statusAntes === '') && ! $enviar) {
            self::enforceDraftStatus($cotista);

            return false;
        }

        if ($requested !== null && $requested !== $cotista->status) {
            $cotista->status = $requested;
            self::applySlaForStatus($cotista, $requested);
            $cotista->save();
        }

        return false;
    }

    /**
     * @param Cotista $cotista
     */
    private static function enforceDraftStatus(Cotista $cotista)
    {
        $cotista->status = Cotista::STATUS_RASCUNHO;
        $cotista->sla = null;
        $cotista->save();
    }

    /**
     * @param array $data
     * @return bool
     */
    private static function wantsEnviar(array $data)
    {
        if (array_key_exists('enviar', $data)) {
            return self::normalizeBool($data['enviar']);
        }

        if (array_key_exists('status', $data)) {
            return self::normalizeStatusValue($data['status']) === Cotista::STATUS_PENDENTE;
        }

        return false;
    }

    /**
     * @param Cotista $cotista
     * @param array $data
     * @param array $snapshotAntes
     * @param string $statusAntes
     * @param string $tipo
     */
    private static function finalizeUpdate(Cotista $cotista, array $data, array $snapshotAntes, $statusAntes, $tipo)
    {
        $cotista->refresh();
        $cotista->load(Cotista::apiRelations());
        $snapshotDepois = self::snapshotForAudit($cotista);
        $alteracoes = self::buildAlteracoes($snapshotAntes, $snapshotDepois, $data, $tipo);
        $statusAtual = $cotista->status ?: Cotista::STATUS_RASCUNHO;

        if (! empty($alteracoes) || $statusAtual !== $statusAntes) {
            self::recordStatusAudit(
                $cotista->id,
                CotistaAudit::EVENT_COTISTA_ATUALIZADO,
                $statusAtual,
                $statusAntes,
                [
                    'descricao' => 'Cadastro atualizado',
                    'tipo_atualizacao' => $tipo,
                    'alteracoes' => $alteracoes,
                ]
            );
        }

        if ($statusAtual !== $statusAntes) {
            self::recordStatusAudit(
                $cotista->id,
                CotistaAudit::EVENT_STATUS_ALTERADO,
                $statusAtual,
                $statusAntes,
                [
                    'descricao' => 'Status alterado de ' . Cotista::statusLabel($statusAntes) . ' para ' . Cotista::statusLabel($statusAtual),
                ]
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private static function scalarDefinitions()
    {
        return [
            'nome_completo' => 'string',
            'tipo_pessoa' => 'tipo_pessoa',
            'documento' => 'string',
            'tin' => 'string',
            'nome_mae' => 'string',
            'sexo' => 'string',
            'local_nascimento' => 'string',
            'data_nascimento' => 'date',
            'estado_civil' => 'string',
            'regime_casamento' => 'string',
            'nome_conjuge' => 'string',
            'cpf_conjuge' => 'string',
            'numero_dependentes' => 'uint',
            'nacionalidade' => 'string',
            'possui_outra_nacionalidade' => 'bool',
            'outras_nacionalidades' => 'string',
            'tipo_documento' => 'string',
            'numero_documento' => 'string',
            'data_emissao_documento' => 'date',
            'orgao_emissor' => 'string',
            'uf_pais_emissor' => 'string',
            'tempo_residencia' => 'string',
            'endereco_correspondencia' => 'correspondencia',
            'autorizacao_residencia_outros_paises' => 'bool',
            'paises_residencia' => 'string',
            'possui_outro_domicilio_fiscal' => 'bool',
            'outros_domicilios_fiscais' => 'string',
            'telefone' => 'phone',
            'celular' => 'phone',
            'email' => 'string',
            'us_person' => 'bool',
            'natureza_ocupacao' => 'string',
            'instituicao_trabalho' => 'string',
            'cnpj_empregador' => 'string',
            'telefone_comercial' => 'phone',
            'profissao' => 'string',
            'cargo' => 'string',
            'email_comercial' => 'string',
            'codigo_operacional_cvm' => 'string',
            'rde_cademp' => 'string',
            'pais_origem' => 'string',
            'representante_brasil' => 'string',
            'representante_coresponsavel' => 'string',
            'custodiante' => 'string',
            'administrador_carteira' => 'string',
            'situacao' => 'situacao',
            'proposito_cambio' => 'bool',
            'proposito_aplicacoes' => 'bool',
            'proposito_intermediacao' => 'bool',
            'proposito_credito' => 'bool',
            'proposito_acessorias' => 'bool',
            'proposito_outros' => 'string',
            'e_ppe' => 'bool',
            'ppe_cargo' => 'string',
            'relacionamento_ppe' => 'bool',
            'ppe_relacionada_cargo' => 'string',
            'ppe_relacionada_nome' => 'string',
            'ppe_relacionada_cpf' => 'string',
            'ppe_relacionada_nacionalidade' => 'string',
        ];
    }

    /**
     * @param Cotista $cotista
     * @param array $data
     * @param bool $partial
     */
    private static function applyScalars(Cotista $cotista, array $data, $partial)
    {
        foreach (self::scalarDefinitions() as $field => $type) {
            if ($partial && ! array_key_exists($field, $data)) {
                continue;
            }
            $raw = array_key_exists($field, $data) ? $data[$field] : null;
            $cotista->$field = self::castScalar($field, $type, $raw);
        }
    }

    /**
     * @param string $field
     * @param string $type
     * @param mixed $raw
     * @return mixed
     */
    private static function castScalar($field, $type, $raw)
    {
        if ($type === 'bool') {
            if ($raw === null || $raw === '') {
                return false;
            }

            return self::normalizeBool($raw);
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        if ($type === 'date') {
            return self::normalizeDate($field, $raw);
        }

        if ($type === 'uint') {
            if (! is_numeric($raw)) {
                throw new InvalidArgumentException($field . ' deve ser um numero inteiro');
            }
            $n = (int) $raw;
            if ($n < 0) {
                throw new InvalidArgumentException($field . ' nao pode ser negativo');
            }

            return $n;
        }

        if ($type === 'correspondencia') {
            $value = self::normalizeToken($raw);
            if (! in_array($value, Cotista::correspondenciaValues(), true)) {
                throw new InvalidArgumentException('endereco_correspondencia invalido (use residencial ou comercial)');
            }

            return $value;
        }

        if ($type === 'situacao') {
            $value = self::normalizeToken($raw);
            $aliases = [
                'nao_isento' => Cotista::SITUACAO_NAO_ISENTO,
                'nao_isenta' => Cotista::SITUACAO_NAO_ISENTO,
                'não_isento' => Cotista::SITUACAO_NAO_ISENTO,
                'isento' => Cotista::SITUACAO_ISENTO,
                'isenta' => Cotista::SITUACAO_ISENTO,
            ];
            if (isset($aliases[$value])) {
                return $aliases[$value];
            }
            throw new InvalidArgumentException('situacao invalida (use isento ou nao_isento)');
        }

        if ($type === 'tipo_pessoa') {
            $value = self::normalizeToken($raw);
            $value = str_replace(['á', 'í'], ['a', 'i'], $value);
            $aliases = [
                'fisica' => Cotista::TIPO_PESSOA_FISICA,
                'pf' => Cotista::TIPO_PESSOA_FISICA,
                'pessoa_fisica' => Cotista::TIPO_PESSOA_FISICA,
                'juridica' => Cotista::TIPO_PESSOA_JURIDICA,
                'pj' => Cotista::TIPO_PESSOA_JURIDICA,
                'pessoa_juridica' => Cotista::TIPO_PESSOA_JURIDICA,
            ];
            if (isset($aliases[$value])) {
                return $aliases[$value];
            }

            throw new InvalidArgumentException('tipo_pessoa invalido (use fisica ou juridica)');
        }

        $text = is_string($raw) ? trim($raw) : trim((string) $raw);
        if ($text === '') {
            return null;
        }

        if ($type === 'phone' && strlen($text) > 32) {
            throw new InvalidArgumentException($field . ' excede 32 caracteres');
        }

        return $text;
    }

    /**
     * @param mixed $raw
     * @return bool
     */
    private static function normalizeBool($raw)
    {
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }
        $s = is_string($raw) ? strtolower(trim($raw)) : strtolower(trim((string) $raw));
        if (in_array($s, ['1', 'true', 'sim', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($s, ['0', 'false', 'nao', 'não', 'no', 'off', ''], true)) {
            return false;
        }

        return (bool) $raw;
    }

    /**
     * @param mixed $raw
     * @return string
     */
    private static function normalizeToken($raw)
    {
        $s = is_string($raw) ? trim($raw) : trim((string) $raw);
        $s = strtolower($s);
        $s = str_replace([' ', '-'], '_', $s);

        return $s;
    }

    /**
     * @param string $field
     * @param mixed $raw
     * @return string
     */
    private static function normalizeDate($field, $raw)
    {
        $value = is_string($raw) ? trim($raw) : trim((string) $raw);
        $value = substr($value, 0, 10);
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if (! $dt || $dt->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($field . ' deve estar no formato AAAA-MM-DD');
        }

        return $value;
    }

    /**
     * @param mixed $raw
     * @return string|null
     */
    private static function normalizeStatusValue($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = self::normalizeToken($raw);
        $aliases = [
            'inconsistencia' => Cotista::STATUS_INCONSISTENTE,
            'inconsistencia_cadastral' => Cotista::STATUS_INCONSISTENTE,
            'recusado' => Cotista::STATUS_REJEITADO,
        ];
        if (isset($aliases[$s])) {
            return $aliases[$s];
        }

        return $s === '' ? null : $s;
    }

    /**
     * @param mixed $raw
     * @return string
     */
    private static function resolveStatusForUpdate($raw)
    {
        $s = self::normalizeStatusValue($raw);
        if ($s === null || ! Cotista::isCadastroStatus($s)) {
            throw new InvalidArgumentException('Status do cadastro invalido');
        }

        return $s;
    }

    /**
     * @param Cotista $cotista
     * @param mixed $list
     */
    private static function replaceReferencias(Cotista $cotista, $list)
    {
        $cotista->referencias()->delete();
        if (! is_array($list)) {
            return;
        }

        foreach ($list as $row) {
            if (! is_array($row) || self::rowEmpty($row)) {
                continue;
            }
            $nome = isset($row['nome_completo']) ? trim((string) $row['nome_completo']) : '';
            if ($nome === '') {
                throw new InvalidArgumentException('nome_completo e obrigatorio em cada referencia');
            }
            CotistaReferencia::create([
                'cotista_id' => $cotista->id,
                'nome_completo' => $nome,
                'nacionalidade' => self::nullableString(isset($row['nacionalidade']) ? $row['nacionalidade'] : null),
                'telefone' => self::nullableString(isset($row['telefone']) ? $row['telefone'] : null, 32, 'telefone da referencia'),
            ]);
        }
    }

    /**
     * @param Cotista $cotista
     * @param mixed $list
     */
    private static function replaceContas(Cotista $cotista, $list)
    {
        $cotista->contas()->delete();
        if (! is_array($list)) {
            return;
        }

        $allowed = array_keys(CotistaConta::tiposConta());
        foreach ($list as $row) {
            if (! is_array($row) || self::rowEmpty($row)) {
                continue;
            }
            $tipo = isset($row['tipo_conta']) ? self::normalizeToken($row['tipo_conta']) : '';
            $aliases = [
                'poupanca' => CotistaConta::TIPO_POUPANCA,
                'poupança' => CotistaConta::TIPO_POUPANCA,
                'conta_poupanca' => CotistaConta::TIPO_POUPANCA,
                'investimentos' => CotistaConta::TIPO_INVESTIMENTOS,
                'investimento' => CotistaConta::TIPO_INVESTIMENTOS,
            ];
            if (isset($aliases[$tipo])) {
                $tipo = $aliases[$tipo];
            }
            if (! in_array($tipo, $allowed, true)) {
                throw new InvalidArgumentException('tipo_conta invalido em conta (use poupanca ou investimentos)');
            }
            $banco = self::nullableString(isset($row['banco']) ? $row['banco'] : null);
            $agencia = self::nullableString(isset($row['agencia']) ? $row['agencia'] : null);
            $numero = self::nullableString(isset($row['numero_conta']) ? $row['numero_conta'] : null);
            if ($banco === null || $agencia === null || $numero === null) {
                throw new InvalidArgumentException('banco, agencia e numero_conta sao obrigatorios em cada conta');
            }

            CotistaConta::create([
                'cotista_id' => $cotista->id,
                'banco' => $banco,
                'agencia' => $agencia,
                'numero_conta' => $numero,
                'tipo_conta' => $tipo,
            ]);
        }
    }

    /**
     * @param Cotista $cotista
     * @param mixed $list
     */
    private static function replaceItensPatrimoniais(Cotista $cotista, $list)
    {
        $cotista->itensPatrimoniais()->delete();
        if (! is_array($list)) {
            return;
        }

        foreach ($list as $row) {
            if (! is_array($row) || self::rowEmpty($row)) {
                continue;
            }
            CotistaItemPatrimonial::create([
                'cotista_id' => $cotista->id,
                'categoria' => self::nullableString(isset($row['categoria']) ? $row['categoria'] : null),
                'descricao' => self::nullableString(isset($row['descricao']) ? $row['descricao'] : null),
                'valor_atual' => self::normalizeOptionalDecimal(isset($row['valor_atual']) ? $row['valor_atual'] : null, 'valor_atual'),
            ]);
        }
    }

    /**
     * @param Cotista $cotista
     * @param mixed $list
     */
    private static function replaceParticipacoes(Cotista $cotista, $list)
    {
        $cotista->participacoes()->delete();
        if (! is_array($list)) {
            return;
        }

        foreach ($list as $row) {
            if (! is_array($row) || self::rowEmpty($row)) {
                continue;
            }
            $percentual = self::normalizeOptionalDecimal(
                isset($row['percentual_capital']) ? $row['percentual_capital'] : null,
                'percentual_capital'
            );
            if ($percentual !== null && ((float) $percentual < 0 || (float) $percentual > 9999.99)) {
                throw new InvalidArgumentException('percentual_capital fora do intervalo permitido');
            }

            CotistaParticipacao::create([
                'cotista_id' => $cotista->id,
                'razao_social' => self::nullableString(isset($row['razao_social']) ? $row['razao_social'] : null),
                'cnpj' => self::nullableString(isset($row['cnpj']) ? $row['cnpj'] : null),
                'nacionalidade' => self::nullableString(isset($row['nacionalidade']) ? $row['nacionalidade'] : null),
                'percentual_capital' => $percentual,
                'instituicao_financeira' => ! empty($row['instituicao_financeira']) && self::normalizeBool($row['instituicao_financeira']),
                'giin' => self::nullableString(isset($row['giin']) ? $row['giin'] : null, 32, 'giin'),
            ]);
        }
    }

    /**
     * @param Cotista $cotista
     * @param mixed $payload
     */
    private static function syncSuitability(Cotista $cotista, $payload)
    {
        $existing = CotistaSuitability::where('cotista_id', $cotista->id)->first();

        if ($payload === null || $payload === '') {
            if ($existing) {
                $existing->respostas()->delete();
                $existing->delete();
            }
            $cotista->unsetRelation('suitability');

            return;
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException('suitability invalido');
        }

        $respostas = isset($payload['respostas']) && is_array($payload['respostas']) ? $payload['respostas'] : [];
        $mapa = [];
        foreach ($respostas as $idx => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Cada resposta de suitability deve ser um objeto');
            }
            if (self::rowEmpty($row)) {
                continue;
            }
            $pergunta = isset($row['pergunta']) ? (int) $row['pergunta'] : 0;
            $opcao = isset($row['opcao']) ? (int) $row['opcao'] : 0;
            if ($pergunta < 1 || $pergunta > CotistaSuitability::PERGUNTAS) {
                throw new InvalidArgumentException('pergunta de suitability invalida no item ' . $idx . ' (use 1 a 7)');
            }
            if ($opcao < 1 || $opcao > CotistaSuitability::OPCOES) {
                throw new InvalidArgumentException('opcao de suitability invalida na pergunta ' . $pergunta . ' (use 1 a 4)');
            }
            if (isset($mapa[$pergunta])) {
                throw new InvalidArgumentException('pergunta de suitability duplicada: ' . $pergunta);
            }
            $mapa[$pergunta] = $opcao;
        }

        $perfilInformado = array_key_exists('perfil', $payload)
            ? self::nullableString($payload['perfil'], 64, 'perfil')
            : null;
        $perfil = $perfilInformado !== null ? $perfilInformado : CotistaSuitability::atribuirPerfil($mapa);

        if (empty($mapa) && $perfil === null) {
            if ($existing) {
                $existing->respostas()->delete();
                $existing->delete();
            }
            $cotista->unsetRelation('suitability');

            return;
        }

        if (! $existing) {
            $existing = CotistaSuitability::create([
                'cotista_id' => $cotista->id,
                'perfil' => $perfil,
            ]);
        } else {
            $existing->perfil = $perfil;
            $existing->save();
            $existing->respostas()->delete();
        }

        foreach ($mapa as $pergunta => $opcao) {
            CotistaSuitabilityResposta::create([
                'cotista_suitability_id' => $existing->id,
                'pergunta' => $pergunta,
                'opcao' => $opcao,
            ]);
        }

        $cotista->unsetRelation('suitability');
    }

    /**
     * @param Cotista $cotista
     * @param mixed $payload null remove
     * @param bool $partial
     */
    private static function syncOperador(Cotista $cotista, $payload, $partial)
    {
        $existing = CotistaOperador::where('cotista_id', $cotista->id)->first();

        if ($payload === null) {
            self::deleteOperador($existing);
            $cotista->unsetRelation('operador');

            return;
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException('operador invalido');
        }

        if (! $existing && self::operadorPayloadEmpty($payload)) {
            return;
        }

        $addressId = $existing ? $existing->address_id : null;
        if (array_key_exists('endereco', $payload)) {
            $addressId = self::syncLooseAddress($addressId, $payload['endereco']);
        }

        $definitions = [
            'opera_conta_propria' => 'bool',
            'autoriza_ordens_procurador' => 'bool',
            'nome_completo' => 'string',
            'cpf' => 'string',
            'data_nascimento' => 'date',
            'descricao_poderes' => 'string',
            'e_ppe' => 'bool',
            'ppe_cargo' => 'string',
            'relacionamento_ppe' => 'bool',
            'ppe_relacionada_cargo' => 'string',
            'ppe_relacionada_nome' => 'string',
            'ppe_relacionada_cpf' => 'string',
            'ppe_relacionada_nacionalidade' => 'string',
        ];

        $fields = [
            'cotista_id' => $cotista->id,
            'address_id' => $addressId,
        ];
        foreach ($definitions as $field => $type) {
            if ($partial && $existing && ! array_key_exists($field, $payload)) {
                continue;
            }
            $raw = array_key_exists($field, $payload) ? $payload[$field] : null;
            if ($type === 'bool') {
                $fields[$field] = self::normalizeBool($raw);
            } elseif ($type === 'date') {
                $fields[$field] = self::nullableDate($field, $raw);
            } else {
                $fields[$field] = self::nullableString($raw);
            }
        }

        if ($existing) {
            $existing->fill($fields);
            $existing->save();
        } else {
            CotistaOperador::create($fields);
        }

        $cotista->unsetRelation('operador');
    }

    /**
     * @param CotistaOperador|null $operador
     */
    private static function deleteOperador($operador)
    {
        if (! $operador) {
            return;
        }
        $addressId = $operador->address_id;
        $operador->delete();
        if ($addressId) {
            Address::where('id', $addressId)->delete();
        }
    }

    /**
     * @param array $payload
     * @return bool
     */
    private static function operadorPayloadEmpty(array $payload)
    {
        foreach ($payload as $key => $value) {
            if ($key === 'endereco') {
                if (is_array($value) && ! self::addressPayloadEmpty($value)) {
                    return false;
                }
                continue;
            }
            if ($value === null || $value === '' || $value === false || $value === 0 || $value === '0' || $value === 'false') {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param Cotista $cotista
     * @param string $column
     * @param mixed $payload
     */
    private static function syncOwnedAddress(Cotista $cotista, $column, $payload)
    {
        $currentId = $cotista->$column;
        $nextId = self::syncLooseAddress($currentId, $payload);
        if ((int) $nextId !== (int) $currentId) {
            $cotista->$column = $nextId;
            $cotista->save();
        }
    }

    /**
     * @param int|null $currentId
     * @param mixed $payload
     * @return int|null
     */
    private static function syncLooseAddress($currentId, $payload)
    {
        if ($payload === null || (is_array($payload) && self::addressPayloadEmpty($payload))) {
            if ($currentId) {
                Address::where('id', $currentId)->delete();
            }

            return null;
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException('Endereco invalido');
        }

        $fields = self::addressFields($payload);
        if ($currentId) {
            $addr = Address::find($currentId);
            if ($addr) {
                $addr->fill($fields);
                $addr->save();

                return (int) $addr->id;
            }
        }

        return self::createAddressFromPayload($fields);
    }

    /**
     * @param array $payload
     * @return int|null
     */
    private static function createAddressFromPayload(array $payload)
    {
        if (self::addressPayloadEmpty($payload)) {
            return null;
        }

        $addr = new Address();
        $addr->fill(self::addressFields($payload));
        $addr->save();

        return (int) $addr->id;
    }

    /**
     * @param array $payload
     * @return bool
     */
    private static function addressPayloadEmpty(array $payload)
    {
        $fields = self::addressFields($payload);
        foreach ($fields as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $payload
     * @return array
     */
    private static function addressFields(array $payload)
    {
        if (! isset($payload['estado']) && isset($payload['uf'])) {
            $payload['estado'] = $payload['uf'];
        }
        if (! isset($payload['logradouro']) && isset($payload['endereco'])) {
            $payload['logradouro'] = $payload['endereco'];
        }
        if (! isset($payload['numero']) && isset($payload['n'])) {
            $payload['numero'] = $payload['n'];
        }

        return [
            'cep' => self::nullableString(isset($payload['cep']) ? $payload['cep'] : null),
            'logradouro' => self::nullableString(isset($payload['logradouro']) ? $payload['logradouro'] : null),
            'numero' => self::nullableString(isset($payload['numero']) ? $payload['numero'] : null),
            'complemento' => self::nullableString(isset($payload['complemento']) ? $payload['complemento'] : null),
            'bairro' => self::nullableString(isset($payload['bairro']) ? $payload['bairro'] : null),
            'estado' => self::nullableString(isset($payload['estado']) ? $payload['estado'] : null),
            'cidade' => self::nullableString(isset($payload['cidade']) ? $payload['cidade'] : null),
            'pais' => self::nullableString(isset($payload['pais']) ? $payload['pais'] : null),
        ];
    }

    /**
     * @param mixed $arquivos
     */
    private static function validateArquivos($arquivos)
    {
        if (! is_array($arquivos)) {
            throw new InvalidArgumentException('arquivos deve ser uma lista');
        }

        $allowed = CotistaFile::documentTypeIds();
        $seen = [];
        foreach ($arquivos as $idx => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Cada item de arquivos deve ser um objeto');
            }
            $dt = isset($item['document_type']) ? (int) $item['document_type'] : 0;
            if (! in_array($dt, $allowed, true)) {
                throw new InvalidArgumentException('document_type invalido em arquivos[' . $idx . '] (use 1 ou 2)');
            }
            if (isset($seen[$dt])) {
                throw new InvalidArgumentException('document_type duplicado: ' . $dt);
            }
            $seen[$dt] = true;

            $on = isset($item['original_name']) ? trim((string) $item['original_name']) : '';
            if ($on === '') {
                throw new InvalidArgumentException('original_name e obrigatorio para document_type ' . $dt);
            }

            $b64 = isset($item['content_base64']) ? $item['content_base64'] : (isset($item['base64']) ? $item['base64'] : null);
            if ($b64 === null || (is_string($b64) && trim($b64) === '')) {
                throw new InvalidArgumentException('content_base64 (ou base64) e obrigatorio para document_type ' . $dt);
            }
        }
    }

    /**
     * @param Cotista $cotista
     * @param array $arquivos
     */
    private static function syncArquivos(Cotista $cotista, array $arquivos)
    {
        $maxBytes = 20 * 1024 * 1024;
        $created = [];

        try {
            foreach ($arquivos as $item) {
                $dt = (int) $item['document_type'];
                $on = trim((string) $item['original_name']);
                $b64 = isset($item['content_base64']) ? $item['content_base64'] : $item['base64'];
                $binary = self::decodeBase64Payload($b64);
                if (strlen($binary) > $maxBytes) {
                    throw new InvalidArgumentException('Arquivo excede 20MB (document_type ' . $dt . ')');
                }

                $extHint = null;
                if (! empty($item['type'])) {
                    $extHint = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $item['type']));
                    if ($extHint === '') {
                        $extHint = null;
                    }
                }

                $created[] = self::upsertArquivo($cotista, $dt, $on, $binary, $extHint);
            }
        } catch (\Exception $e) {
            foreach ($created as $file) {
                if ($file && $file->exists) {
                    $file->deletePhysicalFile();
                    $file->forceDelete();
                }
            }
            throw $e;
        }
    }

    /**
     * @param Cotista $cotista
     * @param int $documentType
     * @param string $originalName
     * @param string $binary
     * @param string|null $extHint
     * @return CotistaFile
     */
    private static function upsertArquivo(Cotista $cotista, $documentType, $originalName, $binary, $extHint = null)
    {
        $existingActive = CotistaFile::where('cotista_id', $cotista->id)
            ->where('document_type', (int) $documentType)
            ->first();

        if ($existingActive) {
            $existingActive->delete();
        }

        $created = CotistaFile::storeFromBinary($cotista->id, $documentType, $originalName, $binary, $extHint);
        self::clearArquivoInconsistenciaAfterUpload($cotista, (int) $documentType);

        return $created;
    }

    /**
     * @param Cotista $cotista
     * @param int $documentType
     */
    private static function clearArquivoInconsistenciaAfterUpload(Cotista $cotista, $documentType)
    {
        $campo = CotistaFile::inconsistenciaCampoForDocumentType($documentType);
        CotistaInconsistencia::where('cotista_id', $cotista->id)
            ->where('campo_inconsistente', $campo)
            ->delete();

    }

    /**
     * @param mixed $raw
     * @return string
     */
    private static function decodeBase64Payload($raw)
    {
        if (! is_string($raw) || trim($raw) === '') {
            throw new InvalidArgumentException('Arquivo em base64 invalido');
        }

        $raw = trim($raw);
        if (preg_match('#^data:[^;]+;base64,#i', $raw)) {
            $raw = preg_replace('#^data:[^;]+;base64,#i', '', $raw);
        }
        $raw = str_replace(["\r", "\n", "\t", ' '], '', $raw);

        $binary = base64_decode($raw, true);
        if ($binary === false) {
            throw new InvalidArgumentException('Base64 invalido em um dos arquivos');
        }

        return $binary;
    }

    /**
     * @param Cotista $cotista
     * @param string|null $status
     * @param string|null $referenceDate
     */
    private static function applySlaForStatus(Cotista $cotista, $status, $referenceDate = null)
    {
        $referenceDate = $referenceDate ?: date('Y-m-d');

        if ($status === Cotista::STATUS_APROVADO && $cotista->prazo_atualizacao_cadastral) {
            $sla = Cotista::computeSlaDeadlineFromMonths((int) $cotista->prazo_atualizacao_cadastral, $referenceDate);
        } else {
            $sla = Cotista::computeSlaForStatus($status, $referenceDate);
        }

        if ($sla !== null) {
            $cotista->sla = $sla;
        }
    }

    /**
     * @param Cotista $cotista
     * @return array
     */
    private static function snapshotForAudit(Cotista $cotista)
    {
        $snapshot = self::toApiArray($cotista);
        unset($snapshot['historico'], $snapshot['inconsistencias'], $snapshot['arquivos']);

        return $snapshot;
    }

    /**
     * @param array $antes
     * @param array $depois
     * @param array $data
     * @param string $tipo
     * @return array
     */
    private static function buildAlteracoes(array $antes, array $depois, array $data, $tipo)
    {
        $alteracoes = [];
        $keys = $tipo === 'patch'
            ? array_keys($data)
            : array_keys($depois);

        foreach ($keys as $key) {
            if (in_array($key, ['id', 'payload', 'cotista', 'enviar', 'historico', 'inconsistencias'], true)) {
                continue;
            }
            if ($key === 'arquivos') {
                if (array_key_exists('arquivos', $data)) {
                    $alteracoes['arquivos'] = ['de' => 'atualizado', 'para' => 'atualizado'];
                }
                continue;
            }
            $old = isset($antes[$key]) ? $antes[$key] : null;
            $new = isset($depois[$key]) ? $depois[$key] : null;
            if (json_encode($old) !== json_encode($new)) {
                $alteracoes[$key] = ['de' => $old, 'para' => $new];
            }
        }

        return $alteracoes;
    }

    /**
     * @param int $cotistaId
     * @param string $event
     * @param string $newStatus
     * @param string|null $oldStatus
     * @param array|null $changes
     * @param bool $systemActor
     */
    private static function recordStatusAudit($cotistaId, $event, $newStatus, $oldStatus = null, array $changes = null, $systemActor = false)
    {
        $userId = null;
        if (! $systemActor) {
            $user = User::logged();
            $userId = $user ? (int) $user->id : null;
        }

        CotistaAudit::create([
            'cotista_id' => (int) $cotistaId,
            'user_id' => $userId,
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changes' => $changes,
        ]);

        $notifica = $event === CotistaAudit::EVENT_STATUS_ALTERADO
            || $event === CotistaAudit::EVENT_CADASTRO_CRIADO;
        if ($notifica && $oldStatus !== $newStatus) {
            CotistaMailService::notifyStatusChange((int) $cotistaId, $oldStatus, $newStatus);
        }
    }

    /**
     * @param \App\CotistaAudit $audit
     * @return string
     */
    private static function resolveAuditUserName($audit)
    {
        if (! $audit->user_id) {
            return 'Sistema';
        }

        $user = $audit->user;
        if (! $user) {
            return 'Sistema';
        }

        if ($user->employee && is_string($user->employee->name) && trim($user->employee->name) !== '') {
            return trim($user->employee->name);
        }

        return $user->email ?: 'Usuario';
    }

    /**
     * @param CotistaOperador|null $operador
     * @return array|null
     */
    private static function operadorToApi($operador)
    {
        if (! $operador) {
            return null;
        }

        return [
            'id' => $operador->id,
            'opera_conta_propria' => (bool) $operador->opera_conta_propria,
            'autoriza_ordens_procurador' => (bool) $operador->autoriza_ordens_procurador,
            'nome_completo' => $operador->nome_completo,
            'cpf' => $operador->cpf,
            'data_nascimento' => self::formatDate($operador->data_nascimento),
            'descricao_poderes' => $operador->descricao_poderes,
            'e_ppe' => (bool) $operador->e_ppe,
            'ppe_cargo' => $operador->ppe_cargo,
            'relacionamento_ppe' => (bool) $operador->relacionamento_ppe,
            'ppe_relacionada_cargo' => $operador->ppe_relacionada_cargo,
            'ppe_relacionada_nome' => $operador->ppe_relacionada_nome,
            'ppe_relacionada_cpf' => $operador->ppe_relacionada_cpf,
            'ppe_relacionada_nacionalidade' => $operador->ppe_relacionada_nacionalidade,
            'endereco' => $operador->address ? $operador->address->toArray() : null,
        ];
    }

    /**
     * @param CotistaSuitability|null $suitability
     * @param array $perguntas
     * @return array|null
     */
    private static function suitabilityToApi($suitability, array $perguntas)
    {
        if (! $suitability) {
            return null;
        }

        $respostas = [];
        foreach ($suitability->respostas as $resposta) {
            $n = (int) $resposta->pergunta;
            $opcao = (int) $resposta->opcao;
            $catalogo = isset($perguntas[$n]) ? $perguntas[$n] : null;
            $respostas[] = [
                'pergunta' => $n,
                'opcao' => $opcao,
                'enunciado' => $catalogo ? $catalogo['enunciado'] : null,
                'opcao_label' => ($catalogo && isset($catalogo['opcoes'][$opcao])) ? $catalogo['opcoes'][$opcao] : null,
            ];
        }

        return [
            'id' => $suitability->id,
            'perfil' => $suitability->perfil,
            'respostas' => $respostas,
        ];
    }

    /**
     * @param array $row
     * @param string[] $ignoreKeys
     * @return bool
     */
    private static function rowEmpty(array $row, array $ignoreKeys = [])
    {
        foreach ($row as $key => $value) {
            if (in_array($key, $ignoreKeys, true)) {
                continue;
            }
            if (is_array($value)) {
                if (! self::rowEmpty($value)) {
                    return false;
                }
                continue;
            }
            if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
                continue;
            }
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $raw
     * @param int|null $max
     * @param string|null $label
     * @return string|null
     */
    private static function nullableString($raw, $max = null, $label = null)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $text = is_string($raw) ? trim($raw) : trim((string) $raw);
        if ($text === '') {
            return null;
        }
        if ($max !== null && strlen($text) > $max) {
            throw new InvalidArgumentException(($label ?: 'campo') . ' excede ' . $max . ' caracteres');
        }

        return $text;
    }

    /**
     * @param string $field
     * @param mixed $raw
     * @return string|null
     */
    private static function nullableDate($field, $raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return self::normalizeDate($field, $raw);
    }

    /**
     * @param mixed $raw
     * @param string $field
     * @return string|null
     */
    private static function normalizeOptionalDecimal($raw, $field)
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $normalized = str_replace([' ', 'R$'], '', (string) $raw);
        if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException($field . ' deve ser numerico');
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private static function formatDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }
}
