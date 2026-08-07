<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CedenteAudit extends Model
{
    public const EVENT_CADASTRO_CRIADO = 'cadastro_criado';

    public const EVENT_STATUS_ALTERADO = 'status_alterado';

    public const EVENT_VALIDACAO_INICIADA = 'validacao_iniciada';

    public const EVENT_VALIDACAO_SERPRO_CHAMADA = 'validacao_serpro_chamada';

    public const EVENT_VALIDACAO_SERPRO = 'validacao_serpro';

    public const EVENT_VALIDACAO_SERPRO_ERRO = 'validacao_serpro_erro';

    public const EVENT_AVALIACAO_REGISTRADA = 'avaliacao_registrada';

    public const EVENT_ARQUIVO_VALIDADO = 'arquivo_validado';

    public const EVENT_ARQUIVO_RECUSADO = 'arquivo_recusado';

    public const EVENT_CEDENTE_ATUALIZADO = 'cedente_atualizado';

    public const EVENT_DAYCOVAL_CADASTRO_CHAMADA = 'daycoval_cadastro_chamada';

    public const EVENT_DAYCOVAL_CADASTRO = 'daycoval_cadastro';

    public const EVENT_DAYCOVAL_CADASTRO_ERRO = 'daycoval_cadastro_erro';

    public const EVENT_VALIDACAO_VADU_CHAMADA = 'validacao_vadu_chamada';

    public const EVENT_VALIDACAO_VADU = 'validacao_vadu';

    public const EVENT_VALIDACAO_VADU_ERRO = 'validacao_vadu_erro';

    protected $table = 'cedente_audit';

    protected $fillable = [
        'cedente_id',
        'user_id',
        'event',
        'old_status',
        'new_status',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function cedente()
    {
        return $this->belongsTo(Cedente::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
