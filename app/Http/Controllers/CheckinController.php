<?php

namespace App\Http\Controllers;

use App\Checkin;
use App\Client;
use App\Extra;
use App\ExtraItem;
use App\Http\Services\MailService;
use App\Job;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Http\Request;

class CheckinController extends Controller
{

    public function __construct() {}

    public function selectCheckin(Request $request, int $id = null)
    {


        if (!isset($id)) {
            $checkin = Checkin::list();
            if (!$checkin) {
                return response()->json(['error' => 'true', 'message' => 'Nenhum Checkin encontrado'], 400);
            }

            return $checkin;
        } else {
            $checkin = Checkin::getUnique($id);

            if (!$checkin) {
                return response()->json(['error' => 'true', 'message' => 'Checkin ' . $id . ' nao encontrado'], 400);
            }
            return $checkin;
        }
    }

    public function createCheckin(Request $request)
    {
        /*if ($request->address_number < 0) {
            return response()->json(['error' => 'true', 'message' => 'Número invalido para o endereço'], 400);
        }

        $client = Client::where('id', $request->client_id)->first();

        if (!$client) {
            return response()->json(['error' => 'true', 'message' => 'Cliente não cadastrado.'], 400);
        }

        $organization = new Checkin();
        $organization->name = $request->name;
        $organization->city = $request->city;
        $organization->address = $request->address;
        $organization->address_number = $request->address_number;
        $organization->site = $request->site;
        $organization->client_id = $request->client_id;
        $organization->save();*/

        $checkin = Checkin::create($request->all());
        return response()->json(['error' => 'false', 'message' => 'Checkin cadastrada com sucesso', 'object' => $checkin]);
    }

    public function updateCheckin(Request $request)
    {
        /*$organization = Organization::where('id', $request->id)->first();

        if (!$organization) {
            return response()->json(['error' => 'true', 'message' => 'Organização ' . $request->id . ' não encontrada'], 400);
        }

        if (isset($request->name)) {
            if ($request->name) {
                $organization->name = $request->name;
            }
        }

        if (isset($request->city)) {
            if ($request->city) {
                $organization->name = $request->city;
            }
        }

        if (isset($request->address)) {
            if ($request->address) {
                $organization->address = $request->address;
            }
        }

        if (isset($request->address_number)) {
            if ($request->address_number) {
                $organization->address_number = $request->address_number;
            }
        }

        if (isset($request->site)) {
            if ($request->site) {
                $organization->site = $request->site;
            }
        }

        if (isset($request->client_id)) {
            if ($request->client_id) {
                $organization->client_id = $request->client_id;
            }
        }

        $organization->save();*/

        $checkin = Checkin::find($request->id);
        $checkin->update($request->all());


        return response()->json(['error' => 'false', 'message' => 'Checkin atualizada com sucesso', 'object' => $checkin]);
    }

    public static function sendMailCheckin(Request $request)
    {
        $checkinId = $request->checkin_id;

        $checkin = Checkin::getUnique($checkinId);
        $extra = Extra::where('job_id', $checkin->job_id)->first();

        $hash = self::generateUuidHash();

        $extra->update([
            'hash' => $hash,
        ]);

        $email = $checkin->client_email;
        $nome = '';

        if (!$email) {
            return response()->json(['error' => 'false', 'message' => 'Sem E-mail do destinatário.']);
        }

        $link = MailService::frontendBaseUrl() . '/external/extras/' . $checkinId . '/' . $hash;
        $body = 'Olá! 😊<br /><br />'
            . 'Gostaríamos de expressar nossa gratidão pela confiança e parceria. Para '
            . 'prosseguirmos com o próximo passo, solicitamos gentilmente que clique no botão abaixo para visualização dos itens extras.';

        try {
            MailService::send([
                'to' => $email,
                'to_name' => $nome,
                'from' => env('MAIL_FROM_ADDRESS_MYJOB', env('MAIL_FROM_ADDRESS', 'no-reply@think.com')),
                'from_name' => env('MAIL_FROM_NAME_MYJOB', 'My Job'),
                'subject' => 'Obrigado pela parceria',
                'body' => MailService::renderHtmlLayout('Obrigado pela Parceria!', $body, $link, 'Visualizar'),
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()]);
        }

        return response()->json(['error' => 'false', 'message' => 'Email de confirmação enviado ao cliente.']);
    }

    public static function sendMailCheckinAccept(Request $request)
    {
        $checkinId = $request->checkin_id;

        $checkin = Checkin::getUnique($checkinId);
        $extra = Extra::where('job_id', $checkin->job_id)->first();

        $hash = self::generateUuidHash();

        $extra->update([
            'hash' => $hash,
        ]);

        $email = $checkin->client_email;
        $nome = '';

        if (!$email) {
            return response()->json(['error' => 'false', 'message' => 'Sem E-mail do destinatário.']);
        }

        $base = MailService::frontendBaseUrl() . '/external/check-in/' . $checkinId . '/' . $hash;
        $acceptUrl = htmlspecialchars($base . '/1', ENT_QUOTES, 'UTF-8');
        $refuseUrl = htmlspecialchars($base . '/2', ENT_QUOTES, 'UTF-8');
        $bodyInner = 'Olá! 😊<br /><br />'
            . 'Gostaríamos de expressar nossa gratidão pela confiança e parceria. Para '
            . 'prosseguirmos com o próximo passo, escolha uma das opções abaixo:<br /><br />'
            . '<a href="' . $acceptUrl . '" target="_blank" style="display:inline-block;padding:12px 20px;background:#28a745;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;">Aceitar Proposta</a>'
            . '<br /><br />'
            . '<a href="' . $refuseUrl . '" target="_blank" style="font-size:12px;">Recusar proposta</a>';

        try {
            MailService::send([
                'to' => $email,
                'to_name' => $nome,
                'subject' => 'Obrigado pela parceria',
                'body' => MailService::renderHtmlLayout('Obrigado pela Parceria!', $bodyInner),
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()]);
        }

        return response()->json(['error' => 'false', 'message' => 'Email de confirmação enviado ao cliente.']);
    }

    /**
     * @return string
     */
    private static function generateUuidHash()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }


    //Confirma os extras
    public static function confirmMailCheckin(Request $request)
    {
        $checkInId = $request->checkin_id;
        $hash = $request->hash;

        $checkin = Checkin::where('id', '=', $checkInId)
            ->first();

        if ($checkin == null) {
            return response()->json(['error' => 'true', 'message' => 'Checkin Não encontrado.']);
        }

        $extra = Extra::where('job_id', $checkin->job_id)->where('hash', '=', $hash)->first();

        if ($extra == null) {
            return response()->json(['error' => 'true', 'message' => 'Extra Não encontrado.']);
        }
        
        if ($extra->accept_client == 0 || $extra->accept_client == null) {
            $extra->update([
                'accept_client' => 1,
                'accept_client_date' => Carbon::now()
            ]);

            return response()->json(['error' => 'false', 'message' => 'Checkin confirmado.']);
        } else {
            return response()->json(['error' => 'true', 'message' => 'Checkin já confirmado no dia ' . Carbon::parse($extra->accept_client_date)->format("d/m/Y") . '.']);
        }
    }

    //Confirma o checkin
    public static function confirmMailCheckinAccept(Request $request)
    {
        $checkInId = $request->checkin_id;
        $hash = $request->checkin_hash;

        $checkin = Checkin::where('id', '=', $checkInId)
            ->where('checkin_hash', '=', $hash)
            ->first();

        if ($checkin == null) {
            return response()->json(['error' => 'true', 'message' => 'Checkin Não encontrado.']);
        }

        if ($checkin->accept_client == 0 || $checkin->accept_client == null) {
            $checkin->update([
                'accept_client' => $request->accept_client,
                'reason_for_rejection' => $request->reason_for_rejection,
                'accept_client_date' => Carbon::now()
            ]);

            return response()->json(['error' => 'false', 'message' => 'Aceite do cliente alterado com sucesso.']);
        }
    }
}
