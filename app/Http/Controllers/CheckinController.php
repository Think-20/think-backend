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

    public static function sendMailCheckin()
    {

        $mail = new PHPMailer(true);


        //Tentativa com gmail
        try {
            // Configurações do servidor SMTP do Gmail
            $mail->isSMTP();
            $mail->SMTPDebug = 2; // ou 3 para mais informações detalhadas
            $mail->Debugoutput = 'html';

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'gui9788534514088@gmail.com'; // Seu endereço de e-mail
            $mail->Password = 'amky uxiz mkxx huif';  // Senha de app gerada no Google
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Remetente e destinatário
            $mail->setFrom('gui9788534514088@gmail.com', 'Douglas');
            $mail->addAddress('guibarbosa28@outlook.com', 'Guilherme Barbosa'); // Adicione o destinatário

            // Conteúdo do e-mail
            $mail->isHTML(true);
            $mail->Subject = 'Teste de Email';
            $mail->Body    = 'Esse e um teste de envio de e-mail usando PHPMailer com Gmail.<br> <a href="http://localhost:8000/testeEmail/1">Clicke aqui para confirmar check-in</a>';

            // Enviar o e-mail
            $mail->send();
            echo 'Mensagem enviada com sucesso!';
        } catch (Exception $e) {
            echo "Erro ao enviar mensagem: {$mail->ErrorInfo}";
        }

        return response()->json(['error' => 'false', 'message' => 'Email de confirmação enviado ao cliente.']);
    }

    public static function confirmMailCheckin(int $id)
    {
        $checkin = Checkin::find($id);

        if ($checkin->accept_client == 0) {
            $checkin->update([
                'accept_client' => 1,
                'accept_client_date' => Carbon::now()
            ]);
            return response()->json(['error' => 'false', 'message' => 'Checkin confirmado.']);
        } else {
            return response()->json(['error' => 'false', 'message' => 'Checkin já confirmado na data ' . $checkin->accept_client_date . '.']);
        }
    }
}


        //Tentando com outlook e
        /*try {
            // Configurações do servidor SMTP
            $mail->isSMTP();
            $mail->SMTPDebug = 2; // ou 3 para mais informações detalhadas
            $mail->Debugoutput = 'html';

            $mail->Host = 'smtp.office365.com'; // Servidor SMTP
            $mail->Port = 587; // Porta TCP (normalmente 587 para tls ou 465 para ssl)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Criptografia (tls ou ssl)
            $mail->SMTPAuth = true;


            //$mail->Username = 'think_ideias@outlook.com'; // Usuário SMTP
            //$mail->Password = 'hprtmpszaeqwvfys'; // Senha SMTP

            // Provedor OAuth2
            $provider = new GenericProvider([
                'clientId'                => 'CLIENT_ID_AQUI',
                'clientSecret'            => 'CLIENT_SECRET_AQUI',
                'redirectUri'             => 'https://example.com/callback-url',
                'urlAuthorize'            => 'https://login.microsoftonline.com/TENANT_ID_AQUI/oauth2/v2.0/authorize',
                'urlAccessToken'          => 'https://login.microsoftonline.com/TENANT_ID_AQUI/oauth2/v2.0/token',
                'urlResourceOwnerDetails' => '',
                'scopes'                  => ['https://outlook.office365.com/.default'],
            ]);

            // Token de acesso
            $accessToken = $provider->getAccessToken('password', [
                'username' => 'think_ideias@outlook.com',
                'password' => 'hprtmpszaeqwvfys',
            ]);

            // Configuração OAuth2 para PHPMailer
            $mail->setOAuth(
                new OAuth([
                    'provider'       => $provider,
                    'clientId'       => 'CLIENT_ID_AQUI',
                    'clientSecret'   => 'CLIENT_SECRET_AQUI',
                    'refreshToken'   => $accessToken->getToken(),
                    'userName'       => 'think_ideias@outlook.com',
                ])
            );


            // Remetente e destinatário
            $mail->setFrom('think_ideias@outlook.com', 'Think');
            $mail->addAddress('guibarbosa28@outlook.com', 'Douglas');

            // Conteúdo do email
            $mail->isHTML(true); // Definir o formato como HTML
            $mail->Subject = 'Confirmação de Pedido';
            $mail->Body    = 'Olá, por favor, confirme seu pedido clicando no link abaixo:<br><br>';
            $mail->Body   .= '<a href="http://www.seusite.com/confirmar_pedido?codigo=12345">Confirmar Pedido</a>';
            $mail->AltBody = 'Olá, por favor, confirme seu pedido clicando no link: http://www.seusite.com/confirmar_pedido?codigo=12345';

            $mail->send();
            echo 'Email enviado com sucesso';
        } catch (Exception $e) {
            echo "Erro ao enviar o email: {$mail->ErrorInfo}";
        }*/