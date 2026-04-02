<?php

namespace App\Http\Controllers;

use App\Installment;
use App\Tag;
use App\Transaction;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransactionController extends Controller
{
    public function getByJobAndBankAccount(int $jobId, int $bankAccountId)
    {
        $transactions = Transaction::with([
            'category',
            'bankAccount.bank',
            'bankAccount.bankAccountType',
            'installments',
            'tags'
        ])
            ->where('job_id', $jobId)
            ->where('bank_account_id', $bankAccountId)
            ->orderBy('creation_date', 'desc')
            ->get();

        $totalRealizado = $transactions->filter(function ($t) {
            return $t->realized_date !== null;
        })->sum('total_value');

        $totalPrevisto = $transactions->sum('total_value');
        $totalReceber = $totalPrevisto - $totalRealizado;

        $transacoes = $transactions->map(function ($transaction) {
            return $this->formatTransactionToResponse($transaction);
        });

        return response()->json([
            'totalRealizado' => (float) $totalRealizado,
            'totalReceber' => (float) $totalReceber,
            'totalPrevisto' => (float) $totalPrevisto,
            'transacoes' => $transacoes
        ]);
    }

    public function totalByJobAndType(int $jobId, int $transactionType)
    {
        $total = Transaction::where('job_id', $jobId)
            ->where('transaction_type', $transactionType)
            ->sum('total_value');

        return response()->json([
            'total' => (float) $total,
        ]);
    }

    public function create(Request $request)
    {
        try {
            $payload = $request->all();
            $transaction = null;

            DB::beginTransaction();
            $transaction = $this->upsertTransactionFromPayload($payload, null);
            DB::commit();

            $transaction = Transaction::with([
                'category',
                'bankAccount.bank',
                'bankAccount.bankAccountType',
                'installments',
                'tags',
            ])->find($transaction->id);

            return response()->json($this->formatTransactionToResponse($transaction));
        } catch (InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            DB::rollBack();
            if ($e->getCode() == 23000) {
                return response()->json(['error' => 'true', 'message' => 'Transacao ja cadastrada'], 200);
            }
            return response()->json(['error' => 'true', 'message' => 'Erro ao cadastrar no banco de dados'], 400);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao cadastrar'], 400);
        }
    }

    public function edit(Request $request)
    {
        try {
            $payload = $request->all();

            if (!isset($payload['idtransacao'])) {
                return response()->json(['error' => 'true', 'message' => 'Id nao informado'], 400);
            }

            DB::beginTransaction();
            $transaction = Transaction::find((int) $payload['idtransacao']);
            if (!$transaction) {
                DB::rollBack();
                return response()->json(['error' => 'true', 'message' => 'Transacao ' . $payload['idtransacao'] . ' nao encontrada'], 400);
            }

            $transaction = $this->upsertTransactionFromPayload($payload, $transaction);
            DB::commit();

            $transaction = Transaction::with([
                'category',
                'bankAccount.bank',
                'bankAccount.bankAccountType',
                'installments',
                'tags',
            ])->find($transaction->id);

            return response()->json($this->formatTransactionToResponse($transaction));
        } catch (InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $e) {
            DB::rollBack();
            if ($e->getCode() == 23000) {
                return response()->json(['error' => 'true', 'message' => 'Transacao ja cadastrada'], 200);
            }
            return response()->json(['error' => 'true', 'message' => 'Erro ao atualizar no banco de dados'], 400);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao atualizar'], 400);
        }
    }

    public function updateStatus(Request $request)
    {
        $id = $request->input('idtransacao');
        $status = $request->input('status');

        if (empty($id)) {
            return response()->json(['error' => 'true', 'message' => 'Id nao informado'], 400);
        }

        if ($status === null || $status === '') {
            return response()->json(['error' => 'true', 'message' => 'Status invalido'], 400);
        }

        $status = (int) $status;
        if (!in_array($status, [1, 2, 3], true)) {
            return response()->json(['error' => 'true', 'message' => 'Status invalido'], 400);
        }

        $transaction = Transaction::find((int) $id);
        if (!$transaction) {
            return response()->json(['error' => 'true', 'message' => 'Transacao ' . $id . ' nao encontrada'], 400);
        }

        $transaction->status = $status;
        $transaction->save();

        return response()->json([
            'idtransacao' => $transaction->id,
            'status' => $transaction->status,
        ]);
    }

    public function remove(int $id)
    {
        try {
            $transaction = Transaction::find($id);
            if (!$transaction) {
                return response()->json(['error' => 'true', 'message' => 'Transacao ' . $id . ' nao encontrada'], 400);
            }

            DB::beginTransaction();
            $transaction->tags()->detach();
            $transaction->delete();
            DB::commit();

            return response()->json(['error' => false]);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['error' => 'true', 'message' => 'Erro ao excluir no banco de dados'], 400);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao excluir'], 400);
        }
    }

    private function upsertTransactionFromPayload(array $payload, $transaction = null)
    {
        $jobId = isset($payload['idjob']) ? (int) $payload['idjob'] : 0;
        if ($jobId <= 0) {
            throw new InvalidArgumentException('jobId invalido');
        }

        $type = isset($payload['tipotransacao']) ? (int) $payload['tipotransacao'] : 0;
        if (!in_array($type, [1, 2], true)) {
            throw new InvalidArgumentException('tipoTransacao invalido');
        }

        $status = isset($payload['status']) ? (int) $payload['status'] : 1;
        if (!in_array($status, [1, 2, 3], true)) {
            throw new InvalidArgumentException('status invalido');
        }

        $bankAccountId = isset($payload['idcontabancaria']) ? (int) $payload['idcontabancaria'] : 0;
        if ($bankAccountId <= 0) {
            throw new InvalidArgumentException('Conta bancaria invalida');
        }

        $categoryId = isset($payload['idcategoria']) && $payload['idcategoria'] !== null && $payload['idcategoria'] !== ''
            ? (int) $payload['idcategoria']
            : null;

        $paymentMethod = isset($payload['formapagamento']) ? (int) $payload['formapagamento'] : 1;
        if (!in_array($paymentMethod, [1, 2, 3, 4, 5], true)) {
            throw new InvalidArgumentException('formapagamento invalido');
        }

        $numInstallments = isset($payload['numparcelas']) ? (int) $payload['numparcelas'] : 1;
        if ($numInstallments <= 0) {
            throw new InvalidArgumentException('numparcelas invalido');
        }

        $period = isset($payload['periodo']) ? (int) $payload['periodo'] : 1;
        if ($period <= 0) {
            throw new InvalidArgumentException('periodo invalido');
        }

        $totalValue = isset($payload['valortotal']) ? (float) $payload['valortotal'] : 0;
        if ($totalValue < 0) {
            throw new InvalidArgumentException('valortotal invalido');
        }

        $attrs = [
            'job_id' => $jobId,
            'transaction_type' => $type,
            'description' => isset($payload['descricao']) ? $payload['descricao'] : null,
            'observation' => isset($payload['observacao']) ? $payload['observacao'] : null,
            'status' => $status,
            'creation_date' => isset($payload['datacriacao']) ? $payload['datacriacao'] : null,
            'receipt_date' => isset($payload['datarecebimento']) ? $payload['datarecebimento'] : null,
            'due_date' => isset($payload['datavencimento']) ? $payload['datavencimento'] : null,
            'realized_date' => isset($payload['datarealizado']) ? $payload['datarealizado'] : null,
            'billing_date' => isset($payload['datacobranca']) ? $payload['datacobranca'] : null,
            'category_id' => $categoryId,
            'bank_account_id' => $bankAccountId,
            'payment_method' => $paymentMethod,
            'num_installments' => $numInstallments,
            'total_value' => $totalValue,
            'period' => $period,
            'pix_key' => isset($payload['chavepix']) ? $payload['chavepix'] : null,
            'bank' => isset($payload['banco']) ? $payload['banco'] : null,
            'agency' => isset($payload['agencia']) ? $payload['agencia'] : null,
            'checking_account' => isset($payload['contacorrente']) ? $payload['contacorrente'] : null,
            'ticket_file_directory' => isset($payload['diretorioarquivoboleto']) ? $payload['diretorioarquivoboleto'] : null,
        ];

        if ($transaction) {
            $transaction->update($attrs);
        } else {
            $transaction = Transaction::create($attrs);
        }

        if (isset($payload['parcelas']) && is_array($payload['parcelas'])) {
            $this->syncInstallments($transaction, $payload['parcelas']);
        }

        if (isset($payload['tags']) && is_array($payload['tags'])) {
            $this->syncTags($transaction, $payload['tags']);
        }

        return $transaction;
    }

    private function syncInstallments(Transaction $transaction, array $parcelas)
    {
        $keepIds = [];

        foreach ($parcelas as $p) {
            if (!is_array($p)) {
                continue;
            }

            $value = isset($p['valor']) ? (float) $p['valor'] : null;
            $date = isset($p['data']) ? $p['data'] : null;
            $order = isset($p['ordem']) ? (int) $p['ordem'] : null;

            if ($value === null || $order === null) {
                throw new InvalidArgumentException('Parcela invalida');
            }

            $attrs = [
                'transaction_id' => $transaction->id,
                'value' => $value,
                'date' => $date,
                'order' => $order,
            ];

            if (isset($p['idparcela']) && $p['idparcela']) {
                $inst = Installment::where('transaction_id', $transaction->id)->find((int) $p['idparcela']);
                if ($inst) {
                    $inst->update($attrs);
                    $keepIds[] = $inst->id;
                    continue;
                }
            }

            $inst = Installment::create($attrs);
            $keepIds[] = $inst->id;
        }

        Installment::where('transaction_id', $transaction->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function syncTags(Transaction $transaction, array $tags)
    {
        $tagIds = [];

        foreach ($tags as $t) {
            if (!is_array($t)) {
                continue;
            }

            if (isset($t['idtag']) && $t['idtag']) {
                $tag = Tag::find((int) $t['idtag']);
                if ($tag) {
                    $tagIds[] = $tag->id;
                    continue;
                }
            }

            if (isset($t['descricao']) && trim((string) $t['descricao']) !== '') {
                $desc = trim((string) $t['descricao']);
                $tag = Tag::where('description', $desc)->first();
                if (!$tag) {
                    $tag = Tag::create(['description' => $desc]);
                }
                $tagIds[] = $tag->id;
            }
        }

        $transaction->tags()->sync(array_values(array_unique($tagIds)));
    }

    private function formatTransactionToResponse(Transaction $transaction)
    {
        $category = null;
        if ($transaction->category) {
            $category = [
                'idcategoria' => $transaction->category->id,
                'nome' => $transaction->category->name,
                'tema' => $transaction->category->theme
            ];
        }

        $contabancaria = null;
        if ($transaction->bankAccount) {
            $bank = $transaction->bankAccount->bank;
            $contabancaria = [
                'idcontabancaria' => $transaction->bankAccount->id,
                'nome' => $transaction->bankAccount->name,
                'banco' => $bank ? $bank->name : '',
                'agencia' => $transaction->bankAccount->agency,
                'conta' => $transaction->bankAccount->account_number,
                'datacadastro' => $transaction->bankAccount->registration_date
                    ? $transaction->bankAccount->registration_date->format('Y-m-d\TH:i:s')
                    : null
            ];
        }

        $parcelas = $transaction->installments->map(function ($installment) {
            return [
                'idparcela' => $installment->id,
                'idtransacao' => $installment->transaction_id,
                'valor' => (float) $installment->value,
                'data' => $installment->date ? $installment->date->format('Y-m-d\TH:i:s') : null,
                'ordem' => $installment->order
            ];
        })->values();

        $tags = $transaction->tags->map(function ($tag) {
            return [
                'idtag' => $tag->id,
                'descricao' => $tag->description
            ];
        })->values();

        return [
            'idtransacao' => $transaction->id,
            'idjob' => $transaction->job_id,
            'tipotransacao' => $transaction->transaction_type,
            'descricao' => $transaction->description,
            'observacao' => $transaction->observation ?? '',
            'status' => $transaction->status,
            'datacriacao' => $transaction->creation_date ? $transaction->creation_date->format('Y-m-d\TH:i:s') : null,
            'datarecebimento' => $transaction->receipt_date ? $transaction->receipt_date->format('Y-m-d\TH:i:s') : null,
            'datavencimento' => $transaction->due_date ? $transaction->due_date->format('Y-m-d\TH:i:s') : null,
            'datarealizado' => $transaction->realized_date ? $transaction->realized_date->format('Y-m-d\TH:i:s') : null,
            'datacobranca' => $transaction->billing_date ? $transaction->billing_date->format('Y-m-d\TH:i:s') : null,
            'idcategoria' => $transaction->category_id,
            'categoria' => $category,
            'idcontabancaria' => $transaction->bank_account_id,
            'contabancaria' => $contabancaria,
            'formapagamento' => $transaction->payment_method,
            'numparcelas' => $transaction->num_installments,
            'valortotal' => (float) $transaction->total_value,
            'periodo' => $transaction->period,
            'chavepix' => $transaction->pix_key ?? '',
            'banco' => $transaction->bank ?? '',
            'agencia' => $transaction->agency ?? '',
            'contacorrente' => $transaction->checking_account ?? '',
            'diretorioarquivoboleto' => $transaction->ticket_file_directory ?? '',
            'parcelas' => $parcelas,
            'tags' => $tags
        ];
    }
}
