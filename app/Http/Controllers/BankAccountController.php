<?php

namespace App\Http\Controllers;

use App\BankAccount;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Response;

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
        $status = false;
        $bankAccount = null;
        $message = '';

        try {
            $model = BankAccount::insert($request->all());
            $bankAccount = BankAccount::with('bank', 'bankAccountType')
                ->withCount('transactions')
                ->find($model->id);
            $message = 'Conta bancaria cadastrada com sucesso!';
            $status = true;
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
        } catch (QueryException $queryException) {
            if ($queryException->getCode() == 23000) {
                $message = 'Conta bancaria ja cadastrada';
            } else {
                $message = 'Erro ao cadastrar no banco de dados';
            }
        } catch (Exception $e) {
            $message = 'Erro desconhecido ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'bankAccount' => $bankAccount,
        ]), 200);
    }

    public static function edit(Request $request)
    {
        $status = false;
        $bankAccount = null;
        $message = '';

        try {
            $model = BankAccount::edit($request->all());
            if ($model === false) {
                $id = isset($request->id) ? $request->id : '';
                $message = 'Conta bancaria ' . $id . ' nao encontrada';
            } else {
                $bankAccount = BankAccount::with('bank', 'bankAccountType')
                    ->withCount('transactions')
                    ->find($model->id);
                $message = 'Conta bancaria alterada com sucesso!';
                $status = true;
            }
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
        } catch (QueryException $queryException) {
            if ($queryException->getCode() == 23000) {
                $message = 'Conta bancaria ja cadastrada';
            } else {
                $message = 'Erro ao atualizar no banco de dados';
            }
        } catch (Exception $e) {
            $message = 'Erro desconhecido ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'bankAccount' => $bankAccount,
        ]), 200);
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
