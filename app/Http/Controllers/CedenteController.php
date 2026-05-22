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
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        }

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $paginator = Cedente::forFund($fundId)
            ->with(['address', 'pessoasVinculadas', 'contasDesembolso', 'cedenteFiles', 'fund'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

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
            $cedente = Cedente::forFund($fundId)
                ->with(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'fund'])
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
            CedenteService::findCedenteForFund($id, $fundId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 404);
        }

        $linhas = CedenteAudit::where('cedente_id', (int) $id)
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
            'fund_id' => $fundId,
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
}
