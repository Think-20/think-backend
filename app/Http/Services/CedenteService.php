<?php

namespace App\Http\Services;

use App\Address;
use App\Cedente;
use App\CedenteAudit;
use App\CedentePessoaVinculada;
use App\CedenteFile;
use App\CedenteInconsistencia;
use App\ContaDesembolso;
use App\Fund;
use App\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CedenteService
{
    /**
     * Aceita payload plano ou aninhado em `cedente` (JSON ou form-data `cedente[nome]` etc.).
     * Listas no nível raiz (`partes_relacionadas`, `avalistas`, `contas_desembolso`) sobrescrevem as do objeto interno.
     *
     * @return array
     */
    public static function normalizePayload(array $data)
    {
        if (isset($data['payload']) && is_array($data['payload'])) {
            $inner = $data['payload'];
            unset($data['payload']);
            $data = array_merge($inner, $data);
        }

        if (isset($data['cedente']) && is_array($data['cedente'])) {
            $nested = $data['cedente'];
            unset($data['cedente']);
            $data = array_merge($nested, $data);
        }

        if (isset($data['nome']) && is_string($data['nome'])) {
            $data['nome'] = trim($data['nome']);
        }
        if (isset($data['documento'])) {
            $data['documento'] = is_string($data['documento'])
                ? trim($data['documento'])
                : trim((string) $data['documento']);
        }

        return $data;
    }

    /**
     * @param array $data
     * @return int
     */
    public static function resolveFundId(array $data)
    {
        // Remover bloco quando o front passar fund_id em todas as requisições.
        $fundIdWhenOmitted = 1;

        $raw = isset($data['fund_id']) ? $data['fund_id'] : null;
        if ($raw === null || $raw === '') {
            $id = $fundIdWhenOmitted;
        } else {
            $id = (int) $raw;
            if ($id < 1) {
                throw new InvalidArgumentException('fund_id invalido');
            }
        }

        self::assertFundExists($id);

        return $id;
    }

    /**
     * @param int $fundId
     */
    public static function assertFundExists($fundId)
    {
        if (! Fund::where('id', (int) $fundId)->exists()) {
            throw new InvalidArgumentException('Fundo nao encontrado');
        }

        $employeeId = null;
        $logged = User::logged();
        if ($logged && $logged->employee) {
            $employeeId = $logged->employee->id;
        }

        if (! Fund::employeeCanAccess($employeeId, (int) $fundId)) {
            throw new InvalidArgumentException('Fundo nao permitido para este usuario');
        }
    }

    /**
     * @param int $cedenteId
     * @param int $fundId
     * @return Cedente
     */
    public static function findCedenteForFund($cedenteId, $fundId)
    {
        $cedente = Cedente::forFund($fundId)->find((int) $cedenteId);
        if (! $cedente) {
            throw new InvalidArgumentException('Cedente nao encontrado para este fundo');
        }

        return $cedente;
    }

    /**
     * Cria cedente com endereço, pessoas vinculadas (partes + avalistas) e contas de desembolso.
     *
     * @return Cedente
     */
    public static function create(array $data)
    {
        $data = self::normalizePayload($data);
        CedentePermissionService::assertCanCreate();

        return DB::transaction(function () use ($data) {
            $fundId = self::resolveFundId($data);

            $addressId = self::createAddressFromPayload(isset($data['endereco']) ? $data['endereco'] : null);

            $nome = isset($data['nome']) && is_string($data['nome']) ? trim($data['nome']) : (isset($data['nome']) ? trim((string) $data['nome']) : '');
            $documento = isset($data['documento']) && is_string($data['documento']) ? trim($data['documento']) : (isset($data['documento']) ? trim((string) $data['documento']) : '');

            $cedente = new Cedente();
            $cedente->fill([
                'fund_id' => $fundId,
                'nome' => $nome !== '' ? $nome : null,
                'documento' => $documento !== '' ? $documento : null,
                'email' => isset($data['email']) ? $data['email'] : null,
                'faturamento_anual' => self::normalizeOptionalDecimal(isset($data['faturamento_anual']) ? $data['faturamento_anual'] : null),
                'minimo_assinantes' => self::normalizeOptionalUInt(isset($data['minimo_assinantes']) ? $data['minimo_assinantes'] : null),
                'address_id' => $addressId,
                'sistema_financeiro_nacional' => !empty($data['sistema_financeiro_nacional']),
                'telefone' => isset($data['telefone']) ? $data['telefone'] : null,
                'status' => Cedente::STATUS_RASCUNHO,
            ]);
            $cedente->save();

            self::syncPessoas($cedente, $data, false);
            self::syncContas($cedente, $data, false);

            if (array_key_exists('arquivos', $data) && is_array($data['arquivos'])) {
                self::validateArquivosObrigatorios($data['arquivos'], false);
                self::syncArquivos($cedente, $data['arquivos']);
            }

            $cedente->refresh();
            $cedente->load(['address', 'partesRelacionadas', 'contasDesembolso', 'cedenteFiles']);

            if (self::isCadastroCompleto($cedente)) {
                self::promoteAndValidateSerpro($cedente, null);
            } else {
                self::enforceDraftStatusIfIncomplete($cedente);
                self::recordStatusAudit(
                    $cedente->id,
                    CedenteAudit::EVENT_CADASTRO_CRIADO,
                    Cedente::STATUS_RASCUNHO,
                    null,
                    [
                        'descricao' => 'Cadastro criado em rascunho',
                        'nome' => $cedente->nome,
                        'documento' => $cedente->documento,
                        'fund_id' => $cedente->fund_id,
                    ]
                );
            }

            return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles', 'inconsistencias']);
        });
    }

    /**
     * Atualização completa (PUT /cedente/edit): snapshot — nome/documento obrigatórios;
     * listas de pessoas e contas sempre substituídas; arquivos só se a chave `arquivos` vier.
     *
     * @return Cedente
     */
    public static function update(array $data)
    {
        $data = self::normalizePayload($data);

        return DB::transaction(function () use ($data) {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('ID do cedente e obrigatorio para atualizacao');
            }

            $fundId = self::resolveFundId($data);
            $cedente = self::findCedenteForFund($data['id'], $fundId);
            CedentePermissionService::assertCanUpdate($cedente, $data);
            $cedente->load(['pessoasVinculadas', 'contasDesembolso', 'address', 'inconsistencias']);

            $statusAntes = $cedente->status ?: Cedente::STATUS_PENDENTE;
            $snapshotAntes = self::snapshotForAudit($cedente);

            if (array_key_exists('endereco', $data)) {
                self::updateAddressForCedente($cedente, $data['endereco']);
            }

            foreach ($cedente->pessoasVinculadas as $p) {
                if ($p->address_id) {
                    Address::where('id', $p->address_id)->delete();
                }
            }
            $cedente->pessoasVinculadas()->delete();
            $cedente->contasDesembolso()->delete();

            if (array_key_exists('arquivos', $data)) {
                self::validateArquivosObrigatorios($data['arquivos'], false);
            }

            $nome = isset($data['nome']) && is_string($data['nome']) ? trim($data['nome']) : (isset($data['nome']) ? trim((string) $data['nome']) : '');
            $documento = isset($data['documento']) && is_string($data['documento']) ? trim($data['documento']) : (isset($data['documento']) ? trim((string) $data['documento']) : '');

            $fill = [
                'nome' => $nome !== '' ? $nome : null,
                'documento' => $documento !== '' ? $documento : null,
                'email' => isset($data['email']) ? $data['email'] : null,
                'faturamento_anual' => self::normalizeOptionalDecimal(isset($data['faturamento_anual']) ? $data['faturamento_anual'] : null),
                'minimo_assinantes' => self::normalizeOptionalUInt(isset($data['minimo_assinantes']) ? $data['minimo_assinantes'] : null),
                'sistema_financeiro_nacional' => !empty($data['sistema_financeiro_nacional']),
                'telefone' => isset($data['telefone']) ? $data['telefone'] : null,
            ];
            if (array_key_exists('status', $data)) {
                $fill['status'] = self::resolveStatusForUpdate($data['status']);
            }
            $cedente->fill($fill);

            $cedente->save();

            self::syncPessoas($cedente, $data, false);
            self::syncContas($cedente, $data, false);

            if (array_key_exists('arquivos', $data)) {
                $cedente->load('cedenteFiles');
                self::validateArquivosObrigatorios($data['arquivos'], false);
                self::syncArquivos($cedente, $data['arquivos']);
            }

            $cedente->save();

            self::applyStatusAfterSave($cedente, $data, $statusAntes);

            self::finalizeCedenteUpdate($cedente, $data, $snapshotAntes, $statusAntes, 'edit');

            return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles', 'inconsistencias']);
        });
    }

    /**
     * Atualização parcial (PATCH /cedente/patch): só altera chaves presentes no JSON.
     *
     * @return Cedente
     */
    public static function patchPartial(array $data)
    {
        $data = self::normalizePayload($data);

        return DB::transaction(function () use ($data) {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('ID do cedente e obrigatorio para atualizacao');
            }

            $fundId = self::resolveFundId($data);
            $cedente = self::findCedenteForFund($data['id'], $fundId);
            CedentePermissionService::assertCanUpdate($cedente, $data);
            $cedente->load(['pessoasVinculadas.address', 'contasDesembolso', 'address', 'inconsistencias']);

            $statusAntes = $cedente->status ?: Cedente::STATUS_PENDENTE;
            $snapshotAntes = self::snapshotForAudit($cedente);

            if (array_key_exists('nome', $data)) {
                $nome = is_string($data['nome']) ? trim($data['nome']) : trim((string) $data['nome']);
                $cedente->nome = $nome === '' ? null : $nome;
            }

            if (array_key_exists('documento', $data)) {
                $documento = is_string($data['documento']) ? trim($data['documento']) : trim((string) $data['documento']);
                $cedente->documento = $documento === '' ? null : $documento;
            }

            if (array_key_exists('email', $data)) {
                $cedente->email = $data['email'];
            }

            if (array_key_exists('telefone', $data)) {
                $cedente->telefone = $data['telefone'];
            }

            if (array_key_exists('faturamento_anual', $data)) {
                $cedente->faturamento_anual = self::normalizeOptionalDecimal($data['faturamento_anual']);
            }

            if (array_key_exists('minimo_assinantes', $data)) {
                $cedente->minimo_assinantes = self::normalizeOptionalUInt($data['minimo_assinantes']);
            }

            if (array_key_exists('sistema_financeiro_nacional', $data)) {
                $cedente->sistema_financeiro_nacional = !empty($data['sistema_financeiro_nacional']);
            }

            if (array_key_exists('status', $data)) {
                $cedente->status = self::resolveStatusForUpdate($data['status']);
            }

            if (array_key_exists('endereco', $data)) {
                self::updateAddressForCedente($cedente, $data['endereco']);
            }

            if (array_key_exists('partes_relacionadas', $data) || array_key_exists('avalistas', $data)) {
                self::replacePessoasVinculadas($cedente, $data);
            }

            if (array_key_exists('contas_desembolso', $data)) {
                $cedente->contasDesembolso()->delete();
                self::syncContas($cedente, $data, false);
            }

            if (array_key_exists('arquivos', $data)) {
                self::validateArquivosObrigatorios($data['arquivos'], false);
                self::syncArquivos($cedente, $data['arquivos']);
            }

            $cedente->save();

            self::applyStatusAfterSave($cedente, $data, $statusAntes);

            self::finalizeCedenteUpdate($cedente, $data, $snapshotAntes, $statusAntes, 'patch');

            return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles', 'inconsistencias']);
        });
    }

    /**
     * Reconcilia inconsistencias SERPRO, registra historico de alteracao e status.
     *
     * @param Cedente $cedente
     * @param array $data
     * @param array $snapshotAntes
     * @param string $statusAntes
     * @param string $tipo patch|edit
     */
    private static function finalizeCedenteUpdate(Cedente $cedente, array $data, array $snapshotAntes, $statusAntes, $tipo)
    {
        $cedente->refresh();
        $cedente->load(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'inconsistencias']);

        $reconcileResult = [
            'removed' => [],
            'remaining' => 0,
            'status_alterado' => false,
            'status_anterior' => null,
            'status_novo' => null,
        ];

        // TODO: reativar quando o front estiver preparado para inconsistencias SERPRO.
        // if ($cedente->status !== Cedente::STATUS_RASCUNHO) {
        //     $reconcileResult = CedenteSerproComparison::reconcileAfterUpdate($cedente);
        //     $cedente->refresh();
        //     $cedente->load(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'inconsistencias']);
        //
        //     // Reconciliacao pode devolver o status (ex.: pendente pedido com
        //     // inconsistencias abertas → inconsistente). Ajusta SLA do status final.
        //     if ($reconcileResult['status_alterado'] && $reconcileResult['status_novo']) {
        //         self::applySlaForStatus($cedente, $reconcileResult['status_novo']);
        //         $cedente->save();
        //
        //         // Evita historico com ida temporaria (inconsistente → pendente → inconsistente).
        //         if ($reconcileResult['status_novo'] === $statusAntes) {
        //             $reconcileResult['status_alterado'] = false;
        //             $reconcileResult['status_anterior'] = null;
        //             $reconcileResult['status_novo'] = null;
        //         }
        //     }
        // }

        $snapshotDepois = self::snapshotForAudit($cedente);
        $alteracoes = self::buildAlteracoesFromPayload($snapshotAntes, $snapshotDepois, $data, $tipo);
        $statusAtual = $cedente->status ?: Cedente::STATUS_PENDENTE;

        $auditChanges = [
            'descricao' => 'Cadastro atualizado',
            'tipo_atualizacao' => $tipo,
            'alteracoes' => $alteracoes,
            'inconsistencias_resolvidas' => $reconcileResult['removed'],
            'inconsistencias_restantes' => $reconcileResult['remaining'],
        ];

        $manteveInconsistentePorPendencias = false;
        if (array_key_exists('status', $data) && $reconcileResult['remaining'] > 0) {
            $requestedStatus = self::normalizeStatusValue($data['status']);
            if ($requestedStatus === Cedente::STATUS_PENDENTE
                && $statusAtual === Cedente::STATUS_INCONSISTENTE) {
                $manteveInconsistentePorPendencias = true;
                $auditChanges['descricao'] = 'Cadastro mantido inconsistente: ainda ha inconsistencias abertas';
                $auditChanges['status_solicitado'] = Cedente::STATUS_PENDENTE;
            }
        }

        if (! empty($alteracoes) || ! empty($reconcileResult['removed']) || $statusAtual !== $statusAntes || $reconcileResult['status_alterado'] || $manteveInconsistentePorPendencias) {
            self::recordStatusAudit(
                $cedente->id,
                CedenteAudit::EVENT_CEDENTE_ATUALIZADO,
                $statusAtual,
                $statusAntes,
                $auditChanges
            );
        }

        if ($reconcileResult['status_alterado']) {
            self::recordStatusAudit(
                $cedente->id,
                CedenteAudit::EVENT_STATUS_ALTERADO,
                $reconcileResult['status_novo'],
                $reconcileResult['status_anterior'],
                [
                    'descricao' => self::auditDescricaoStatusAlterado($reconcileResult['status_anterior'], $reconcileResult['status_novo']),
                ]
            );
        } elseif ($statusAtual !== $statusAntes && ! $reconcileResult['status_alterado']) {
            self::recordStatusAudit(
                $cedente->id,
                CedenteAudit::EVENT_STATUS_ALTERADO,
                $statusAtual,
                $statusAntes,
                [
                    'descricao' => self::auditDescricaoStatusAlterado($statusAntes, $statusAtual),
                ]
            );
        }
    }

    /**
     * Substitui pessoas vinculadas; lista omitida no PATCH é preservada a partir do banco.
     */
    private static function replacePessoasVinculadas(Cedente $cedente, array $data)
    {
        $partes = array_key_exists('partes_relacionadas', $data)
            ? (is_array($data['partes_relacionadas']) ? $data['partes_relacionadas'] : [])
            : self::pessoasVinculadasAsInput($cedente, true, false);

        $avalistas = array_key_exists('avalistas', $data)
            ? (is_array($data['avalistas']) ? $data['avalistas'] : [])
            : self::pessoasVinculadasAsInput($cedente, false, true);

        foreach ($cedente->pessoasVinculadas as $p) {
            if ($p->address_id) {
                Address::where('id', $p->address_id)->delete();
            }
        }
        $cedente->pessoasVinculadas()->delete();

        self::syncPessoas($cedente, [
            'partes_relacionadas' => $partes,
            'avalistas' => $avalistas,
        ], false);
    }

    /**
     * @param bool $partes
     * @param bool $avalistas
     * @return array
     */
    private static function pessoasVinculadasAsInput(Cedente $cedente, $partes, $avalistas)
    {
        $out = [];
        foreach ($cedente->pessoasVinculadas as $p) {
            if ($partes && ! $p->e_parte_relacionada) {
                continue;
            }
            if ($avalistas && ! $p->e_avalista) {
                continue;
            }
            $out[] = self::pessoaVinculadaToInput($p);
        }

        return $out;
    }

    /**
     * @return array
     */
    private static function pessoaVinculadaToInput(CedentePessoaVinculada $p)
    {
        return [
            'nome' => $p->nome,
            'tipo_parte_relacionada' => $p->tipo_parte_relacionada,
            'nacionalidade' => $p->nacionalidade,
            'email' => $p->email,
            'cpf' => $p->cpf,
            'telefone' => $p->telefone,
            'beneficiario_final' => $p->beneficiario_final,
            'assinante_operacao' => $p->assinante_operacao,
            'assinante_obrigatorio' => $p->assinante_obrigatorio,
            'estado_civil' => $p->estado_civil,
            'regime_casamento' => $p->regime_casamento,
            'profissao' => $p->profissao,
            'endereco' => $p->address ? $p->address->toArray() : null,
        ];
    }

    public static function deleteById($id, $fundId)
    {
        CedentePermissionService::assertCanDelete();

        return DB::transaction(function () use ($id, $fundId) {
            try {
                $cedente = self::findCedenteForFund($id, $fundId);
            } catch (InvalidArgumentException $e) {
                return false;
            }

            $cedente->load(['pessoasVinculadas', 'cedenteFiles']);

            foreach ($cedente->cedenteFiles as $f) {
                $f->deletePhysicalFile();
            }

            $addressIds = [];
            if ($cedente->address_id) {
                $addressIds[] = (int) $cedente->address_id;
            }
            foreach ($cedente->pessoasVinculadas as $p) {
                if ($p->address_id) {
                    $addressIds[] = (int) $p->address_id;
                }
            }

            $cedente->delete();

            if (!empty($addressIds)) {
                Address::whereIn('id', array_unique($addressIds))->delete();
            }

            return true;
        });
    }

    public static function toApiArray(Cedente $cedente)
    {
        $cedente->loadMissing(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles', 'inconsistencias', 'audits.user.employee']);

        $labels = CedenteFile::documentTypeLabels();

        $out = [
            'id' => $cedente->id,
            'fund_id' => $cedente->fund_id,
            'nome' => $cedente->nome,
            'documento' => $cedente->documento,
            'status' => $cedente->status ?: Cedente::STATUS_PENDENTE,
            'sla' => self::formatSlaForApi($cedente->sla),
            'email' => $cedente->email,
            'faturamento_anual' => $cedente->faturamento_anual,
            'minimo_assinantes' => $cedente->minimo_assinantes,
            'sistema_financeiro_nacional' => (bool) $cedente->sistema_financeiro_nacional,
            'telefone' => $cedente->telefone,
            'observacao' => $cedente->observacao,
            'limite_aprovado' => $cedente->limite_aprovado !== null ? (string) $cedente->limite_aprovado : null,
            'prazo_atualizacao_cadastral' => $cedente->prazo_atualizacao_cadastral !== null ? (int) $cedente->prazo_atualizacao_cadastral : null,
            'endereco' => $cedente->address ? $cedente->address->toArray() : null,
            'partes_relacionadas' => [],
            'avalistas' => [],
            'contas_desembolso' => $cedente->contasDesembolso->map(function ($c) {
                return $c->toArray();
            })->values()->all(),
            'arquivos' => $cedente->cedenteFiles->sortBy('document_type')->values()->map(function ($f) use ($labels) {
                return [
                    'id' => $f->id,
                    'document_type' => $f->document_type,
                    'document_type_label' => isset($labels[$f->document_type]) ? $labels[$f->document_type] : null,
                    'original_name' => $f->original_name,
                    'name' => $f->name,
                    'type' => $f->type,
                    'valido' => (bool) $f->valido,
                    'status_arquivo' => CedenteFile::statusFromValido((bool) $f->valido),
                    'created_at' => $f->created_at,
                    'updated_at' => $f->updated_at,
                ];
            })->all(),
            'inconsistencias' => self::inconsistenciasToApiArray($cedente),
            'historico' => self::historicoToApiArray($cedente),
        ];

        foreach ($cedente->pessoasVinculadas as $p) {
            $block = $p->toArray();
            $block['endereco'] = $p->address ? $p->address->toArray() : null;
            if ($p->e_parte_relacionada) {
                $out['partes_relacionadas'][] = $block;
            }
            if ($p->e_avalista) {
                $out['avalistas'][] = $block;
            }
        }

        return $out;
    }

    /**
     * Linhas de `cedente_inconsistencia` no formato da API.
     *
     * @param Cedente $cedente
     * @return array<int, array{id: int, campo_inconsistente: string, valor_serpro: string|null}>
     */
    public static function inconsistenciasToApiArray(Cedente $cedente)
    {
        $cedente->loadMissing('inconsistencias');

        return $cedente->inconsistencias->map(function ($i) {
            return [
                'id' => $i->id,
                'campo_inconsistente' => $i->campo_inconsistente,
                'valor_serpro' => $i->valor_serpro,
            ];
        })->values()->all();
    }

    /**
     * Linhas de `cedente_audit` no formato da API (mesmo conteúdo de GET /cedentes/historico/{id}).
     *
     * @param Cedente $cedente
     * @return array<int, array<string, mixed>>
     */
    public static function historicoToApiArray(Cedente $cedente)
    {
        $cedente->loadMissing(['audits.user.employee']);

        return $cedente->audits
            ->sortByDesc('id')
            ->values()
            ->map(function ($a) {
                return self::auditRowToApiArray($a);
            })
            ->all();
    }

    /**
     * @param \App\CedenteAudit $audit
     * @return array<string, mixed>
     */
    public static function auditRowToApiArray($audit)
    {
        $audit->loadMissing(['user.employee']);
        $u = $audit->user;

        return [
            'id' => $audit->id,
            'event' => $audit->event,
            'old_status' => $audit->old_status,
            'new_status' => $audit->new_status,
            'changes' => $audit->changes,
            'user_id' => $audit->user_id,
            'user_name' => self::resolveAuditUserName($audit),
            'usuario_email' => $u ? $u->email : null,
            'created_at' => $audit->created_at ? $audit->created_at->toDateTimeString() : null,
        ];
    }

    /**
     * @param \App\CedenteAudit $audit
     * @return string
     */
    private static function resolveAuditUserName($audit)
    {
        if (! $audit->user_id) {
            return 'Sistema';
        }

        $u = $audit->user;
        if (! $u) {
            return 'Sistema';
        }

        if ($u->employee && self::isNonEmptyString($u->employee->name)) {
            return trim($u->employee->name);
        }

        return $u->email ?: 'Usuario';
    }

    /**
     * @param string|null $statusAnterior
     * @param string|null $statusNovo
     * @return string
     */
    private static function auditDescricaoStatusAlterado($statusAnterior, $statusNovo)
    {
        $de = $statusAnterior ?: '—';
        $para = $statusNovo ?: '—';

        return 'Status alterado de ' . $de . ' para ' . $para;
    }

    private static function syncPessoas(Cedente $cedente, array $data, $strict = true)
    {
        $partes = isset($data['partes_relacionadas']) && is_array($data['partes_relacionadas'])
            ? $data['partes_relacionadas'] : [];
        $avalistas = isset($data['avalistas']) && is_array($data['avalistas'])
            ? $data['avalistas'] : [];

        $merged = self::mergePartesEAvalistas($partes, $avalistas);

        foreach ($merged as $item) {
            $row = $item['data'];
            if (empty($row['nome'])) {
                if (! $strict) {
                    continue;
                }
                throw new InvalidArgumentException('Nome e obrigatorio em cada parte relacionada ou avalista');
            }

            $addrId = self::createAddressFromPayload(isset($row['endereco']) ? $row['endereco'] : null);

            CedentePessoaVinculada::create([
                'cedente_id' => $cedente->id,
                'e_parte_relacionada' => $item['e_parte_relacionada'],
                'e_avalista' => $item['e_avalista'],
                'nome' => $row['nome'],
                'tipo_parte_relacionada' => isset($row['tipo_parte_relacionada']) ? $row['tipo_parte_relacionada'] : null,
                'nacionalidade' => isset($row['nacionalidade']) ? $row['nacionalidade'] : null,
                'email' => isset($row['email']) ? $row['email'] : null,
                'cpf' => isset($row['cpf']) ? $row['cpf'] : null,
                'telefone' => isset($row['telefone']) ? $row['telefone'] : null,
                'beneficiario_final' => !empty($row['beneficiario_final']),
                'assinante_operacao' => !empty($row['assinante_operacao']),
                'assinante_obrigatorio' => !empty($row['assinante_obrigatorio']),
                'estado_civil' => isset($row['estado_civil']) ? $row['estado_civil'] : null,
                'regime_casamento' => isset($row['regime_casamento']) ? $row['regime_casamento'] : null,
                'profissao' => isset($row['profissao']) ? $row['profissao'] : null,
                'address_id' => $addrId,
            ]);
        }
    }

    private static function mergePartesEAvalistas(array $partes, array $avalistas)
    {
        $merged = [];

        foreach ($partes as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $k = self::personMergeKey($row, 'pr', $i);
            $merged[$k] = [
                'data' => $row,
                'e_parte_relacionada' => true,
                'e_avalista' => false,
            ];
        }

        foreach ($avalistas as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $k = self::personMergeKey($row, 'av', $i);
            if (isset($merged[$k])) {
                $merged[$k]['e_avalista'] = true;
                $merged[$k]['data'] = array_replace(
                    $merged[$k]['data'],
                    array_filter($row, function ($v) {
                        return $v !== null && $v !== '';
                    })
                );
            } else {
                $merged[$k] = [
                    'data' => $row,
                    'e_parte_relacionada' => false,
                    'e_avalista' => true,
                ];
            }
        }

        return array_values($merged);
    }

    private static function personMergeKey(array $row, $prefix, $index)
    {
        $cpf = isset($row['cpf']) ? preg_replace('/\D+/', '', $row['cpf']) : '';
        if ($cpf !== '') {
            return 'cpf:' . $cpf;
        }

        return $prefix . ':' . $index;
    }

    private static function syncContas(Cedente $cedente, array $data, $strict = true)
    {
        $list = isset($data['contas_desembolso']) && is_array($data['contas_desembolso'])
            ? $data['contas_desembolso'] : [];

        $allowed = array_keys(ContaDesembolso::tiposConta());

        foreach ($list as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (empty($c['tipo_conta']) || !in_array($c['tipo_conta'], $allowed, true)) {
                if (! $strict) {
                    continue;
                }
                throw new InvalidArgumentException('tipo_conta invalido em conta de desembolso (use conta_corrente, conta_poupanca ou conta_salario)');
            }
            if (!isset($c['codigo_banco']) || !isset($c['agencia']) || !isset($c['numero_conta'])) {
                if (! $strict) {
                    continue;
                }
                throw new InvalidArgumentException('codigo_banco, agencia e numero_conta sao obrigatorios em cada conta de desembolso');
            }

            ContaDesembolso::create([
                'cedente_id' => $cedente->id,
                'tipo_conta' => $c['tipo_conta'],
                'codigo_banco' => (string) $c['codigo_banco'],
                'agencia' => (string) $c['agencia'],
                'numero_conta' => (string) $c['numero_conta'],
                'digito_conta' => isset($c['digito_conta']) && $c['digito_conta'] !== '' && $c['digito_conta'] !== null
                    ? (string) $c['digito_conta']
                    : null,
                'descricao' => isset($c['descricao']) ? $c['descricao'] : null,
            ]);
        }
    }

    /**
     * @param array|null $payload
     * @return int|null
     */
    private static function createAddressFromPayload($payload)
    {
        if (!is_array($payload) || self::addressPayloadEmpty($payload)) {
            return null;
        }

        $addr = new Address();
        $addr->fill(self::addressFields($payload));
        $addr->save();

        return $addr->id;
    }

    private static function updateAddressForCedente(Cedente $cedente, $payload)
    {
        if ($payload === null) {
            return;
        }

        if (!is_array($payload) || self::addressPayloadEmpty($payload)) {
            if ($cedente->address_id) {
                Address::where('id', $cedente->address_id)->delete();
                $cedente->address_id = null;
                $cedente->save();
            }

            return;
        }

        if ($cedente->address_id) {
            $addr = Address::find($cedente->address_id);
            if ($addr) {
                $addr->fill(self::addressFields($payload));
                $addr->save();
            }

            return;
        }

        $cedente->address_id = self::createAddressFromPayload($payload);
        $cedente->save();
    }

    private static function addressPayloadEmpty(array $e)
    {
        foreach ($e as $v) {
            if ($v !== null && $v !== '') {
                return false;
            }
        }

        return true;
    }

    private static function addressFields(array $payload)
    {
        return [
            'cep' => isset($payload['cep']) ? $payload['cep'] : null,
            'logradouro' => isset($payload['logradouro']) ? $payload['logradouro'] : null,
            'numero' => isset($payload['numero']) ? $payload['numero'] : null,
            'complemento' => isset($payload['complemento']) ? $payload['complemento'] : null,
            'bairro' => isset($payload['bairro']) ? $payload['bairro'] : null,
            'estado' => isset($payload['estado']) ? $payload['estado'] : null,
            'cidade' => isset($payload['cidade']) ? $payload['cidade'] : null,
            'pais' => isset($payload['pais']) ? $payload['pais'] : null,
        ];
    }

    /**
     * @param mixed $v
     * @return int|null
     */
    private static function normalizeOptionalUInt($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return max(0, (int) $v);
        }

        return null;
    }

    /**
     * @param mixed $v
     * @return float|int|null
     */
    private static function normalizeOptionalDecimal($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return $v;
        }

        return null;
    }

    /**
     * Recalcula `sla` na criação ou quando o status muda.
     * Não altera SLA para `inconsistente` e `cancelado`; em `aprovado` usa +3 meses.
     *
     * @param string|null $status
     * @param string|null $referenceDate Y-m-d (padrão hoje)
     */
    private static function applySlaForStatus(Cedente $cedente, $status, $referenceDate = null)
    {
        $referenceDate = $referenceDate ?: date('Y-m-d');

        if ($status === Cedente::STATUS_APROVADO && $cedente->prazo_atualizacao_cadastral) {
            $sla = Cedente::computeSlaDeadlineFromMonths((int) $cedente->prazo_atualizacao_cadastral, $referenceDate);
        } else {
            $sla = Cedente::computeSlaForStatus($status, $referenceDate);
        }

        if ($sla !== null) {
            $cedente->sla = $sla;
        }
    }

    /**
     * Cedentes aprovados com SLA vencido passam para status vencido.
     *
     * @param int|null $fundId
     * @return int
     */
    public static function markExpiredApprovedAsVencido($fundId = null)
    {
        $hoje = date('Y-m-d');
        $query = Cedente::where('status', Cedente::STATUS_APROVADO)
            ->whereNotNull('sla')
            ->where('sla', '<', $hoje);

        if ($fundId !== null) {
            $query->forFund((int) $fundId);
        }

        $count = 0;
        foreach ($query->get() as $cedente) {
            $statusAntes = $cedente->status;
            $cedente->status = Cedente::STATUS_VENCIDO;
            $cedente->save();
            $count++;

            self::recordStatusAudit(
                $cedente->id,
                CedenteAudit::EVENT_STATUS_ALTERADO,
                Cedente::STATUS_VENCIDO,
                $statusAntes,
                [
                    'descricao' => 'Cadastro vencido — prazo de atualizacao cadastral expirado',
                    'sla' => self::formatSlaForApi($cedente->sla),
                ],
                true
            );
        }

        return $count;
    }

    /**
     * Avalia completude após save e promove para pendente ou mantém rascunho.
     * Validacao SERPRO desligada temporariamente (front ainda nao preparado).
     *
     * @param Cedente $cedente
     * @param array $data
     * @param string|null $statusAntes
     */
    private static function applyStatusAfterSave(Cedente $cedente, array $data, $statusAntes)
    {
        $cedente->refresh();
        $cedente->load(['address', 'partesRelacionadas', 'contasDesembolso', 'cedenteFiles']);

        if (self::isCadastroCompleto($cedente)) {
            $wasDraft = ($statusAntes === Cedente::STATUS_RASCUNHO || $statusAntes === null || $statusAntes === '');
            if ($wasDraft) {
                self::promoteAndValidateSerpro($cedente, $statusAntes);

                return;
            }

            if (array_key_exists('status', $data)) {
                $statusDepois = $cedente->status ?: Cedente::STATUS_PENDENTE;
                if ($statusAntes !== $statusDepois) {
                    self::applySlaForStatus($cedente, $statusDepois);
                    $cedente->save();
                }
            }

            return;
        }

        if (array_key_exists('status', $data)) {
            $requested = self::normalizeStatusValue($data['status']);
            if ($requested !== null && $requested !== Cedente::STATUS_RASCUNHO) {
                throw new InvalidArgumentException('Cadastro incompleto: apenas status rascunho e permitido ate completar todos os campos obrigatorios');
            }
        }

        self::enforceDraftStatusIfIncomplete($cedente);
    }

    /**
     * Verifica se o cadastro persistido atende todos os requisitos para promoção a pendente.
     *
     * @param Cedente $cedente
     * @return bool
     */
    public static function isCadastroCompleto(Cedente $cedente)
    {
        $cedente->loadMissing(['address', 'partesRelacionadas', 'contasDesembolso', 'cedenteFiles']);

        if (empty($cedente->fund_id)) {
            return false;
        }

        if (! self::isNonEmptyString($cedente->nome) || ! self::isNonEmptyString($cedente->documento)) {
            return false;
        }

        $requiredTypes = CedenteFile::requiredDocumentTypeIds();
        $fileTypes = $cedente->cedenteFiles->pluck('document_type')->unique()->values()->all();
        foreach ($requiredTypes as $type) {
            if (! in_array($type, $fileTypes, true)) {
                return false;
            }
        }

        $addr = $cedente->address;
        if (! $addr) {
            return false;
        }

        foreach (['cep', 'logradouro', 'numero', 'bairro', 'estado', 'cidade'] as $field) {
            if (! self::isNonEmptyString($addr->$field)) {
                return false;
            }
        }

        $hasParte = false;
        foreach ($cedente->partesRelacionadas as $p) {
            if (self::isNonEmptyString($p->nome)) {
                $hasParte = true;
                break;
            }
        }
        if (! $hasParte) {
            return false;
        }

        $allowed = array_keys(ContaDesembolso::tiposConta());
        foreach ($cedente->contasDesembolso as $c) {
            if (! empty($c->tipo_conta) && in_array($c->tipo_conta, $allowed, true)
                && self::isNonEmptyString($c->codigo_banco)
                && self::isNonEmptyString($c->agencia)
                && self::isNonEmptyString($c->numero_conta)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Monta payload de comparação SERPRO a partir do estado persistido do cedente.
     *
     * @param Cedente $cedente
     * @return array
     */
    private static function payloadForSerpro(Cedente $cedente)
    {
        $cedente->loadMissing(['address', 'partesRelacionadas']);

        $partes = [];
        foreach ($cedente->partesRelacionadas as $p) {
            $partes[] = [
                'nome' => $p->nome,
                'cpf' => $p->cpf,
                'email' => $p->email,
                'telefone' => $p->telefone,
            ];
        }

        return [
            'nome' => $cedente->nome,
            'documento' => $cedente->documento,
            'email' => $cedente->email,
            'telefone' => $cedente->telefone,
            'endereco' => $cedente->address ? $cedente->address->toArray() : null,
            'partes_relacionadas' => $partes,
        ];
    }

    /**
     * Promove cedente completo para pendente e aplica SLA.
     * Validacao SERPRO desligada temporariamente (front ainda nao preparado).
     *
     * @param Cedente $cedente
     * @param string|null $statusAnterior
     */
    private static function promoteAndValidateSerpro(Cedente $cedente, $statusAnterior)
    {
        $statusInicial = Cedente::STATUS_PENDENTE;
        $cedente->status = $statusInicial;
        self::applySlaForStatus($cedente, $statusInicial);
        $cedente->save();

        if ($statusAnterior === null || $statusAnterior === '') {
            self::recordStatusAudit(
                $cedente->id,
                CedenteAudit::EVENT_CADASTRO_CRIADO,
                $statusInicial,
                null,
                [
                    'descricao' => 'Cadastro criado',
                    'nome' => $cedente->nome,
                    'documento' => $cedente->documento,
                    'fund_id' => $cedente->fund_id,
                ]
            );
        } elseif ($statusAnterior === Cedente::STATUS_RASCUNHO) {
            self::recordStatusAudit(
                $cedente->id,
                CedenteAudit::EVENT_STATUS_ALTERADO,
                $statusInicial,
                $statusAnterior,
                [
                    'descricao' => self::auditDescricaoStatusAlterado($statusAnterior, $statusInicial),
                ]
            );
        }

        // TODO: reativar quando o front estiver preparado para inconsistencias SERPRO.
        /*
        self::recordSystemAudit(
            $cedente->id,
            CedenteAudit::EVENT_VALIDACAO_INICIADA,
            $statusInicial,
            null,
            [
                'descricao' => 'Inicio das validacoes automaticas do cadastro',
            ]
        );

        self::recordSystemAudit(
            $cedente->id,
            CedenteAudit::EVENT_VALIDACAO_SERPRO_CHAMADA,
            $statusInicial,
            null,
            [
                'descricao' => 'Consulta a API SERPRO (QSA) iniciada',
                'documento' => $cedente->documento,
            ]
        );

        $serproResult = CedenteSerproComparison::compareOnCreate($cedente->id, self::payloadForSerpro($cedente));
        $cedente->refresh();
        $statusAtual = $cedente->status ?: Cedente::STATUS_PENDENTE;

        if (! $serproResult['validated']) {
            self::recordSystemAudit(
                $cedente->id,
                CedenteAudit::EVENT_VALIDACAO_SERPRO_ERRO,
                $statusAtual,
                $statusInicial,
                [
                    'descricao' => 'Erro ao consultar a API SERPRO',
                    'erro' => isset($serproResult['error_message']) ? $serproResult['error_message'] : 'Falha na consulta',
                ]
            );

            return;
        }

        $inconsistencias = isset($serproResult['inconsistencias']) ? $serproResult['inconsistencias'] : [];
        $validationChanges = self::buildSerproValidationChanges($inconsistencias, $statusInicial, $statusAtual);

        if (empty($inconsistencias)) {
            $validationChanges['descricao'] = 'Validacao SERPRO concluida sem inconsistencias';
        } else {
            $validationChanges['descricao'] = 'Validacao SERPRO concluida com inconsistencias';
        }

        self::recordSystemAudit(
            $cedente->id,
            CedenteAudit::EVENT_VALIDACAO_SERPRO,
            $statusAtual,
            $statusInicial,
            $validationChanges
        );

        if ($statusAtual !== $statusInicial) {
            self::recordSystemAudit(
                $cedente->id,
                CedenteAudit::EVENT_STATUS_ALTERADO,
                $statusAtual,
                $statusInicial,
                [
                    'descricao' => self::auditDescricaoStatusAlterado($statusInicial, $statusAtual),
                    'motivo' => 'inconsistencias_serpro',
                ]
            );
        }
        */
    }

    /**
     * Força status rascunho e limpa SLA quando o cadastro está incompleto.
     *
     * @param Cedente $cedente
     */
    private static function enforceDraftStatusIfIncomplete(Cedente $cedente)
    {
        $cedente->status = Cedente::STATUS_RASCUNHO;
        $cedente->sla = null;
        $cedente->save();
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private static function isNonEmptyString($value)
    {
        return is_string($value) && trim($value) !== '';
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

        $s = is_string($raw) ? trim($raw) : trim((string) $raw);

        return $s === '' ? null : $s;
    }

    /**
     * @param mixed $sla
     * @return string|null
     */
    private static function formatSlaForApi($sla)
    {
        if ($sla === null || $sla === '') {
            return null;
        }

        if ($sla instanceof \DateTimeInterface) {
            return $sla->format('Y-m-d');
        }

        return substr((string) $sla, 0, 10);
    }

    /**
     * Atualiza cedentes existentes sem SLA (útil após deploy).
     *
     * @return int quantidade atualizada
     */
    public static function backfillMissingSla()
    {
        $count = 0;
        Cedente::whereNull('sla')->orderBy('id')->chunk(100, function ($cedentes) use (&$count) {
            foreach ($cedentes as $cedente) {
                self::applySlaForStatus(
                    $cedente,
                    $cedente->status ?: Cedente::STATUS_PENDENTE,
                    $cedente->created_at ? $cedente->created_at->format('Y-m-d') : null
                );
                if ($cedente->sla !== null) {
                    $cedente->save();
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * @param mixed $raw
     * @return string
     */
    private static function resolveStatusForCreate($raw)
    {
        if ($raw === null || $raw === '') {
            return Cedente::STATUS_RASCUNHO;
        }

        $s = is_string($raw) ? trim($raw) : trim((string) $raw);
        if (! Cedente::isCadastroStatus($s)) {
            throw new InvalidArgumentException('Status do cadastro invalido');
        }

        return $s;
    }

    /**
     * @param mixed $raw
     * @return string
     */
    private static function resolveStatusForUpdate($raw)
    {
        if ($raw === null || $raw === '') {
            throw new InvalidArgumentException('Status do cadastro invalido');
        }

        $s = is_string($raw) ? trim($raw) : trim((string) $raw);
        if (! Cedente::isCadastroStatus($s)) {
            throw new InvalidArgumentException('Status do cadastro invalido');
        }

        return $s;
    }

    /**
     * @param array $inconsistencias
     * @param string $statusInicial
     * @param string $statusAtual
     * @return array
     */
    private static function buildSerproValidationChanges(array $inconsistencias, $statusInicial, $statusAtual)
    {
        $campos = [];
        foreach ($inconsistencias as $item) {
            if (is_object($item) && isset($item->campo_inconsistente)) {
                $campos[] = $item->campo_inconsistente;
            }
        }

        return [
            'inconsistencias_count' => count($inconsistencias),
            'campos_inconsistentes' => $campos,
            'status_antes_validacao' => $statusInicial,
            'status_apos_validacao' => $statusAtual,
        ];
    }

    /**
     * @return array
     */
    private static function snapshotForAudit(Cedente $cedente)
    {
        $cedente->loadMissing(['address', 'pessoasVinculadas.address', 'contasDesembolso']);

        $partes = [];
        $avalistas = [];
        foreach ($cedente->pessoasVinculadas as $p) {
            $block = self::pessoaVinculadaToInput($p);
            if ($p->e_parte_relacionada) {
                $partes[] = $block;
            }
            if ($p->e_avalista) {
                $avalistas[] = $block;
            }
        }

        return [
            'nome' => $cedente->nome,
            'documento' => $cedente->documento,
            'email' => $cedente->email,
            'telefone' => $cedente->telefone,
            'faturamento_anual' => $cedente->faturamento_anual,
            'minimo_assinantes' => $cedente->minimo_assinantes,
            'observacao' => $cedente->observacao,
            'limite_aprovado' => $cedente->limite_aprovado,
            'prazo_atualizacao_cadastral' => $cedente->prazo_atualizacao_cadastral,
            'sistema_financeiro_nacional' => (bool) $cedente->sistema_financeiro_nacional,
            'status' => $cedente->status ?: Cedente::STATUS_PENDENTE,
            'endereco' => $cedente->address ? $cedente->address->toArray() : null,
            'partes_relacionadas' => $partes,
            'avalistas' => $avalistas,
            'contas_desembolso' => $cedente->contasDesembolso->map(function ($c) {
                return $c->toArray();
            })->values()->all(),
        ];
    }

    /**
     * @param array $antes
     * @param array $depois
     * @param array $data
     * @param string $tipo
     * @return array
     */
    private static function buildAlteracoesFromPayload(array $antes, array $depois, array $data, $tipo)
    {
        $alteracoes = [];
        $keys = $tipo === 'patch'
            ? array_diff(array_keys($data), ['id', 'fund_id', 'payload', 'cedente'])
            : array_keys($depois);

        foreach ($keys as $key) {
            if ($key === 'arquivos') {
                if (array_key_exists('arquivos', $data)) {
                    $alteracoes['arquivos'] = [
                        'de' => 'atualizado',
                        'para' => 'atualizado',
                    ];
                }
                continue;
            }

            if ($key === 'endereco' && is_array(isset($data['endereco']) ? $data['endereco'] : null)) {
                foreach ($data['endereco'] as $enderecoKey => $_) {
                    $path = 'endereco.' . $enderecoKey;
                    $old = self::nestedSnapshotValue($antes, $path);
                    $new = self::nestedSnapshotValue($depois, $path);
                    if ($old !== $new) {
                        $alteracoes[$path] = ['de' => $old, 'para' => $new];
                    }
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
     * @param array $snapshot
     * @param string $path
     * @return mixed
     */
    private static function nestedSnapshotValue(array $snapshot, $path)
    {
        $current = $snapshot;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Histórico de status no cadastro (`cedente_audit`). Usuário: header User / user_id (User::logged()).
     *
     * @param int $cedenteId
     * @param string $event
     * @param string $newStatus
     * @param string|null $oldStatus
     * @param array|null $changes
     * @param bool $systemActor Quando true, grava sem user_id (responsável = Sistema).
     */
    private static function recordStatusAudit($cedenteId, $event, $newStatus, $oldStatus = null, array $changes = null, $systemActor = false)
    {
        $userId = null;
        if (! $systemActor) {
            $user = User::logged();
            $userId = $user ? (int) $user->id : null;
        }

        CedenteAudit::create([
            'cedente_id' => (int) $cedenteId,
            'user_id' => $userId,
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changes' => $changes,
        ]);
    }

    /**
     * @param int $cedenteId
     * @param string $event
     * @param string $newStatus
     * @param string|null $oldStatus
     * @param array|null $changes
     */
    private static function recordSystemAudit($cedenteId, $event, $newStatus, $oldStatus = null, array $changes = null)
    {
        self::recordStatusAudit($cedenteId, $event, $newStatus, $oldStatus, $changes, true);
    }

    private static function validateArquivosObrigatorios($arquivos, $requireAll = true)
    {
        if (! is_array($arquivos)) {
            if ($requireAll) {
                throw new InvalidArgumentException('E obrigatorio enviar o array arquivos com os 13 documentos (base64)');
            }

            return;
        }

        $required = CedenteFile::requiredDocumentTypeIds();
        $labels = CedenteFile::documentTypeLabels();
        $seen = [];

        foreach ($arquivos as $idx => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Cada item de arquivos deve ser um objeto');
            }

            $dt = isset($item['document_type']) ? (int) $item['document_type'] : 0;
            if (! in_array($dt, $required, true)) {
                throw new InvalidArgumentException('document_type invalido em arquivos[' . $idx . '] (use 1 a 13)');
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

        foreach ($required as $r) {
            if (! isset($seen[$r])) {
                if (! $requireAll) {
                    continue;
                }
                $label = isset($labels[$r]) ? $labels[$r] : '';
                throw new InvalidArgumentException('Faltou arquivo para document_type ' . $r . ($label !== '' ? ' (' . $label . ')' : ''));
            }
        }
    }

    private static function syncArquivos(Cedente $cedente, array $arquivos)
    {
        $maxBytes = 20 * 1024 * 1024;
        $created = [];

        try {
            foreach ($arquivos as $item) {
                if (! is_array($item)) {
                    continue;
                }

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

                $created[] = self::upsertArquivo($cedente, $dt, $on, $binary, $extHint);
            }
        } catch (\Exception $e) {
            foreach ($created as $f) {
                if ($f && $f->exists) {
                    $f->deletePhysicalFile();
                    $f->forceDelete();
                }
            }
            throw $e;
        }
    }

    /**
     * Substitui ou cria arquivo por document_type (permite enviar só o arquivo faltante no PATCH).
     *
     * @param Cedente $cedente
     * @param int $documentType
     * @param string $originalName
     * @param string $binary
     * @param string|null $extHint
     * @return CedenteFile
     */
    private static function upsertArquivo(Cedente $cedente, $documentType, $originalName, $binary, $extHint = null)
    {
        // Soft-deleted (ex.: recusados) permanecem no banco para historico.
        // Apenas o arquivo ativo do document_type e substituido.
        $existingActive = CedenteFile::where('cedente_id', $cedente->id)
            ->where('document_type', (int) $documentType)
            ->first();

        if ($existingActive) {
            $existingActive->delete();
        }

        $created = CedenteFile::storeFromBinary($cedente->id, $documentType, $originalName, $binary, $extHint);

        self::clearArquivoInconsistenciaAfterUpload($cedente, (int) $documentType);

        return $created;
    }

    /**
     * Remove inconsistencia do document_type reenviado e, se nao restarem
     * inconsistencias, devolve o cedente de inconsistente para pendente.
     *
     * @param Cedente $cedente
     * @param int $documentType
     */
    private static function clearArquivoInconsistenciaAfterUpload(Cedente $cedente, $documentType)
    {
        $campo = CedenteFile::inconsistenciaCampoForDocumentType($documentType);
        CedenteInconsistencia::where('cedente_id', $cedente->id)
            ->where('campo_inconsistente', $campo)
            ->delete();

        $remaining = CedenteInconsistencia::where('cedente_id', $cedente->id)->count();
        if ($remaining > 0) {
            return;
        }

        $statusAntes = $cedente->status ?: Cedente::STATUS_PENDENTE;
        if ($statusAntes !== Cedente::STATUS_INCONSISTENTE) {
            return;
        }

        $cedente->status = Cedente::STATUS_PENDENTE;
        self::applySlaForStatus($cedente, Cedente::STATUS_PENDENTE);
        $cedente->save();

        $user = User::logged();
        CedenteAudit::create([
            'cedente_id' => $cedente->id,
            'user_id' => $user ? (int) $user->id : null,
            'event' => CedenteAudit::EVENT_STATUS_ALTERADO,
            'old_status' => $statusAntes,
            'new_status' => Cedente::STATUS_PENDENTE,
            'changes' => [
                'descricao' => 'Status alterado de inconsistente para pendente',
                'motivo' => 'arquivo_reenviado',
                'document_type' => $documentType,
            ],
        ]);
    }

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
}
