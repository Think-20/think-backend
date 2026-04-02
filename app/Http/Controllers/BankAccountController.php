<?php

namespace App\Http\Controllers;

use App\BankAccount;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class BankAccountController extends Controller
{
    public static function all(Request $request)
    {
        return BankAccount::list($request->all());
    }

    public static function filter(Request $request)
    {
        return BankAccount::filter($request->all());
    }

    public static function get(int $id)
    {
        return BankAccount::get($id);
    }

    public static function save(Request $request)
    {
        try {
            BankAccount::insert($request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $queryException) {
            if ($queryException->getCode() == 23000) {
                return response()->json(['error' => 'true', 'message' => 'Conta bancaria ja cadastrada'], 200);
            }

            return response()->json(['error' => 'true', 'message' => 'Erro ao cadastrar no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao cadastrar'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Conta bancaria cadastrada com sucesso']);
    }

    public static function edit(Request $request)
    {
        try {
            $ok = BankAccount::edit($request->all());
            if ($ok === false) {
                $id = isset($request->id) ? $request->id : '';

                return response()->json(['error' => 'true', 'message' => 'Conta bancaria ' . $id . ' nao encontrada'], 400);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $queryException) {
            if ($queryException->getCode() == 23000) {
                return response()->json(['error' => 'true', 'message' => 'Conta bancaria ja cadastrada'], 200);
            }

            return response()->json(['error' => 'true', 'message' => 'Erro ao atualizar no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao atualizar'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Conta bancaria atualizada com sucesso']);
    }

    public static function remove(int $id)
    {
        try {
            if (!BankAccount::remove($id)) {
                return response()->json(['error' => 'true', 'message' => 'Conta bancaria ' . $id . ' nao encontrada'], 400);
            }
        } catch (QueryException $queryException) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao excluir no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao excluir'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Conta bancaria excluida com sucesso']);
    }
}
