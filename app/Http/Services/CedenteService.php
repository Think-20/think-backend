<?php

namespace App\Http\Services;

use App\Address;
use App\Cedente;
use App\CedentePessoaVinculada;
use App\CedenteFile;
use App\ContaDesembolso;
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
     * Cria cedente com endereço, pessoas vinculadas (partes + avalistas) e contas de desembolso.
     *
     * @return Cedente
     */
    public static function create(array $data)
    {
        $data = self::normalizePayload($data);

        self::validateArquivosObrigatorios(isset($data['arquivos']) ? $data['arquivos'] : null);

        return DB::transaction(function () use ($data) {
            if (! isset($data['nome'], $data['documento']) || $data['nome'] === '' || $data['documento'] === '') {
                throw new InvalidArgumentException('Nome e documento do cedente sao obrigatorios');
            }

            $addressId = self::createAddressFromPayload(isset($data['endereco']) ? $data['endereco'] : null);

            $cedente = new Cedente();
            $cedente->fill([
                'nome' => $data['nome'],
                'documento' => $data['documento'],
                'email' => isset($data['email']) ? $data['email'] : null,
                'faturamento_anual' => self::normalizeOptionalDecimal(isset($data['faturamento_anual']) ? $data['faturamento_anual'] : null),
                'minimo_assinantes' => self::normalizeOptionalUInt(isset($data['minimo_assinantes']) ? $data['minimo_assinantes'] : null),
                'address_id' => $addressId,
                'sistema_financeiro_nacional' => !empty($data['sistema_financeiro_nacional']),
                'telefone' => isset($data['telefone']) ? $data['telefone'] : null,
            ]);
            $cedente->save();

            self::syncPessoas($cedente, $data);
            self::syncContas($cedente, $data);
            self::syncArquivos($cedente, $data['arquivos']);

            return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles']);
        });
    }

    /**
     * Atualiza cedente substituindo listas de pessoas e contas (snapshot enviado pelo front).
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

            $cedente = Cedente::with(['pessoasVinculadas', 'contasDesembolso'])->find($data['id']);
            if (!$cedente) {
                throw new InvalidArgumentException('Cedente nao encontrado');
            }

            if (! isset($data['nome'], $data['documento']) || $data['nome'] === '' || $data['documento'] === '') {
                throw new InvalidArgumentException('Nome e documento do cedente sao obrigatorios');
            }

            self::updateAddressForCedente($cedente, isset($data['endereco']) ? $data['endereco'] : null);

            foreach ($cedente->pessoasVinculadas as $p) {
                if ($p->address_id) {
                    Address::where('id', $p->address_id)->delete();
                }
            }
            $cedente->pessoasVinculadas()->delete();
            $cedente->contasDesembolso()->delete();

            if (array_key_exists('arquivos', $data)) {
                $cedente->load('cedenteFiles');
                foreach ($cedente->cedenteFiles as $f) {
                    $f->deletePhysicalFile();
                }
                $cedente->cedenteFiles()->delete();
                self::validateArquivosObrigatorios($data['arquivos']);
            }

            $cedente->fill([
                'nome' => $data['nome'],
                'documento' => $data['documento'],
                'email' => isset($data['email']) ? $data['email'] : null,
                'faturamento_anual' => self::normalizeOptionalDecimal(isset($data['faturamento_anual']) ? $data['faturamento_anual'] : null),
                'minimo_assinantes' => self::normalizeOptionalUInt(isset($data['minimo_assinantes']) ? $data['minimo_assinantes'] : null),
                'sistema_financeiro_nacional' => !empty($data['sistema_financeiro_nacional']),
                'telefone' => isset($data['telefone']) ? $data['telefone'] : null,
            ]);
            $cedente->save();

            self::syncPessoas($cedente, $data);
            self::syncContas($cedente, $data);

            if (array_key_exists('arquivos', $data)) {
                self::syncArquivos($cedente, $data['arquivos']);
            }

            return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles']);
        });
    }

    public static function deleteById($id)
    {
        return DB::transaction(function () use ($id) {
            $cedente = Cedente::with(['pessoasVinculadas', 'cedenteFiles'])->find($id);
            if (!$cedente) {
                return false;
            }

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
        $cedente->loadMissing(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles']);

        $labels = CedenteFile::documentTypeLabels();

        $out = [
            'id' => $cedente->id,
            'nome' => $cedente->nome,
            'documento' => $cedente->documento,
            'email' => $cedente->email,
            'faturamento_anual' => $cedente->faturamento_anual,
            'minimo_assinantes' => $cedente->minimo_assinantes,
            'sistema_financeiro_nacional' => (bool) $cedente->sistema_financeiro_nacional,
            'telefone' => $cedente->telefone,
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
                    'created_at' => $f->created_at,
                    'updated_at' => $f->updated_at,
                ];
            })->all(),
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

    private static function syncPessoas(Cedente $cedente, array $data)
    {
        $partes = isset($data['partes_relacionadas']) && is_array($data['partes_relacionadas'])
            ? $data['partes_relacionadas'] : [];
        $avalistas = isset($data['avalistas']) && is_array($data['avalistas'])
            ? $data['avalistas'] : [];

        $merged = self::mergePartesEAvalistas($partes, $avalistas);

        foreach ($merged as $item) {
            $row = $item['data'];
            if (empty($row['nome'])) {
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

    private static function syncContas(Cedente $cedente, array $data)
    {
        $list = isset($data['contas_desembolso']) && is_array($data['contas_desembolso'])
            ? $data['contas_desembolso'] : [];

        $allowed = array_keys(ContaDesembolso::tiposConta());

        foreach ($list as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (empty($c['tipo_conta']) || !in_array($c['tipo_conta'], $allowed, true)) {
                throw new InvalidArgumentException('tipo_conta invalido em conta de desembolso (use conta_corrente, conta_poupanca ou conta_salario)');
            }
            if (!isset($c['codigo_banco']) || !isset($c['agencia']) || !isset($c['numero_conta'])) {
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

    private static function validateArquivosObrigatorios($arquivos)
    {
        if (! is_array($arquivos)) {
            throw new InvalidArgumentException('E obrigatorio enviar o array arquivos com os 13 documentos (base64)');
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

                $created[] = CedenteFile::storeFromBinary($cedente->id, $dt, $on, $binary, $extHint);
            }
        } catch (\Exception $e) {
            foreach ($created as $f) {
                $f->deletePhysicalFile();
            }
            throw $e;
        }
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
