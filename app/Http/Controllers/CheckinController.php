<?php

namespace App\Http\Controllers;

use App\Checkin;
use App\Client;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Http\Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\OAuth;
use League\OAuth2\Client\Provider\GenericProvider;

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


    public static function sendMailCheckinOld(Request $request, int $id = null)
    {
        $checkin = Checkin::getUnique($id);
        $mail = new PHPMailer(true);

        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        $hash =  vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $checkin->update([
            'hash' => $hash
        ]);

        $client = Client::get($checkin->client_id);
        $email = "";
        $nome = "";

        if ($client && $client->contacts && $client->contacts[0]) {
            $nome = $client->contacts[0]->name;
            $email = $client->contacts[0]->email;
        } else {
            return response()->json(['error' => 'false', 'message' => 'Cliente sem email cadastrado.']);
        }

        try {
            // Configurações do servidor SMTP do Gmail
            $mail->isSMTP();
            #$mail->SMTPDebug = 2; // ou 3 para mais informações detalhadas
            #$mail->Debugoutput = 'html';

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'gui9788534514088@gmail.com'; // Seu endereço de e-mail
            $mail->Password = 'amky uxiz mkxx huif';  // Senha de app gerada no Google
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Remetente e destinatário
            $mail->setFrom('gui9788534514088@gmail.com', 'Douglas');
            #$mail->addAddress($checkin->organization_login, $checkin->client_object->name); // Adicione o destinatário
            $mail->addAddress($email, $nome); // Adicione o destinatário

            // Conteúdo do e-mail
            $mail->isHTML(true);
            $mail->Subject = 'Obrigado pela parceria';

            /*
            $mail->Body    = 'Confirmacao de checkin para o evento ' . $checkin->event_object->name . ' que vai ocorrer entre os dias '
            . Carbon::parse($checkin->event_object->ini_date)->format("d/m/Y") . ' e '
            . Carbon::parse($checkin->event_object->fin_date)->format("d/m/Y") . '.<br> <a href="http://54.163.167.198:8000/testeEmailConfirm/'
            . $id . '">Clicke aqui para confirmar check-in</a>';
            */

            $mail->Body    = 'DEV Obrigado pela parceria com a Think Ideias, a gente gostaria de confirmar o pedido dos extras do projeto.' .
                'Para confirmar clique aqui.<br> <a href="http://54.163.167.198:8000/testeEmailConfirm/' . $id . '/' . $hash . '">Clicke aqui para confirmar check-in</a>';

            // Enviar o e-mail
            $mail->send();
        } catch (Exception $e) {
            return response()->json(['error' => 'false', 'message' => 'Falha ao enviar email.' . $e]);
        }

        return response()->json(['error' => 'false', 'message' => 'Email de confirmação enviado ao cliente.']);
    }

    public static function sendMailCheckin(Request $request)
    {
        $checkinId = $request->checkin_id;

        $checkin = Checkin::getUnique($checkinId);
        $mail = new PHPMailer(true);

        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        $hash =  vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $checkin->update([
            'hash' => $hash
        ]);

        $client = Client::get($checkin->client_id);
        $email = "";
        $nome = "";

        if ($client && $client->contacts && $client->contacts[0]) {
            $nome = $client->contacts[0]->name;
            $email = $client->contacts[0]->email;
        } else {
            return response()->json(['error' => 'false', 'message' => 'Cliente sem email cadastrado.']);
        }

        try {
            // Configurações do servidor SMTP do Gmail
            $mail->isSMTP();
            #$mail->SMTPDebug = 2; // ou 3 para mais informações detalhadas
            #$mail->Debugoutput = 'html';

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'gui9788534514088@gmail.com'; // Seu endereço de e-mail
            $mail->Password = 'amky uxiz mkxx huif';  // Senha de app gerada no Google
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Remetente e destinatário
            #$mail->setFrom('gui9788534514088@gmail.com', 'Douglas');
            #$mail->addAddress($checkin->organization_login, $checkin->client_object->name); // Adicione o destinatário

            // Remetente e destinatário
            $mail->setFrom('gui9788534514088@gmail.com', 'Douglas');
            #$mail->addAddress($checkin->organization_login, $checkin->client_object->name); // Adicione o destinatário
            $mail->addAddress($email, $nome); // Adicione o destinatário

            // Conteúdo do e-mail
            $mail->isHTML(true);
            $mail->Subject = 'Obrigado pela parceria';

            /*
            $mail->Body    = 'Confirmacao de checkin para o evento ' . $checkin->event_object->name . ' que vai ocorrer entre os dias '
            . Carbon::parse($checkin->event_object->ini_date)->format("d/m/Y") . ' e '
            . Carbon::parse($checkin->event_object->fin_date)->format("d/m/Y") . '.<br> <a href="http://54.163.167.198:8000/testeEmailConfirm/'
            . $id . '">Clicke aqui para confirmar check-in</a>';
            */

            //$mail->Body    = 'Obrigado pela parceria com a Think Ideias, a gente gostaria de confirmar o pedido dos extras do projeto. <br> <a href="http://127.0.0.1:8000/external/extras/' . $checkinId . '/'.$hash.'"> Para confirmar clique aqui. </a>';
            $mail->Body    = 'Obrigado pela parceria com a Think Ideias, a gente gostaria de confirmar o pedido dos extras do projeto. <br> <a href="http://54.163.167.198:8000/external/extras/' . $checkinId . '/' . $hash . '"> Para confirmar clique aqui. </a>';

            //Antigo envio
            //'Para confirmar clique aqui.<br> <a href="http://54.163.167.198:8000/testeEmailConfirm/' . $id . '/'.$hash.'">Clicke aqui para confirmar check-in</a>';
            //

            // Enviar o e-mail
            $mail->send();
        } catch (Exception $e) {
            #echo "Erro ao enviar mensagem: {$mail->ErrorInfo}";
        }

        return response()->json(['error' => 'false', 'message' => 'Email de confirmação enviado ao cliente.']);
    }

    public static function confirmMailCheckinOld(Request $request, int $checkInId, string $hash)
    {
        $checkin = Checkin::where('id', '=', $checkInId)
            ->where('hash', '=', $hash)
            ->first();

        return ([$checkin]);

        if ($checkin == null) {
            return response()->json(['error' => 'true', 'message' => 'Checkin Não encontrado.']);
        }

        if ($checkin->accept_client == 0) {
            $checkin->update([
                'accept_client' => 1,
                'accept_client_date' => Carbon::now()
            ]);

            //Checkin confirmado com sucesso redirecionando para a tela do front
            //return redirect('/reminders');
            return response()->json(['error' => 'false', 'message' => 'Checkin confirmado.']);
        } else {

            //Checkin ja realizado
            //return redirect('/reminders');
            return response()->json(['error' => 'false', 'message' => 'Checkin já confirmado na data ' . $checkin->accept_client_date . '.']);
        }
    }

    public static function confirmMailCheckin(Request $request)
    {
        $checkInId = $request->checkin_id;
        $hash = $request->hash;

        $checkin = Checkin::where('id', '=', $checkInId)
            ->where('hash', '=', $hash)
            ->first();

        if ($checkin == null) {
            return response()->json(['error' => 'true', 'message' => 'Checkin Não encontrado.']);
        }

        if ($checkin->extras_accept_client == 0 || $checkin->extras_accept_client == null) {
            $checkin->update([
                'extras_accept_client' => 1,
                'extras_accept_client_date' => Carbon::now()
            ]);

            return response()->json(['error' => 'false', 'message' => 'Checkin confirmado.']);
        } else {
            return response()->json(['error' => 'true', 'message' => 'Checkin já confirmado no dia ' . Carbon::parse($checkin->accept_client_date)->format("d/m/Y") . '.']);
        }
    }
}
