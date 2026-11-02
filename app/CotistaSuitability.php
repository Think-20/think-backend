<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaSuitability extends Model
{
    protected $table = 'cotista_suitability';

    public const PERGUNTAS = 7;

    public const OPCOES = 4;

    protected $fillable = [
        'cotista_id',
        'perfil',
    ];

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }

    public function respostas()
    {
        return $this->hasMany(CotistaSuitabilityResposta::class);
    }

    /**
     * Enunciados fixos. Os textos definitivos ainda nao foram definidos;
     * o numero da pergunta (1–7) e da opcao (1–4) e o que o cadastro persiste.
     *
     * @return array<int, array{enunciado: string, opcoes: array<int, string>}>
     */
    public static function perguntas()
    {
        $opcoes = [
            1 => 'Opcao 1',
            2 => 'Opcao 2',
            3 => 'Opcao 3',
            4 => 'Opcao 4',
        ];

        $out = [];
        for ($n = 1; $n <= self::PERGUNTAS; $n++) {
            $out[$n] = [
                'enunciado' => 'Pergunta ' . $n,
                'opcoes' => $opcoes,
            ];
        }

        return $out;
    }

    /**
     * Converte as respostas no perfil do cotista.
     * A regra de pontuacao ainda nao foi definida: devolve null.
     *
     * @param array<int, int> $respostas mapa pergunta => opcao
     * @return string|null
     */
    public static function atribuirPerfil(array $respostas)
    {
        unset($respostas);

        return null;
    }
}
