<?php

namespace App\Http\Controllers;

use App\Cedente;
use App\CedenteAudit;
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

        // `cadastro_status_resumo` no JSON raiz junto com current_page, data, etc.
        // (Laravel 5.6: paginador não tem `additional()`; isso existe em versões mais novas.)
        $paginator = Cedente::with(['address', 'pessoasVinculadas', 'contasDesembolso', 'cedenteFiles'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json(array_merge($paginator->toArray(), [
            'cadastro_status_resumo' => Cedente::cadastroStatusResumo(),
        ]));
    }

    /**
     * Contagem de cadastros de cedente por status atual (sem listar registros).
     */
    public static function statusResumo()
    {
        return response()->json([
            'error' => 'false',
            'data' => Cedente::cadastroStatusResumo(),
        ]);
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

    /**
     * Histórico de mudanças de status do cadastro (`cedente_audit`).
     * Quem alterou: `user_id` / `usuario_email` (usuário logado via header User em save/edit).
     * Quando: `created_at`.
     */
    public static function historico($id)
    {
        $id = (int) $id;
        if ($id < 1 || Cedente::find($id) === null) {
            return response()->json(['error' => 'true', 'message' => 'Cedente nao encontrado'], 404);
        }

        $linhas = CedenteAudit::where('cedente_id', $id)
            ->orderBy('id', 'desc')
            ->with(['user'])
            ->get();

        $data = $linhas->map(function ($a) {
            $u = $a->user;

            return [
                'id' => $a->id,
                'event' => $a->event,
                'old_status' => $a->old_status,
                'new_status' => $a->new_status,
                'user_id' => $a->user_id,
                'usuario_email' => $u ? $u->email : null,
                'created_at' => $a->created_at ? $a->created_at->toDateTimeString() : null,
            ];
        });

        return response()->json([
            'error' => 'false',
            'data' => $data->values()->all(),
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
