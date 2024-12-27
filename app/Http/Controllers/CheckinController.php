<?php

namespace App\Http\Controllers;

use App\Checkin;
use App\Client;
use App\Extra;
use App\ExtraItem;
use App\Job;
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

    public static function sendMailCheckin(Request $request)
    {
        $checkinId = $request->checkin_id;

        $checkin = Checkin::getUnique($checkinId);
        $extra = Extra::where('job_id', $checkin->job_id)->first();
        
        $mail = new PHPMailer(true);

        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        $hash =  vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $extra->update([
            'hash' => $hash
        ]);

        #$client = Client::get($checkin->client_email);
        $email = $checkin->client_email;
        $nome = "";

        if (!$email) {
            return response()->json(['error' => 'false', 'message' => 'Sem E-mail do destinatário.']);
        }

        try {
            // Configurações do servidor SMTP do Gmail
            $mail->isSMTP();

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'think.ideias.1@gmail.com'; // Seu endereço de e-mail
            $mail->Password = 'dhqg bibw laok mawt';  // Senha de app gerada no Google
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            // Remetente e destinatário
            $mail->setFrom('no-reply@think.com', 'Think');
            $mail->addAddress($email, $nome); // Adicione o destinatário

            // Conteúdo do e-mail
            $mail->isHTML(true);
            $mail->Subject = 'Obrigado pela parceria';

            $mail->Body    = 'Obrigado pela parceria com a Think Ideias, a gente gostaria de confirmar o pedido dos extras do projeto. <br> <a href="http://localhost:4200/external/extras/' . $checkinId . '/' . $hash . '"> Para confirmar clique aqui. </a>';

            // Enviar o e-mail
            $mail->send();
        } catch (Exception $e) {
            #echo "Erro ao enviar mensagem: {$mail->ErrorInfo}";
        }

        return response()->json(['error' => 'false', 'message' => 'Email de confirmação enviado ao cliente.']);
    }

    public static function sendMailCheckinAccept(Request $request)
    {
        $checkinId = $request->checkin_id;

        $checkin = Checkin::getUnique($checkinId);
        $extra = Extra::where('job_id', $checkin->job_id)->first();
        
        $mail = new PHPMailer(true);

        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        $hash =  vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $extra->update([
            'hash' => $hash
        ]);

        #$client = Client::get($checkin->client_email);
        $email = $checkin->client_email;
        $nome = "";

        if (!$email) {
            return response()->json(['error' => 'false', 'message' => 'Sem E-mail do destinatário.']);
        }

        try {
            // Configurações do servidor SMTP do Gmail
            $mail->isSMTP();

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            
            $mail->Username = 'think.ideias.1@gmail.com'; // Seu endereço de e-mail
            $mail->Password = 'dhqg bibw laok mawt';  // Senha de app gerada no Google
            
            
            //local
            //$mail->Username = 'gui9788534514088@gmail.com'; // Seu endereço de e-mail
            //$mail->Password = 'amky uxiz mkxx huif';  // Senha de app gerada no Google
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->CharSet = 'UTF-8';
            //$mail = utf8_decode($_POST['mensagem']);

            // Remetente
            $mail->setFrom('no-reply@think.com', 'Think Support'); // Altere aqui para o e-mail e nome desejado


            #$mail->addAddress($checkin->organization_login, $checkin->client_object->name); // Adicione o destinatário
            $mail->addAddress($email, $nome); // Adicione o destinatário

            // Conteúdo do e-mail
            $mail->isHTML(true);
            $mail->Subject = 'Obrigado pela parceria';

            $mail->Body    = '<!DOCTYPE html>
                <html lang="pt-BR">
                <head>
                    <meta charset="UTF-8" />
                    <title>Agradecimento e Solicitação</title>
                </head>
                <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; background-color: #ffffff;">
                    <!-- Cabeçalho -->
                    <tr>
                        <td align="center" style="padding: 20px 0; background-color: #0056b3; color: #ffffff;">
                        <h1 style="margin: 0; font-size: 24px; font-weight: bold;">Obrigado pela Parceria!</h1>
                        </td>
                    </tr>
                    <!-- Corpo -->
                    <tr>
                        <td style="padding: 30px; color: #333333; text-align: center; font-size: 16px;">
                        <p style="margin: 0;">
                            Olá! 😊<br /><br />
                            Gostaríamos de expressar nossa gratidão pela confiança e parceria. Para
                            prosseguirmos com o próximo passo, solicitamos gentilmente que clique no botão abaixo para realizar o aceite.
                        </p>
                        <br />
                        <!-- Botão -->
                        <table align="center" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                            <td align="center" style="background-color: #28a745; border-radius: 4px;">
                                <a href="http://localhost:4200/external/check-in/' . $checkinId . '/' . $hash . '/1" target="_blank" style="display: block; padding: 12px 20px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold; font-family: Arial, sans-serif;">
                                Aceitar Proposta
                                </a>
                            </td>
                            </tr>
                            <tr>
                                <td align="center" style="display: flex;">
                                <a href="http://localhost:4200/external/check-in/' . $checkinId . '/' . $hash . '/2" target="_blank" style="display: block; font-size: 10px; font-family: Arial, sans-serif; margin: auto; margin-top: 20px;">
                                    Recusar proposta
                                </a>
                                </td>
                            </tr>
                        </table>
                        </td>
                    </tr>
                    <!-- Rodapé -->
                    <tr>
                        <td align="center" style="padding: 20px; font-size: 12px; color: #777777; background-color: #f5f5f5;">
                        Caso tenha alguma dúvida, não hesite em nos contatar.<br />
                        <strong>Think</strong>
                        </td>
                    </tr>
                    </table>
                </body>
                </html>';

            // Enviar o e-mail
            $mail->send();
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => "Erro ao enviar mensagem: {$mail->ErrorInfo}"]);
        }

        return response()->json(['error' => 'false', 'message' => 'Email de confirmação enviado ao cliente.']);
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
