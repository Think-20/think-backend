<?php

namespace App\Http\Services;

use App\Cedente;
use App\CedentePessoaVinculada;
use App\ContaDesembolso;
use App\Fund;
use Illuminate\Http\Request;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Importa cadastroCedente/cedentes/cedente (XML Daycoval/Fromtis) em lote via CedenteService::create.
 */
class CedenteXmlImportService
{
    /**
     * @param Request $request
     * @return string
     */
    public static function extractXmlFromRequest(Request $request)
    {
        if ($request->hasFile('xml')) {
            $file = $request->file('xml');
            if (!$file || !$file->isValid()) {
                throw new InvalidArgumentException('Arquivo xml invalido');
            }
            $content = file_get_contents($file->getRealPath());
            if ($content === false || trim($content) === '') {
                throw new InvalidArgumentException('Arquivo xml vazio');
            }

            return $content;
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if ($file && $file->isValid()) {
                $content = file_get_contents($file->getRealPath());
                if ($content !== false && trim($content) !== '') {
                    return $content;
                }
            }
        }

        $data = $request->all();
        if (isset($data['xml']) && is_string($data['xml']) && trim($data['xml']) !== '') {
            return $data['xml'];
        }

        $raw = $request->getContent();
        if ($raw !== false && trim($raw) !== '') {
            $trimmed = ltrim($raw);
            if (isset($trimmed[0]) && $trimmed[0] === '<') {
                return $raw;
            }
        }

        throw new InvalidArgumentException('Envie o XML em multipart (campo xml ou file), no JSON (campo xml) ou como corpo application/xml');
    }

    /**
     * @param string $xml
     * @param int $fundId
     * @return array{total: int, created: int, results: array<int, array>}
     */
    public static function import($xml, $fundId)
    {
        $fundId = (int) $fundId;
        if ($fundId < 1) {
            throw new InvalidArgumentException('fund_id e obrigatorio');
        }

        CedenteService::assertFundExists($fundId);
        $fund = Fund::find($fundId);
        if (!$fund) {
            throw new InvalidArgumentException('Fundo nao encontrado');
        }

        $root = self::loadXml($xml);
        if (!isset($root->cedentes->cedente)) {
            throw new InvalidArgumentException('XML invalido: esperado cadastroCedente/cedentes/cedente');
        }

        $results = [];
        $created = 0;

        foreach ($root->cedentes->cedente as $cedenteNode) {
            $documento = self::digits(self::childText($cedenteNode, 'cnpjCpf'));
            $index = count($results);

            try {
                self::assertFundCnpjMatches($fund, $cedenteNode);
                $payload = self::mapCedenteNode($cedenteNode, $fundId);
                $cedente = CedenteService::create($payload);
                $created++;
                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'documento' => $documento !== '' ? $documento : (isset($payload['documento']) ? $payload['documento'] : null),
                    'nome' => isset($payload['nome']) ? $payload['nome'] : null,
                    'status' => $cedente->status ?: Cedente::STATUS_PENDENTE,
                    'cedente_id' => $cedente->id,
                    'data' => CedenteService::toApiArray($cedente),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'documento' => $documento !== '' ? $documento : null,
                    'nome' => self::resolveNome($cedenteNode),
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (empty($results)) {
            throw new InvalidArgumentException('Nenhum cedente encontrado no XML');
        }

        return [
            'fund_id' => $fundId,
            'total' => count($results),
            'created' => $created,
            'failed' => count($results) - $created,
            'results' => $results,
        ];
    }

    /**
     * @param string $xml
     * @return SimpleXMLElement
     */
    public static function loadXml($xml)
    {
        \libxml_use_internal_errors(true);
        $root = \simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($root === false) {
            $errors = \libxml_get_errors();
            \libxml_clear_errors();
            $msg = 'XML malformado';
            if (!empty($errors[0]->message)) {
                $msg .= ': ' . trim($errors[0]->message);
            }
            throw new InvalidArgumentException($msg);
        }

        return $root;
    }

    /**
     * @param Fund $fund
     * @param SimpleXMLElement $cedenteNode
     */
    public static function assertFundCnpjMatches(Fund $fund, SimpleXMLElement $cedenteNode)
    {
        $xmlCnpj = self::digits(self::childText($cedenteNode, 'fundo', 'cnpjFundo'));
        if ($xmlCnpj === '') {
            return;
        }

        $fundCnpj = Fund::normalizeCnpj($fund->cnpj);
        if ($fundCnpj === null || $fundCnpj === '') {
            throw new InvalidArgumentException(
                'Fundo id ' . $fund->id . ' nao possui CNPJ cadastrado para conferir com cnpjFundo do XML (' . $xmlCnpj . ')'
            );
        }

        if ($fundCnpj !== $xmlCnpj) {
            throw new InvalidArgumentException(
                'cnpjFundo do XML (' . $xmlCnpj . ') nao confere com o CNPJ do fund_id ' . $fund->id . ' (' . $fundCnpj . ')'
            );
        }
    }

    /**
     * @param SimpleXMLElement $cedenteNode
     * @param int $fundId
     * @return array
     */
    public static function mapCedenteNode(SimpleXMLElement $cedenteNode, $fundId)
    {
        $nome = self::resolveNome($cedenteNode);
        $email = self::firstNonEmpty([
            self::childText($cedenteNode, 'email'),
            self::childText($cedenteNode, 'dadosContato', 'contato', 'emailContato'),
        ]);
        $telefone = self::firstNonEmpty([
            self::childText($cedenteNode, 'telefone'),
            self::childText($cedenteNode, 'dadosContato', 'contato', 'telContato'),
        ]);

        $faturamento = self::childText($cedenteNode, 'faturamentoAnual');
        $minAssinantes = self::childText($cedenteNode, 'minAprovacao');

        $payload = [
            'fund_id' => (int) $fundId,
            'nome' => $nome !== '' ? $nome : null,
            'documento' => self::digits(self::childText($cedenteNode, 'cnpjCpf')),
            'email' => $email !== '' ? $email : null,
            'telefone' => $telefone !== '' ? $telefone : null,
            'faturamento_anual' => $faturamento !== '' ? $faturamento : null,
            'minimo_assinantes' => $minAssinantes !== '' ? (int) $minAssinantes : null,
            'endereco' => self::mapEndereco($cedenteNode),
            'partes_relacionadas' => self::mapPartesRelacionadas($cedenteNode),
            'avalistas' => self::mapAvalistas($cedenteNode),
            'contas_desembolso' => self::mapContas($cedenteNode),
        ];

        if ($payload['documento'] === '') {
            $payload['documento'] = null;
        }

        return $payload;
    }

    /**
     * @param SimpleXMLElement $cedenteNode
     * @return string
     */
    public static function resolveNome(SimpleXMLElement $cedenteNode)
    {
        return self::firstNonEmpty([
            self::childText($cedenteNode, 'dadosContato', 'contato', 'nomeContato'),
            self::childText($cedenteNode, 'nome'),
        ]);
    }

    /**
     * @param SimpleXMLElement $cedenteNode
     * @return array|null
     */
    private static function mapEndereco(SimpleXMLElement $cedenteNode)
    {
        $fields = [
            'cep' => self::digits(self::childText($cedenteNode, 'cep')),
            'logradouro' => self::childText($cedenteNode, 'endereco'),
            'numero' => self::childText($cedenteNode, 'numEndereco'),
            'complemento' => self::childText($cedenteNode, 'compEndereco'),
            'bairro' => self::childText($cedenteNode, 'bairro'),
            'estado' => strtoupper(self::childText($cedenteNode, 'uf')),
            'cidade' => self::childText($cedenteNode, 'cidade'),
        ];

        $hasAny = false;
        foreach ($fields as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                $hasAny = true;
                break;
            }
        }

        return $hasAny ? $fields : null;
    }

    /**
     * @param SimpleXMLElement $cedenteNode
     * @return array
     */
    private static function mapPartesRelacionadas(SimpleXMLElement $cedenteNode)
    {
        $partes = [];
        $seenCpfs = [];

        if (isset($cedenteNode->partesRelacionadas->parteRelacionada)) {
            foreach ($cedenteNode->partesRelacionadas->parteRelacionada as $parte) {
                $row = self::mapParteRelacionadaNode($parte);
                if ($row === null) {
                    continue;
                }
                $partes[] = $row;
                $cpf = self::digits(isset($row['cpf']) ? $row['cpf'] : '');
                if ($cpf !== '') {
                    $seenCpfs[$cpf] = true;
                }
            }
        }

        if (empty($partes) && isset($cedenteNode->representantes->representante)) {
            foreach ($cedenteNode->representantes->representante as $rep) {
                $nome = self::childText($rep, 'nomeRepresentante');
                if ($nome === '') {
                    continue;
                }
                $cpf = self::digits(self::childText($rep, 'cpfRepresentante'));
                if ($cpf !== '' && isset($seenCpfs[$cpf])) {
                    continue;
                }
                $partes[] = [
                    'nome' => $nome,
                    'cpf' => $cpf !== '' ? $cpf : null,
                    'email' => self::childText($rep, 'emailRepresentante') ?: null,
                    'tipo_parte_relacionada' => CedentePessoaVinculada::TIPO_REPRESENTANTE_LEGAL,
                    'assinante_operacao' => self::isSim(self::childText($rep, 'assinaIsoladamente')),
                ];
                if ($cpf !== '') {
                    $seenCpfs[$cpf] = true;
                }
            }
        }

        return $partes;
    }

    /**
     * @param SimpleXMLElement $parte
     * @return array|null
     */
    private static function mapParteRelacionadaNode(SimpleXMLElement $parte)
    {
        $nome = self::childText($parte, 'nomeParteRelacionada');
        if ($nome === '') {
            return null;
        }

        return [
            'nome' => $nome,
            'cpf' => self::digits(self::childText($parte, 'cnpjCpfParteRelacionada')) ?: null,
        ];
    }

    /**
     * @param SimpleXMLElement $cedenteNode
     * @return array
     */
    private static function mapAvalistas(SimpleXMLElement $cedenteNode)
    {
        $avalistas = [];

        if (!isset($cedenteNode->avalistas->avalista)) {
            return $avalistas;
        }

        foreach ($cedenteNode->avalistas->avalista as $avalista) {
            $nome = self::childText($avalista, 'nomeAvalista');
            if ($nome === '') {
                continue;
            }

            $avalistas[] = [
                'nome' => $nome,
                'cpf' => self::digits(self::childText($avalista, 'cnpjCpfAvalista')) ?: null,
                'email' => self::childText($avalista, 'email') ?: null,
            ];
        }

        return $avalistas;
    }

    /**
     * @param SimpleXMLElement $cedenteNode
     * @return array
     */
    private static function mapContas(SimpleXMLElement $cedenteNode)
    {
        $contas = [];

        if (!isset($cedenteNode->contasCorrente->contaCorrente)) {
            return $contas;
        }

        foreach ($cedenteNode->contasCorrente->contaCorrente as $conta) {
            $banco = self::childText($conta, 'banco');
            $agencia = self::childText($conta, 'agencia');
            $numero = self::childText($conta, 'contaCorrente');

            if ($banco === '' && $agencia === '' && $numero === '') {
                continue;
            }

            $contas[] = [
                'tipo_conta' => ContaDesembolso::TIPO_CONTA_CORRENTE,
                'codigo_banco' => $banco,
                'agencia' => $agencia,
                'numero_conta' => $numero,
                'descricao' => self::childText($conta, 'descricao') ?: null,
            ];
        }

        return $contas;
    }

    /**
     * Navega filhos XML por caminho (ex.: fundo, cnpjFundo).
     *
     * @param SimpleXMLElement $node
     * @param string ...$path
     * @return string
     */
    public static function childText(SimpleXMLElement $node)
    {
        $path = func_get_args();
        array_shift($path);

        $current = $node;
        foreach ($path as $segment) {
            if (!isset($current->{$segment})) {
                return '';
            }
            $current = $current->{$segment};
        }

        return trim((string) $current);
    }

    /**
     * @param array $values
     * @return string
     */
    private static function firstNonEmpty(array $values)
    {
        foreach ($values as $value) {
            $t = trim((string) $value);
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function digits($value)
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    /**
     * @param string $value
     * @return bool
     */
    private static function isSim($value)
    {
        return strtoupper(trim((string) $value)) === 'SIM';
    }
}
