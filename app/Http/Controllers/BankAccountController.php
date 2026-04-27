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
    /**
     * MySQL 1062 = entrada duplicada em índice UNIQUE.
     * Não confundir com 1452 (FK) ou outros 23000 — antes o código tratava todo 23000 como "duplicata".
     *
     * @param QueryException $e
     * @return bool
     */
    private static function isDuplicateKeyException(QueryException $e)
    {
        $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;

        return $driverCode === 1062;
    }

    /**
     * MySQL 1452 = FK inválida (ex.: bank_id ou bank_account_type_id inexistente).
     *
     * @param QueryException $e
     * @return bool
     */
    private static function isInvalidForeignKeyException(QueryException $e)
    {
        $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;

        return $driverCode === 1452;
    }

    /**
     * @return string
     */
    private static function duplicateBankAccountMessage()
    {
        return 'Já existe outra conta bancária com a mesma agência e número de conta. '
            . 'Ajuste esses campos ou confira se o payload não está repetindo dados de outro cadastro.';
    }

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
            if (self::isDuplicateKeyException($queryException)) {
                $message = self::duplicateBankAccountMessage();
            } elseif (self::isInvalidForeignKeyException($queryException)) {
                $message = 'Referência inválida: confira se o banco (bank_id) e o tipo de conta (bank_account_type_id) existem na base.';
            } else {
                $message = 'Não foi possível cadastrar no banco de dados. Verifique os dados e tente novamente.';
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
            if (self::isDuplicateKeyException($queryException)) {
                $message = self::duplicateBankAccountMessage();
                if ($request->has('id')) {
                    $bankAccount = BankAccount::with('bank', 'bankAccountType')
                        ->withCount('transactions')
                        ->find($request->input('id'));
                }
            } elseif (self::isInvalidForeignKeyException($queryException)) {
                $message = 'Referência inválida: bank_id ou bank_account_type_id não existe na base. '
                    . 'Após confirmar os ids, rode `php artisan migrate` se a FK legada de bank_id ainda apontar para a tabela errada.';
                if ($request->has('id')) {
                    $bankAccount = BankAccount::with('bank', 'bankAccountType')
                        ->withCount('transactions')
                        ->find($request->input('id'));
                }
            } else {
                $message = 'Não foi possível atualizar no banco de dados. Verifique os dados e tente novamente.';
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
