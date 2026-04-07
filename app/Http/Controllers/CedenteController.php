<?php

namespace App\Http\Controllers;

use App\Cedente;
use App\Http\Services\CedenteService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public static function all(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        return Cedente::with(['address', 'pessoasVinculadas', 'contasDesembolso', 'cedenteFiles'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public static function get($id)
    {
        $cedente = Cedente::with(['address', 'pessoasVinculadas.address', 'contasDesembolso'])->find($id);
        if (!$cedente) {
            return response()->json(['error' => 'true', 'message' => 'Cedente nao encontrado'], 404);
        }

        return response()->json([
            'error' => 'false',
            'data' => CedenteService::toApiArray($cedente),
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

    public static function remove($id)
    {
        try {
            if (!CedenteService::deleteById($id)) {
                return response()->json(['error' => 'true', 'message' => 'Cedente ' . $id . ' nao encontrado'], 404);
            }
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
}
