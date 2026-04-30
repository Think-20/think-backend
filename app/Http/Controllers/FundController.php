<?php

namespace App\Http\Controllers;

use App\Fund;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FundController extends Controller
{
    private static function isIntegrityConstraintViolation(QueryException $e)
    {
        $sqlState = isset($e->errorInfo[0]) ? $e->errorInfo[0] : '';

        return $sqlState === '23000' || $e->getCode() === 23000 || $e->getCode() === '23000';
    }

    public static function all(Request $request)
    {
        return Fund::list($request->all());
    }

    public static function filter(Request $request)
    {
        return Fund::filter($request->all());
    }

    public static function get(int $id)
    {
        return Fund::get($id);
    }

    public static function save(Request $request)
    {
        try {
            $fund = Fund::insert($request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $queryException) {
            if (static::isIntegrityConstraintViolation($queryException)) {
                return response()->json(['error' => 'true', 'message' => 'Codigo de fundo ja cadastrado'], 200);
            }

            $message = config('app.debug')
                ? $queryException->getMessage()
                : 'Erro ao cadastrar no banco de dados';

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao cadastrar'], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Fundo cadastrado com sucesso',
            'data' => $fund,
        ]);
    }

    public static function edit(Request $request)
    {
        try {
            $fund = Fund::edit($request->all());
            if ($fund === false) {
                $id = isset($request->id) ? $request->id : '';

                return response()->json(['error' => 'true', 'message' => 'Fundo ' . $id . ' nao encontrado'], 400);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $queryException) {
            if (static::isIntegrityConstraintViolation($queryException)) {
                return response()->json(['error' => 'true', 'message' => 'Codigo de fundo ja cadastrado'], 200);
            }

            $message = config('app.debug')
                ? $queryException->getMessage()
                : 'Erro ao atualizar no banco de dados';

            return response()->json(['error' => 'true', 'message' => $message], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao atualizar'], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Fundo atualizado com sucesso',
            'data' => $fund,
        ]);
    }

    public static function remove(int $id)
    {
        try {
            if (!Fund::deactivate($id)) {
                return response()->json(['error' => 'true', 'message' => 'Fundo ' . $id . ' nao encontrado'], 400);
            }
        } catch (QueryException $queryException) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao desativar no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao desativar'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Fundo desativado com sucesso']);
    }
}
