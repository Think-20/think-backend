<?php

namespace App\Http\Services;

use App\Cedente;
use App\CedenteAudit;
use App\CedentePessoaVinculada;
use App\DaycovalApi;
use App\User;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Monta e envia o cadastro de cedente aprovado para o WS Daycoval/Fromtis.
 *
 * Campos obrigatorios do SOAP (conforme manual + carga real):
 * fundo, nome, porte, ramo, tipo sociedade, classRisco + dados basicos do cedente.
 * Demais tags sao preenchidas a partir do cadastro local ou defaults configuraveis.
 */
class DaycovalCedenteService
{
    /**
     * @return bool
     */
    public static function isEnabled()
    {
        $enabled = config('services.daycoval.enabled', false);
        if (is_string($enabled)) {
            return ! in_array(strtolower($enabled), ['false', '0', 'no', 'off', ''], true);
        }

        return (bool) $enabled;
    }

    /**
     * @return bool
     */
    public static function failOnError()
    {
        $flag = config('services.daycoval.fail_on_error', false);
        if (is_string($flag)) {
            return ! in_array(strtolower($flag), ['false', '0', 'no', 'off', ''], true);
        }

        return (bool) $flag;
    }

    /**
     * Envia cedente ja aprovado ao servico cadastroCedenteAprovado.
     *
     * @param Cedente $cedente
     * @return array
     */
    public static function cadastrarAprovado(Cedente $cedente)
    {
        $cedente->loadMissing([
            'fund',
            'address',
            'pessoasVinculadas',
            'contasDesembolso',
        ]);

        if ($cedente->status !== Cedente::STATUS_APROVADO) {
            throw new InvalidArgumentException('Somente cedentes com status aprovado podem ser enviados a Daycoval');
        }

        self::assertReadyForDaycoval($cedente);

        $xml = self::buildCedenteXml($cedente);

        self::recordAudit($cedente, CedenteAudit::EVENT_DAYCOVAL_CADASTRO_CHAMADA, [
            'descricao' => 'Envio do cedente ao WS Daycoval (cadastroCedenteAprovado)',
            'documento' => $cedente->documento,
            'fund_id' => $cedente->fund_id,
        ]);

        try {
            $result = DaycovalApi::cadastroCedenteAprovado($xml);
        } catch (Exception $e) {
            self::recordAudit($cedente, CedenteAudit::EVENT_DAYCOVAL_CADASTRO_ERRO, [
                'descricao' => 'Falha ao chamar WS Daycoval',
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (! empty($result['success'])) {
            self::recordAudit($cedente, CedenteAudit::EVENT_DAYCOVAL_CADASTRO, [
                'descricao' => isset($result['descricao']) ? $result['descricao'] : 'Cadastro Daycoval realizado com sucesso',
                'tipo_retorno' => isset($result['tipo_retorno']) ? $result['tipo_retorno'] : null,
                'nome_cedente' => isset($result['nome_cedente']) ? $result['nome_cedente'] : null,
                'cnpj_cedente' => isset($result['cnpj_cedente']) ? $result['cnpj_cedente'] : null,
            ]);

            return $result;
        }

        $erros = isset($result['erros']) && is_array($result['erros']) ? $result['erros'] : [];
        $msg = isset($result['descricao']) && $result['descricao'] !== ''
            ? $result['descricao']
            : 'Cadastro Daycoval rejeitado';
        if (! empty($erros)) {
            $msg .= ': ' . implode('; ', $erros);
        }

        self::recordAudit($cedente, CedenteAudit::EVENT_DAYCOVAL_CADASTRO_ERRO, [
            'descricao' => $msg,
            'tipo_retorno' => isset($result['tipo_retorno']) ? $result['tipo_retorno'] : null,
            'erros' => $erros,
        ]);

        throw new Exception($msg);
    }

    /**
     * Tenta enviar; por padrao nao relanca excecao (aprovacao local permanece).
     * Passe $respectFailOnError=true para respeitar DAYCOVAL_FAIL_ON_ERROR.
     *
     * @param Cedente $cedente
     * @param bool $respectFailOnError
     * @return array{attempted: bool, success: bool, result: array|null, error: string|null}
     */
    public static function cadastrarAprovadoSafe(Cedente $cedente, $respectFailOnError = true)
    {
        if (! self::isEnabled()) {
            return [
                'attempted' => false,
                'success' => false,
                'result' => null,
                'error' => null,
            ];
        }

        try {
            $result = self::cadastrarAprovado($cedente);

            return [
                'attempted' => true,
                'success' => true,
                'result' => $result,
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::warning('DaycovalCedenteService: falha no cadastro', [
                'cedente_id' => $cedente->id,
                'message' => $e->getMessage(),
            ]);

            if ($respectFailOnError && self::failOnError()) {
                throw $e;
            }

            return [
                'attempted' => true,
                'success' => false,
                'result' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param Cedente $cedente
     */
    private static function assertReadyForDaycoval(Cedente $cedente)
    {
        if (! $cedente->fund) {
            throw new InvalidArgumentException('Cedente sem fundo para envio Daycoval');
        }

        $cnpjFundo = self::digits($cedente->fund->cnpj);
        if ($cnpjFundo === '') {
            throw new InvalidArgumentException('Fundo sem CNPJ cadastrado (obrigatorio para Daycoval)');
        }

        if (! self::isNonEmpty($cedente->fund->name)) {
            throw new InvalidArgumentException('Fundo sem nome (obrigatorio para Daycoval)');
        }

        if (! self::isNonEmpty($cedente->nome)) {
            throw new InvalidArgumentException('Nome do cedente e obrigatorio para Daycoval');
        }

        $doc = self::digits($cedente->documento);
        if ($doc === '' || (strlen($doc) !== 11 && strlen($doc) !== 14)) {
            throw new InvalidArgumentException('Documento do cedente invalido para Daycoval (CPF/CNPJ)');
        }

        if (! $cedente->address) {
            throw new InvalidArgumentException('Endereco do cedente e obrigatorio para Daycoval');
        }

        foreach (['cep', 'logradouro', 'numero', 'bairro', 'cidade', 'estado'] as $field) {
            if (! self::isNonEmpty($cedente->address->{$field})) {
                throw new InvalidArgumentException('Endereco incompleto para Daycoval (faltando ' . $field . ')');
            }
        }

        if ($cedente->contasDesembolso->isEmpty()) {
            throw new InvalidArgumentException('Pelo menos uma conta de desembolso e obrigatoria para Daycoval');
        }
    }

    /**
     * @param Cedente $cedente
     * @return string
     */
    public static function buildCedenteXml(Cedente $cedente)
    {
        $fund = $cedente->fund;
        $address = $cedente->address;
        $doc = self::digits($cedente->documento);
        $tipoPessoa = strlen($doc) === 11 ? 'CPF' : 'CNPJ';

        $porte = self::configDefault('default_porte', '9');
        $ramo = self::configDefault('default_ramo', 'SERVICOS');
        $tipoSociedade = self::configDefault('default_tipo_sociedade', 'LTDA');
        $classRisco = self::configDefault('default_class_risco', '1');

        $faturamento = $cedente->faturamento_anual !== null && $cedente->faturamento_anual !== ''
            ? (string) $cedente->faturamento_anual
            : '0';

        $minAprovacao = $cedente->minimo_assinantes !== null && (int) $cedente->minimo_assinantes > 0
            ? (string) ((int) $cedente->minimo_assinantes)
            : '1';

        $dataContrato = $cedente->created_at
            ? $cedente->created_at->format('d/m/Y')
            : date('d/m/Y');

        $telefone = self::digits($cedente->telefone);
        $email = self::isNonEmpty($cedente->email) ? trim((string) $cedente->email) : '';

        $parts = [];
        $parts[] = '<cedente>';
        $parts[] = '<fundo>';
        $parts[] = self::tag('cnpjFundo', self::digits($fund->cnpj));
        $parts[] = self::tag('nomeFundo', $fund->name);
        $parts[] = '</fundo>';
        $parts[] = self::tag('tipoPessoa', $tipoPessoa);
        $parts[] = self::tag('cnpjCpf', $doc);
        $parts[] = self::tag('nome', $cedente->nome);
        $parts[] = self::tag('email', $email);
        $parts[] = self::tag('isentoInscricaoEstadual', 'SIM');
        $parts[] = self::tag('inscricaoEstadual', '00000000000');
        $parts[] = self::tag('inscricaoMunicipal', '');
        $parts[] = self::tag('porte', $porte);
        $parts[] = self::tag('ramodeAtividade', $ramo);
        $parts[] = self::tag('tipodeSociedade', $tipoSociedade);
        $parts[] = self::tag('faturamentoAnual', $faturamento);
        $parts[] = self::tag('conglomeradoEconomico', '');
        $parts[] = self::tag('classRisco', $classRisco);
        $parts[] = self::tag('autorizacao', 'SIM');
        $parts[] = self::tag('endereco', $address->logradouro);
        $parts[] = self::tag('numEndereco', $address->numero);
        $parts[] = self::tag('compEndereco', $address->complemento);
        $parts[] = self::tag('cep', self::digits($address->cep));
        $parts[] = self::tag('bairro', $address->bairro);
        $parts[] = self::tag('cidade', $address->cidade);
        $parts[] = self::tag('uf', strtoupper(trim((string) $address->estado)));
        $parts[] = self::tag('dataContrato', $dataContrato);
        $parts[] = self::tag('telefone', $telefone);
        $parts[] = self::tag('fax', '');
        $parts[] = self::tag('minAprovacao', $minAprovacao);
        $parts[] = self::buildContasXml($cedente);
        $parts[] = self::buildContatosXml($cedente, $email, $telefone);
        $parts[] = self::buildRepresentantesXml($cedente);
        $parts[] = self::buildAvalistasXml($cedente);
        $parts[] = self::buildPartesRelacionadasXml($cedente);
        $parts[] = '</cedente>';

        return implode('', $parts);
    }

    /**
     * @param Cedente $cedente
     * @return string
     */
    private static function buildContasXml(Cedente $cedente)
    {
        $xml = ['<contasCorrente>'];
        $first = true;
        foreach ($cedente->contasDesembolso as $conta) {
            $numero = self::digits($conta->numero_conta);
            $digito = self::digits($conta->digito_conta);
            if ($digito !== '') {
                $numero .= $digito;
            }

            $xml[] = '<contaCorrente>';
            $xml[] = self::tag('banco', self::digits($conta->codigo_banco));
            $xml[] = self::tag('agencia', self::digits($conta->agencia));
            $xml[] = self::tag('contaCorrente', $numero);
            $xml[] = self::tag(
                'descricao',
                self::isNonEmpty($conta->descricao) ? $conta->descricao : $cedente->nome
            );
            $xml[] = self::tag('padrao', $first ? 'SIM' : 'NAO');
            $xml[] = '</contaCorrente>';
            $first = false;
        }
        $xml[] = '</contasCorrente>';

        return implode('', $xml);
    }

    /**
     * @param Cedente $cedente
     * @param string $email
     * @param string $telefone
     * @return string
     */
    private static function buildContatosXml(Cedente $cedente, $email, $telefone)
    {
        $xml = ['<dadosContato>', '<contato>'];
        $xml[] = self::tag('nomeContato', $cedente->nome);
        $xml[] = self::tag('emailContato', $email);
        $xml[] = self::tag('telContato', $telefone);
        $xml[] = '</contato>';
        $xml[] = '</dadosContato>';

        return implode('', $xml);
    }

    /**
     * @param Cedente $cedente
     * @return string
     */
    private static function buildRepresentantesXml(Cedente $cedente)
    {
        $representantes = [];
        foreach ($cedente->pessoasVinculadas as $p) {
            if ((int) $p->tipo_parte_relacionada === CedentePessoaVinculada::TIPO_REPRESENTANTE_LEGAL
                || ! empty($p->assinante_operacao)) {
                $representantes[] = $p;
            }
        }

        if (empty($representantes)) {
            foreach ($cedente->pessoasVinculadas as $p) {
                if (! empty($p->e_avalista) || ! empty($p->e_parte_relacionada)) {
                    $representantes[] = $p;
                    break;
                }
            }
        }

        $xml = ['<representantes>'];
        foreach ($representantes as $p) {
            $xml[] = '<representante>';
            $xml[] = self::tag('nomeRepresentante', $p->nome);
            $xml[] = self::tag('cpfRepresentante', self::digits($p->cpf));
            $xml[] = self::tag('emailRepresentante', self::isNonEmpty($p->email) ? $p->email : ($cedente->email ?: ''));
            $xml[] = self::tag('assinaIsoladamente', ! empty($p->assinante_operacao) ? 'SIM' : 'NAO');
            $xml[] = self::tag('emiteDuplicata', 'SIM');
            $xml[] = self::tag('assinaPorEndosso', 'SIM');
            $xml[] = self::tag('assinaTermoCessao', 'SIM');
            $xml[] = '</representante>';
        }
        $xml[] = '</representantes>';

        return implode('', $xml);
    }

    /**
     * @param Cedente $cedente
     * @return string
     */
    private static function buildAvalistasXml(Cedente $cedente)
    {
        $xml = ['<avalistas>'];
        foreach ($cedente->pessoasVinculadas as $p) {
            if (empty($p->e_avalista)) {
                continue;
            }
            $cpf = self::digits($p->cpf);
            $tipo = strlen($cpf) === 14 ? 'CNPJ' : 'CPF';
            $xml[] = '<avalista>';
            $xml[] = self::tag('nomeAvalista', $p->nome);
            $xml[] = self::tag('tipoPessoaAvalista', $tipo);
            $xml[] = self::tag('cnpjCpfAvalista', $cpf);
            $xml[] = self::tag('email', self::isNonEmpty($p->email) ? $p->email : ($cedente->email ?: ''));
            $xml[] = '</avalista>';
        }
        $xml[] = '</avalistas>';

        return implode('', $xml);
    }

    /**
     * @param Cedente $cedente
     * @return string
     */
    private static function buildPartesRelacionadasXml(Cedente $cedente)
    {
        $xml = ['<partesRelacionadas>'];
        foreach ($cedente->pessoasVinculadas as $p) {
            if (empty($p->e_parte_relacionada)) {
                continue;
            }
            $cpf = self::digits($p->cpf);
            $tipo = strlen($cpf) === 14 ? 'CNPJ' : 'CPF';
            $xml[] = '<parteRelacionada>';
            $xml[] = self::tag('nomeParteRelacionada', $p->nome);
            $xml[] = self::tag('tipoPessoaParteRelacionada', $tipo);
            $xml[] = self::tag('cnpjCpfParteRelacionada', $cpf);
            $xml[] = '</parteRelacionada>';
        }
        $xml[] = '</partesRelacionadas>';

        return implode('', $xml);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return string
     */
    private static function tag($name, $value)
    {
        $text = $value === null ? '' : (string) $value;

        return '<' . $name . '>' . self::escapeXml($text) . '</' . $name . '>';
    }

    /**
     * @param string $value
     * @return string
     */
    private static function escapeXml($value)
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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
     * @param mixed $value
     * @return bool
     */
    private static function isNonEmpty($value)
    {
        return $value !== null && trim((string) $value) !== '';
    }

    /**
     * @param string $key
     * @param string $fallback
     * @return string
     */
    private static function configDefault($key, $fallback)
    {
        $value = config('services.daycoval.' . $key, $fallback);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @param Cedente $cedente
     * @param string $event
     * @param array $changes
     */
    private static function recordAudit(Cedente $cedente, $event, array $changes)
    {
        $user = User::logged();

        CedenteAudit::create([
            'cedente_id' => $cedente->id,
            'user_id' => $user ? (int) $user->id : null,
            'event' => $event,
            'old_status' => $cedente->status,
            'new_status' => $cedente->status,
            'changes' => $changes,
        ]);
    }
}
