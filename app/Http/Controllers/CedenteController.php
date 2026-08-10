<?php

namespace App\Http\Controllers;

use App\Cedente;
use App\CedenteFile;
use App\Http\Services\CedenteAvaliacaoService;
use App\Http\Services\CedenteFileService;
use App\Http\Services\CedentePermissionService;
use App\Http\Services\CedenteService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use InvalidArgumentException;

class CedenteController extends Controller
{
    /**
     * Mescla input parseado pelo Laravel com JSON bruto do corpo (útil quando o Content-Type não é application/json).
     */
    private static function decodeRequestPayload(Request $request)
    {
        $data = $request->all();
        $raw = $request->getContent();
        if ($raw === '' || $raw === false) {
            return is_array($data) ? $data : [];
        }

        $trimmed = ltrim($raw);
        if (! isset($trimmed[0]) || $trimmed[0] !== '{') {
            return is_array($data) ? $data : [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return is_array($data) ? $data : [];
        }

        return array_replace($data, $decoded);
    }

    /**
     * fund_id no JSON do POST ou query string (GET/DELETE).
     */
    private static function payloadWithFund(Request $request)
    {
        $data = self::decodeRequestPayload($request);
        if (! isset($data['fund_id']) && $request->query('fund_id') !== null) {
            $data['fund_id'] = $request->query('fund_id');
        }

        return $data;
    }

    public static function all(Request $request)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CedenteService::resolveFundId($data);
            CedentePermissionService::assertCanView($fundId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        }

        CedenteService::markExpiredApprovedAsVencido($fundId);

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $paginator = Cedente::forFund($fundId)
            ->with([
                'address',
                'pessoasVinculadas',
                'contasDesembolso',
                'cedenteFiles',
                'fund',
                'inconsistencias',
                'restricoes',
                'audits.user.employee',
            ])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function (Cedente $cedente) {
            // Garante o mesmo formato de inconsistencias/restricoes/historico do GET unitario.
            $row = $cedente->toArray();
            $row['inconsistencias'] = CedenteService::inconsistenciasToApiArray($cedente);
            $row['restricoes'] = CedenteService::restricoesToApiArray($cedente);
            $row['historico'] = CedenteService::historicoToApiArray($cedente);

            return $row;
        });

        return response()->json(array_merge($paginator->toArray(), [
            'fund_id' => $fundId,
            'cadastro_status_resumo' => Cedente::cadastroStatusResumo($fundId),
        ]));
    }

    public static function statusResumo(Request $request)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CedenteService::resolveFundId($data);
            CedentePermissionService::assertCanView($fundId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'error' => 'false',
            'data' => Cedente::cadastroStatusResumo($fundId),
        ]);
    }

    public static function status(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CedenteService::resolveFundId($data);
            CedentePermissionService::assertCanView($fundId);
            $cedente = CedenteService::findCedenteForFund($id, $fundId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 404);
        }

        return response()->json([
            'error' => 'false',
            'data' => [
                'id' => $cedente->id,
                'fund_id' => $cedente->fund_id,
                'status' => $cedente->status ?: Cedente::STATUS_PENDENTE,
                'sla' => $cedente->sla ? $cedente->sla->format('Y-m-d') : null,
            ],
        ]);
    }

    public static function get(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CedenteService::resolveFundId($data);
            CedentePermissionService::assertCanView($fundId);
            CedenteService::markExpiredApprovedAsVencido($fundId);
            $cedente = Cedente::forFund($fundId)
                ->with(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'fund', 'inconsistencias', 'restricoes'])
                ->find($id);
            if (! $cedente) {
                return response()->json(['error' => 'true', 'message' => 'Cedente nao encontrado'], 404);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'error' => 'false',
            'data' => CedenteService::toApiArray($cedente),
        ]);
    }

    public static function historico(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CedenteService::resolveFundId($data);
            CedentePermissionService::assertCanView($fundId);
            $cedente = CedenteService::findCedenteForFund($id, $fundId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 404);
        }

        return response()->json([
            'error' => 'false',
            'fund_id' => $fundId,
            'data' => CedenteService::historicoToApiArray($cedente),
        ]);
    }

    public static function save(Request $request)
    {
        try {
            $cedente = CedenteService::create(self::decodeRequestPayload($request));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            Log::error('Cedente save QueryException: ' . $e->getMessage(), ['exception' => $e]);

            $message = 'Erro ao cadastrar no banco de dados';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            Log::error('Cedente save: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => 'Erro ao cadastrar cedente: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Cedente cadastrado com sucesso',
            'data' => CedenteService::toApiArray($cedente),
        ]);
    }

    public static function patch(Request $request)
    {
        try {
            $cedente = CedenteService::patchPartial(self::decodeRequestPayload($request));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            Log::error('Cedente patch QueryException: ' . $e->getMessage(), ['exception' => $e]);

            $message = 'Erro ao atualizar no banco de dados';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            Log::error('Cedente patch: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => 'Erro ao atualizar cedente: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Cedente atualizado com sucesso',
            'data' => CedenteService::toApiArray($cedente),
        ]);
    }

    public static function edit(Request $request)
    {
        try {
            $cedente = CedenteService::update(self::decodeRequestPayload($request));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            Log::error('Cedente edit QueryException: ' . $e->getMessage(), ['exception' => $e]);

            $message = 'Erro ao atualizar no banco de dados';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            Log::error('Cedente edit: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => 'Erro ao atualizar cedente: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Cedente atualizado com sucesso',
            'data' => CedenteService::toApiArray($cedente),
        ]);
    }

    public static function remove(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CedenteService::resolveFundId($data);
            if (! CedenteService::deleteById($id, $fundId)) {
                return response()->json(['error' => 'true', 'message' => 'Cedente ' . $id . ' nao encontrado'], 404);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            Log::error('Cedente remove QueryException: ' . $e->getMessage(), ['exception' => $e]);

            $message = 'Erro ao excluir no banco de dados';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            Log::error('Cedente remove: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => 'Erro ao excluir cedente: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Cedente excluido com sucesso']);
    }

    public static function validarArquivo(Request $request)
    {
        try {
            $cedente = CedenteFileService::setValidacao(self::decodeRequestPayload($request));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            Log::error('Cedente validarArquivo QueryException: ' . $e->getMessage(), ['exception' => $e]);

            $message = 'Erro ao validar arquivo no banco de dados';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            Log::error('Cedente validarArquivo: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => 'Erro ao validar arquivo: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Validacao do arquivo registrada com sucesso',
            'data' => CedenteService::toApiArray($cedente),
        ]);
    }

    public static function avaliar(Request $request)
    {
        try {
            $cedente = CedenteAvaliacaoService::registrar(self::decodeRequestPayload($request));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            Log::error('Cedente avaliar QueryException: ' . $e->getMessage(), ['exception' => $e]);

            $message = 'Erro ao registrar avaliacao no banco de dados';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            Log::error('Cedente avaliar: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => 'Erro ao registrar avaliacao: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Avaliacao registrada com sucesso',
            'data' => CedenteService::toApiArray($cedente),
        ]);
    }

    /**
     * GET /cedentes/arquivos/download/{id}?fund_id=
     * Binario de um arquivo ativo do cedente (id = cedente_file.id).
     */
    public static function downloadArquivo(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CedenteService::resolveFundId($data);
            CedentePermissionService::assertCanDownloadArquivos($fundId);

            $file = CedenteFile::with('cedente')->find((int) $id);
            if (! $file || ! $file->cedente) {
                return response()->json(['error' => 'true', 'message' => 'Arquivo nao encontrado'], 404);
            }
            if ((int) $file->cedente->fund_id !== (int) $fundId) {
                return response()->json(['error' => 'true', 'message' => 'Arquivo nao pertence ao fundo informado'], 404);
            }

            $path = CedenteFile::downloadFile($id);
            $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
            $downloadName = $file->original_name ?: $file->name;

            return Response::make(file_get_contents($path), 200, [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $downloadName) . '"',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Log::error('Cedente downloadArquivo: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /cedentes/arquivos/download-all/{id}?fund_id=
     * ZIP com todos os arquivos ativos do cedente.
     */
    public static function downloadAllArquivos(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $data['id'] = $id;
            $fundId = CedenteService::resolveFundId($data);
            CedentePermissionService::assertCanDownloadArquivos($fundId);
            CedenteService::findCedenteForFund($id, $fundId);

            $zipPath = CedenteFile::downloadAllFiles($id);
            $contents = file_get_contents($zipPath);
            $mime = function_exists('mime_content_type') ? mime_content_type($zipPath) : 'application/zip';
            @unlink($zipPath);

            return Response::make($contents, 200, [
                'Content-Type' => $mime ?: 'application/zip',
                'Content-Disposition' => 'attachment; filename="cedente-' . (int) $id . '-arquivos.zip"',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Log::error('Cedente downloadAllArquivos: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
