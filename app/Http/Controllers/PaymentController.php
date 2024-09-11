<?php

namespace App\Http\Controllers;

use App\Payment;
use Dompdf\FrameDecorator\Page;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct() {}

    public function selectPayment(Request $request, int $id = null)
    {
        if (!isset($id)) {
            $payment = Payment::list();
            if (!$payment) {
                return response()->json(['error' => 'true', 'message' => 'Nenhum pagamento encontrado'], 400);
            }

            return $payment;
        } else {
            $payment = Payment::getUnique($id);
            
            if (!$payment) {
                return response()->json(['error' => 'true', 'message' => 'Pagamento de id ' . $id . ' nao encontrada'], 400);
            }

            return $payment;
        }
    }

    public function selectPaymentByCheckin(Request $request, int $id = null )
    {
        if (!isset($id)) {
                return response()->json(['error' => 'true', 'message' => 'Nenhum id recebido'], 400);
        } else {
            $payment = Payment::getUniqueByCheckin($id);
            if (!$payment) {
                return response()->json(['error' => 'true', 'message' => 'Pagamento de id ' . $id . ' nao encontrada'], 400);
            }

            return $payment;
        }
    }



    public function createPayment(Request $request)
    {
        $payment = Payment::create($request->all());
        return response()->json(['error' => 'false', 'message' => 'Pagamento cadastrada com sucesso', 'object' => $payment]);
    }

    public function updatePayment(Request $request)
    {
        $payment = Payment::find($request->id);
        $payment->update($request->all());

        return response()->json(['error' => 'false', 'message' => 'Pagamento atualizada com sucesso', 'object' => $payment]);
    }

    public function deletePayment(Request $request, int $id = null)
    {
        if (!isset($id)) {
            return response()->json(['error' => 'true', 'message' => 'Pagamento de Id ' . $id . ' nao encontrado'], 400);
        } else {
            $payment = Payment::find($request->id);

            if (!$payment) {
                return response()->json(['error' => 'true', 'message' => 'Pagamento de id ' . $id . ' nao encontrada'], 400);
            }

            $payment->delete();

            return response()->json(['error' => 'false', 'message' => 'Pagamento de Id ' . $id . ' deletado com sucesso'], 400);
        }
    }

}
