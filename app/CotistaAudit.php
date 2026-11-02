<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaAudit extends Model
{
    public const EVENT_CADASTRO_CRIADO = 'cadastro_criado';

    public const EVENT_STATUS_ALTERADO = 'status_alterado';

    public const EVENT_VALIDACAO_INICIADA = 'validacao_iniciada';

    public const EVENT_AVALIACAO_REGISTRADA = 'avaliacao_registrada';

    public const EVENT_ARQUIVO_VALIDADO = 'arquivo_validado';

    public const EVENT_ARQUIVO_RECUSADO = 'arquivo_recusado';

    public const EVENT_COTISTA_ATUALIZADO = 'cotista_atualizado';

    protected $table = 'cotista_audit';

    protected $fillable = [
        'cotista_id',
        'user_id',
        'event',
        'old_status',
        'new_status',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
