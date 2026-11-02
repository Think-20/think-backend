<?php

namespace App\Http\Controllers;

use App\Cotista;
use App\CotistaFile;
use App\Http\Services\CotistaAvaliacaoService;
use App\Http\Services\CotistaFileService;
use App\Http\Services\CotistaPermissionService;
use App\Http\Services\CotistaService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use InvalidArgumentException;

class CotistaController extends Controller
{
    /**
     * @param Request $request
     * @return array
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
     * @param Request $request
     * @return array
     */
    private static function payloadWithFund(Request $request)
    {
        $data = self::decodeRequestPayload($request);
        if (! isset($data['fund_id']) && $request->query('fund_id') !== null) {
            $data['fund_id'] = $request->query('fund_id');
        }

        return $data;
    }

    /**
     * @param string $action
     * @param \Closure $callback
     * @param string $successMessage
     * @return \Illuminate\Http\JsonResponse
     */
    private static function respond($action, $callback, $successMessage)
    {
        try {
            $cotista = $callback();
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            Log::error('Cotista ' . $action . ' QueryException: ' . $e->getMessage(), ['exception' => $e]);

            $message = 'Erro ao processar no banco de dados';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            Log::error('Cotista ' . $action . ': ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => 'Erro ao ' . $action . ' cotista: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => $successMessage,
            'data' => CotistaService::toApiArray($cotista),
        ]);
    }

    public static function all(Request $request)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CotistaService::resolveFundId($data);
            CotistaPermissionService::assertCanView($fundId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        }

        CotistaService::markExpiredApprovedAsVencido($fundId);

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $paginator = Cotista::forFund($fundId)
            ->with(Cotista::apiRelations())
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function (Cotista $cotista) {
            return CotistaService::toApiArray($cotista);
        });

        return response()->json(array_merge($paginator->toArray(), [
            'fund_id' => $fundId,
            'cadastro_status_resumo' => Cotista::cadastroStatusResumo($fundId),
        ]));
    }

    public static function statusResumo(Request $request)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CotistaService::resolveFundId($data);
            CotistaPermissionService::assertCanView($fundId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'error' => 'false',
            'data' => Cotista::cadastroStatusResumo($fundId),
        ]);
    }

    public static function get(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CotistaService::resolveFundId($data);
            CotistaPermissionService::assertCanView($fundId);
            CotistaService::markExpiredApprovedAsVencido($fundId);
            $cotista = Cotista::forFund($fundId)->with(Cotista::apiRelations())->find($id);
            if (! $cotista) {
                return response()->json(['error' => 'true', 'message' => 'Cotista nao encontrado'], 404);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'error' => 'false',
            'data' => CotistaService::toApiArray($cotista),
        ]);
    }

    public static function historico(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CotistaService::resolveFundId($data);
            CotistaPermissionService::assertCanView($fundId);
            $cotista = CotistaService::findCotistaForFund($id, $fundId);
        } catch (InvalidArgumentException $e) {
            $status = strpos($e->getMessage(), 'nao encontrado') !== false ? 404 : 400;

            return response()->json(['error' => 'true', 'message' => $e->getMessage()], $status);
        }

        return response()->json([
            'error' => 'false',
            'fund_id' => (int) $cotista->fund_id,
            'data' => CotistaService::historicoToApiArray($cotista),
        ]);
    }

    public static function save(Request $request)
    {
        return self::respond('cadastrar', function () use ($request) {
            return CotistaService::create(self::decodeRequestPayload($request));
        }, 'Cotista cadastrado com sucesso');
    }

    public static function edit(Request $request)
    {
        return self::respond('atualizar', function () use ($request) {
            return CotistaService::update(self::decodeRequestPayload($request));
        }, 'Cotista atualizado com sucesso');
    }

    public static function patch(Request $request)
    {
        return self::respond('atualizar', function () use ($request) {
            return CotistaService::patchPartial(self::decodeRequestPayload($request));
        }, 'Cotista atualizado com sucesso');
    }

    public static function remove(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CotistaService::resolveFundId($data);
            if (! CotistaService::deleteById($id, $fundId)) {
                return response()->json(['error' => 'true', 'message' => 'Cotista ' . $id . ' nao encontrado'], 404);
            }
        } catch (InvalidArgumentException $e) {
            $status = strpos($e->getMessage(), 'nao encontrado') !== false ? 404 : 400;

            return response()->json(['error' => 'true', 'message' => $e->getMessage()], $status);
        } catch (QueryException $e) {
            Log::error('Cotista remove QueryException: ' . $e->getMessage(), ['exception' => $e]);

            $message = 'Erro ao excluir no banco de dados';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            Log::error('Cotista remove: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => 'Erro ao excluir cotista: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Cotista excluido com sucesso']);
    }

    public static function validarArquivo(Request $request)
    {
        return self::respond('validar arquivo', function () use ($request) {
            return CotistaFileService::setValidacao(self::decodeRequestPayload($request));
        }, 'Validacao do arquivo registrada com sucesso');
    }

    public static function avaliar(Request $request)
    {
        return self::respond('registrar avaliacao', function () use ($request) {
            return CotistaAvaliacaoService::registrar(self::decodeRequestPayload($request));
        }, 'Avaliacao registrada com sucesso');
    }

    /**
     * GET /cotistas/arquivos/download/{id}?fund_id=
     */
    public static function downloadArquivo(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CotistaService::resolveFundId($data);
            CotistaPermissionService::assertCanDownloadArquivos($fundId);

            $file = CotistaFile::with('cotista')->find((int) $id);
            if (! $file || ! $file->cotista) {
                return response()->json(['error' => 'true', 'message' => 'Arquivo nao encontrado'], 404);
            }
            if ((int) $file->cotista->fund_id !== (int) $fundId) {
                return response()->json(['error' => 'true', 'message' => 'Arquivo nao pertence ao fundo informado'], 404);
            }

            $path = CotistaFile::downloadFile($id);
            $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
            $downloadName = $file->original_name ?: $file->name;

            return Response::make(file_get_contents($path), 200, [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $downloadName) . '"',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Log::error('Cotista downloadArquivo: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /cotistas/arquivos/download-all/{id}?fund_id=
     */
    public static function downloadAllArquivos(Request $request, $id)
    {
        try {
            $data = self::payloadWithFund($request);
            $fundId = CotistaService::resolveFundId($data);
            CotistaPermissionService::assertCanDownloadArquivos($fundId);
            CotistaService::findCotistaForFund($id, $fundId);

            $zipPath = CotistaFile::downloadAllFiles($id);
            $contents = file_get_contents($zipPath);
            $mime = function_exists('mime_content_type') ? mime_content_type($zipPath) : 'application/zip';
            @unlink($zipPath);

            return Response::make($contents, 200, [
                'Content-Type' => $mime ?: 'application/zip',
                'Content-Disposition' => 'attachment; filename="cotista-' . (int) $id . '-arquivos.zip"',
            ]);
        } catch (InvalidArgumentException $e) {
            $status = strpos($e->getMessage(), 'nao encontrado') !== false ? 404 : 400;

            return response()->json(['error' => 'true', 'message' => $e->getMessage()], $status);
        } catch (Exception $e) {
            Log::error('Cotista downloadAllArquivos: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'true',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
