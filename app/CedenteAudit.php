<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CedenteAudit extends Model
{
    public const EVENT_CADASTRO_CRIADO = 'cadastro_criado';

    public const EVENT_STATUS_ALTERADO = 'status_alterado';

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
