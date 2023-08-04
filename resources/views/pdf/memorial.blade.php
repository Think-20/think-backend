<html !DOCTYPE>
    <head>
        <meta charset="UTF-8">
        <style>
            @page {
                margin: 0;
            }
            body {
                padding: 60px 50px;
                background-color: #f1f1ef;
                background-image: url('data:image/jpg;base64,{{ $bg }}');
                background-position: top left;
                background-repeat: no-repeat;
                font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif !important;
            }

            p, td {
                color: #333;
                font-size: 14px;
                letter-spacing: -1px;
                margin: 0px 5px;
            }

            .space-down {
                margin-bottom: 50px;
            }

            table {
                width: 100%;
                margin-bottom: 10px;
            }

            table, td {
                font-size: 13px;
                border-collapse: collapse;
                border: 1px solid #e7e7e7;
            }

            td {
                padding: 5px;
            }

            .line-title {
                background-color: #edefee;
            }

            .line-title .nivel, .line-title .title {
                font-weight: bold;
            }

            .title {
                width: 76.5%;
            }

            .nivel {
                width: 5%;
            }  

            .description {
                width: 50%;
                padding-left: 15px;
            }    

            .line-title .description {
                padding-left: 5px;
            }

            .center {
                text-align: center;
            }
        </style>
    </head>
    <body>
        <p class="space-down">São Paulo, {{ strftime('%d', strtotime($task->getAvailableDate())) . ' de ' 
        . ucwords(strftime('%B', strtotime($task->getAvailableDate()))) . ' de ' 
        . strftime('%Y', strtotime($task->getAvailableDate())) }}</p>
        <p class="space-down">
            <strong>Á {{ ($task->job->not_client) ? $task->job->agency->name : $task->job->client->name }}</strong>
            <br>Att. Sr(a). {{ ($task->job->not_client) ? $task->job->agency->contacts[0]->name : $task->job->client->contacts[0]->name }}
        </p>
        <br>
        <p class="center space-down">
            <strong>PROPOSTA E MEMORIAL DESCRITIVO DE PROJETO, MONTAGEM E DESMONTAGEM DE STAND</strong>
        </p>
        <div class="space-down">
            <p>Stand: {{ ($task->job->not_client) ? $task->job->not_client : $task->job->client->fantasy_name }}</p>
            <p>Área: -</p>
            <p>Evento: {{ $task->job->event }}</p>
            <p>Local: {{ $task->job->place }}</p>
            <p>Cidade: -</p>
            <p>Data: -</p>
        </div>
        
        <table class="title-table">
            <tr class="line-title">
                <td class="nivel">1.0</td>
                <td class="title">PISO</td>
                <td class="value">R$ 3.750,00</td>
            </tr>
        </table>
        
        <table class="subtitle-table">
            <tr class="line-title">
                <td class="nivel">&nbsp;</td>
                <td class="description">1.1 - ESTRUTURA</td>
                <td class="un">unidade</td>
                <td class="qtd">quantidade</td>
                <td class="value">valor</td>
                <td class="total">total</td>
            </tr>
            <tr>
                <td class="nivel">C</td>
                <td class="description">Tablado elevado á 10cm nivelado e chapeado.</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
            <tr>
                <td class="nivel">&nbsp;</td>
                <td class="description">Tablado elevado á 30cm nivelado e chapeado.</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
        </table>
        
        <table class="subtitle-table">
            <tr class="line-title">
                <td class="nivel">&nbsp;</td>
                <td class="description">1.2 - ELEVAÇÃO ADICIONAL</td>
                <td class="un">unidade</td>
                <td class="qtd">quantidade</td>
                <td class="value">valor</td>
                <td class="total">total</td>
            </tr>
            <tr>
                <td class="nivel">C</td>
                <td class="description">Praticável com elevação personalizada em Xcm nivelado e chapeado.</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
        </table>
        
        <table class="subtitle-table">
            <tr class="line-title">
                <td class="nivel">&nbsp;</td>
                <td class="description">1.3 - REVESTIMENTOS</td>
                <td class="un">unidade</td>
                <td class="qtd">quantidade</td>
                <td class="value">valor</td>
                <td class="total">total</td>
            </tr>
            <tr>
                <td class="nivel">C</td>
                <td class="description">MDF Laminado (Tipos)</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
            <tr>
                <td class="nivel">&nbsp;</td>
                <td class="description">Carpete Forração (Tipos)</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
            <tr>
                <td class="nivel">&nbsp;</td>
                <td class="description">Grama Sintética (Tipos)</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
            <tr>
                <td class="nivel">&nbsp;</td>
                <td class="description">Piso Vinílico (Tipos)</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
            <tr>
                <td class="nivel">&nbsp;</td>
                <td class="description">Vidro Retroiluminado (Tipos)</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
            <tr>
                <td class="nivel">&nbsp;</td>
                <td class="description">Deck de Madeira (Tipos)</td>
                <td class="un">&nbsp;</td>
                <td class="qtd">&nbsp;</td>
                <td class="value">&nbsp;</td>
                <td class="total">&nbsp;</td>
            </tr>
        </table>
    </body>
</html>
