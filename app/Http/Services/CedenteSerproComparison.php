<?php

namespace App\Http\Services;

use App\Cedente;
use App\CedenteInconsistencia;
use App\SerproApi;
use Illuminate\Support\Facades\Log;

class CedenteSerproComparison
{
    /**
     * Consulta o SERPRO no cadastro e persiste divergencias em cedente_inconsistencia.
     *
     * @return array{validated: bool, inconsistencias: CedenteInconsistencia[]}
     */
    public static function compareOnCreate($cedenteId, array $data)
    {
        $documento = isset($data['documento']) ? $data['documento'] : '';

        try {
            $serpro = SerproApi::serproQsa($documento);
        } catch (\Exception $e) {
            Log::warning('CedenteSerproComparison: falha na consulta SERPRO', [
                'cedente_id' => $cedenteId,
                'documento' => $documento,
                'message' => $e->getMessage(),
            ]);

            return [
                'validated' => false,
                'inconsistencias' => [],
                'error_message' => $e->getMessage(),
            ];
        }

        $inconsistencias = self::buildInconsistencias($data, $serpro);

        CedenteInconsistencia::where('cedente_id', (int) $cedenteId)->delete();

        $saved = [];
        foreach ($inconsistencias as $item) {
            $saved[] = CedenteInconsistencia::create([
                'cedente_id' => (int) $cedenteId,
                'campo_inconsistente' => $item['campo_inconsistente'],
                'valor_serpro' => $item['valor_serpro'],
            ]);
        }

        if (! empty($saved)) {
            $cedente = Cedente::find($cedenteId);
            if ($cedente && $cedente->status !== Cedente::STATUS_INCONSISTENTE) {
                $cedente->status = Cedente::STATUS_INCONSISTENTE;
                $cedente->save();
            }
        }

        return [
            'validated' => true,
            'inconsistencias' => $saved,
        ];
    }

    /**
     * Remove inconsistencias cujo valor cadastrado passou a coincidir com o valor SERPRO.
     *
     * @return array{removed: string[], remaining: int, status_alterado: bool, status_anterior: string|null, status_novo: string|null}
     */
    public static function reconcileAfterUpdate(Cedente $cedente)
    {
        $cedente->loadMissing(['address', 'pessoasVinculadas', 'inconsistencias', 'cedenteFiles']);

        $removed = [];
        foreach ($cedente->inconsistencias as $inconsistencia) {
            if (self::isInconsistenciaResolved($cedente, $inconsistencia->campo_inconsistente, $inconsistencia->valor_serpro)) {
                $removed[] = $inconsistencia->campo_inconsistente;
                $inconsistencia->delete();
            }
        }

        $remaining = CedenteInconsistencia::where('cedente_id', $cedente->id)->count();
        $statusAnterior = $cedente->status ?: Cedente::STATUS_PENDENTE;
        $statusAlterado = false;
        $statusNovo = $statusAnterior;

        if ($remaining === 0 && $statusAnterior === Cedente::STATUS_INCONSISTENTE) {
            $cedente->status = Cedente::STATUS_PENDENTE;
            $cedente->save();
            $statusAlterado = true;
            $statusNovo = Cedente::STATUS_PENDENTE;
        }

        return [
            'removed' => $removed,
            'remaining' => $remaining,
            'status_alterado' => $statusAlterado,
            'status_anterior' => $statusAlterado ? $statusAnterior : null,
            'status_novo' => $statusAlterado ? $statusNovo : null,
        ];
    }

    /**
     * @return bool
     */
    public static function isInconsistenciaResolved(Cedente $cedente, $campo, $valorSerpro)
    {
        if (preg_match('/^arquivo\.document_type\.(\d+)$/', $campo, $matches)) {
            return self::arquivoDocumentTypeResolved($cedente, (int) $matches[1]);
        }

        if ($valorSerpro === null || $valorSerpro === '') {
            return false;
        }

        if (preg_match('/^partes_relacionadas\[(\d+)\]\.nome$/', $campo, $matches)) {
            return self::parteRelacionadaNomeResolved($cedente, (int) $matches[1], $valorSerpro);
        }

        if (preg_match('/^socios\[(\d+)\]\.nome$/', $campo, $matches)) {
            return self::socioNomeResolved($cedente, $valorSerpro);
        }

        $current = self::resolveFieldValue($cedente, $campo);

        if ($campo === 'telefone') {
            return self::telefoneMatchesSerpro($current, $valorSerpro);
        }

        if ($campo === 'documento') {
            return self::normalizeDocument($current) === self::normalizeDocument($valorSerpro);
        }

        if (strpos($campo, 'endereco.cep') === 0 || substr($campo, -4) === '.cep') {
            return self::normalizeCep($current) === self::normalizeCep($valorSerpro);
        }

        return self::normalizeText($current) === self::normalizeText($valorSerpro);
    }

    /**
     * @return array<int, array{campo_inconsistente: string, valor_serpro: string|null}>
     */
    public static function buildInconsistencias(array $data, array $serpro)
    {
        $items = [];

        self::compareScalarField($items, 'nome', self::value($data, 'nome'), self::value($serpro, 'nomeEmpresarial'));
        self::compareScalarField($items, 'documento', self::normalizeDocument(self::value($data, 'documento')), self::normalizeDocument(self::value($serpro, 'ni')));
        self::compareScalarField($items, 'email', self::value($data, 'email'), self::value($serpro, 'correioEletronico'));
        self::compareTelefone($items, self::value($data, 'telefone'), isset($serpro['telefones']) ? $serpro['telefones'] : []);

        $enderecoCadastro = isset($data['endereco']) && is_array($data['endereco']) ? $data['endereco'] : [];
        $enderecoSerpro = isset($serpro['endereco']) && is_array($serpro['endereco']) ? $serpro['endereco'] : [];

        self::compareScalarField($items, 'endereco.cep', self::value($enderecoCadastro, 'cep'), self::value($enderecoSerpro, 'cep'), 'normalizeCep');
        self::compareScalarField($items, 'endereco.logradouro', self::value($enderecoCadastro, 'logradouro'), self::value($enderecoSerpro, 'logradouro'));
        self::compareScalarField($items, 'endereco.numero', self::value($enderecoCadastro, 'numero'), self::value($enderecoSerpro, 'numero'));
        self::compareScalarField($items, 'endereco.complemento', self::value($enderecoCadastro, 'complemento'), self::value($enderecoSerpro, 'complemento'));
        self::compareScalarField($items, 'endereco.bairro', self::value($enderecoCadastro, 'bairro'), self::value($enderecoSerpro, 'bairro'));
        self::compareScalarField($items, 'endereco.estado', self::value($enderecoCadastro, 'estado'), self::value($enderecoSerpro, 'uf'));
        self::compareScalarField(
            $items,
            'endereco.cidade',
            self::value($enderecoCadastro, 'cidade'),
            self::nestedValue($enderecoSerpro, 'municipio.descricao')
        );
        self::compareScalarField(
            $items,
            'endereco.pais',
            self::value($enderecoCadastro, 'pais'),
            self::nestedValue($enderecoSerpro, 'pais.descricao')
        );

        self::comparePartesRelacionadas(
            $items,
            isset($data['partes_relacionadas']) && is_array($data['partes_relacionadas']) ? $data['partes_relacionadas'] : [],
            isset($serpro['socios']) && is_array($serpro['socios']) ? $serpro['socios'] : []
        );

        return $items;
    }

    /**
     * @param Cedente $cedente
     * @param int $documentType
     * @return bool
     */
    private static function arquivoDocumentTypeResolved(Cedente $cedente, $documentType)
    {
        $cedente->loadMissing('cedenteFiles');

        foreach ($cedente->cedenteFiles as $file) {
            if ((int) $file->document_type === (int) $documentType) {
                return true;
            }
        }

        return false;
    }

    private static function parteRelacionadaNomeResolved(Cedente $cedente, $index, $valorSerpro)
    {
        $partes = self::partesRelacionadasFromCedente($cedente);
        if (! isset($partes[$index])) {
            return false;
        }

        $nomeAtual = self::normalizeText(self::value($partes[$index], 'nome'));
        if ($nomeAtual === '') {
            return false;
        }

        foreach (self::splitSerproList($valorSerpro) as $nomeSerpro) {
            if ($nomeAtual === self::normalizeText($nomeSerpro)) {
                return true;
            }
        }

        return false;
    }

    private static function socioNomeResolved(Cedente $cedente, $valorSerpro)
    {
        $nomeSocio = self::normalizeText($valorSerpro);
        if ($nomeSocio === '') {
            return false;
        }

        foreach (self::partesRelacionadasFromCedente($cedente) as $parte) {
            if (self::normalizeText(self::value($parte, 'nome')) === $nomeSocio) {
                return true;
            }
        }

        return false;
    }

    private static function telefoneMatchesSerpro($current, $valorSerpro)
    {
        $currentNorm = self::normalizePhone($current);
        if ($currentNorm === '') {
            return false;
        }

        if ($currentNorm === self::normalizePhone($valorSerpro)) {
            return true;
        }

        foreach (self::splitSerproList($valorSerpro) as $part) {
            if ($currentNorm === self::normalizePhone($part)) {
                return true;
            }
        }

        return false;
    }

    private static function splitSerproList($valorSerpro)
    {
        $parts = array_map('trim', explode(',', (string) $valorSerpro));

        return array_values(array_filter($parts, function ($part) {
            return $part !== '';
        }));
    }

    private static function partesRelacionadasFromCedente(Cedente $cedente)
    {
        $partes = [];
        foreach ($cedente->pessoasVinculadas as $p) {
            if ($p->e_parte_relacionada) {
                $partes[] = ['nome' => $p->nome];
            }
        }

        return $partes;
    }

    private static function resolveFieldValue(Cedente $cedente, $campo)
    {
        if (strpos($campo, 'endereco.') === 0) {
            $key = substr($campo, strlen('endereco.'));
            if (! $cedente->address) {
                return null;
            }

            return $cedente->address->{$key};
        }

        if (isset($cedente->{$campo})) {
            return $cedente->{$campo};
        }

        return null;
    }

    private static function comparePartesRelacionadas(array &$items, array $partes, array $socios)
    {
        $nomesSocios = [];
        foreach ($socios as $socio) {
            if (! is_array($socio)) {
                continue;
            }
            $nome = self::normalizeText(self::value($socio, 'nome'));
            if ($nome !== '') {
                $nomesSocios[] = $nome;
            }
        }

        $nomesPartes = [];
        foreach ($partes as $index => $parte) {
            if (! is_array($parte)) {
                continue;
            }

            $nomeParte = self::normalizeText(self::value($parte, 'nome'));
            if ($nomeParte === '') {
                continue;
            }

            $nomesPartes[] = $nomeParte;

            if (! in_array($nomeParte, $nomesSocios, true)) {
                $items[] = [
                    'campo_inconsistente' => 'partes_relacionadas[' . $index . '].nome',
                    'valor_serpro' => self::formatSociosNomes($socios),
                ];
            }
        }

        foreach ($socios as $index => $socio) {
            if (! is_array($socio)) {
                continue;
            }

            $nomeSocio = self::normalizeText(self::value($socio, 'nome'));
            if ($nomeSocio === '') {
                continue;
            }

            if (! in_array($nomeSocio, $nomesPartes, true)) {
                $items[] = [
                    'campo_inconsistente' => 'socios[' . $index . '].nome',
                    'valor_serpro' => self::value($socio, 'nome'),
                ];
            }
        }
    }

    private static function compareTelefone(array &$items, $telefoneCadastro, array $telefonesSerpro)
    {
        $cadastro = self::normalizePhone($telefoneCadastro);
        $serproPhones = [];

        foreach ($telefonesSerpro as $tel) {
            if (! is_array($tel)) {
                continue;
            }
            $phone = self::normalizePhone(self::value($tel, 'ddd') . self::value($tel, 'numero'));
            if ($phone !== '') {
                $serproPhones[] = $phone;
            }
        }

        if ($cadastro === '' && empty($serproPhones)) {
            return;
        }

        if ($cadastro !== '' && in_array($cadastro, $serproPhones, true)) {
            return;
        }

        $items[] = [
            'campo_inconsistente' => 'telefone',
            'valor_serpro' => self::formatTelefonesSerpro($telefonesSerpro),
        ];
    }

    private static function compareScalarField(array &$items, $campo, $cadastro, $serpro, $normalizer = 'normalizeText')
    {
        $cadastroNorm = self::$normalizer($cadastro);
        $serproNorm = self::$normalizer($serpro);

        if ($cadastroNorm === '' && $serproNorm === '') {
            return;
        }

        if ($cadastroNorm === $serproNorm) {
            return;
        }

        $items[] = [
            'campo_inconsistente' => $campo,
            'valor_serpro' => $serpro !== null && $serpro !== '' ? (string) $serpro : null,
        ];
    }

    private static function formatSociosNomes(array $socios)
    {
        $nomes = [];
        foreach ($socios as $socio) {
            if (! is_array($socio)) {
                continue;
            }
            $nome = self::value($socio, 'nome');
            if ($nome !== null && $nome !== '') {
                $nomes[] = $nome;
            }
        }

        return empty($nomes) ? null : implode(', ', $nomes);
    }

    private static function formatTelefonesSerpro(array $telefones)
    {
        $formatted = [];
        foreach ($telefones as $tel) {
            if (! is_array($tel)) {
                continue;
            }
            $ddd = self::value($tel, 'ddd');
            $numero = self::value($tel, 'numero');
            if ($ddd !== null && $numero !== null && $ddd !== '' && $numero !== '') {
                $formatted[] = '(' . $ddd . ') ' . $numero;
            }
        }

        return empty($formatted) ? null : implode(', ', $formatted);
    }

    private static function value(array $data, $key)
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if ($value === null) {
            return null;
        }

        return is_string($value) ? trim($value) : trim((string) $value);
    }

    private static function nestedValue(array $data, $path)
    {
        $current = $data;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        if ($current === null) {
            return null;
        }

        return is_string($current) ? trim($current) : trim((string) $current);
    }

    private static function normalizeDocument($value)
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    private static function normalizeCep($value)
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    private static function normalizePhone($value)
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    private static function normalizeText($value)
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = mb_strtoupper($text, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        return $text;
    }
}
