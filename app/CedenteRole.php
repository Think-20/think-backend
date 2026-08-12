<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CedenteRole extends Model
{
    protected $table = 'cedente_role';

    protected $fillable = [
        'code',
        'name',
    ];

    public const CODE_PREENCHIMENTO = 'preenchimento';
    public const CODE_AVALISTA = 'avalista';
    public const CODE_ADMINISTRADOR = 'administrador';

    /** IDs canônicos do seed (migrations) — use estes nas regras de permissão. */
    public const ID_PREENCHIMENTO = 1;
    public const ID_AVALISTA = 2;
    public const ID_ADMINISTRADOR = 3;

    /** Label exibida na API para o papel id 2 (code continua `avalista`). */
    public const LABEL_APROVADOR = 'Aprovador';

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'cedente_role_employee', 'cedente_role_id', 'employee_id')
            ->withTimestamps();
    }

    /**
     * Nome amigável para resposta da API (avalista → Aprovador).
     *
     * @return string
     */
    public function displayName()
    {
        if ((int) $this->id === self::ID_AVALISTA || $this->code === self::CODE_AVALISTA) {
            return self::LABEL_APROVADOR;
        }

        return $this->name;
    }

    /**
     * Payload padrão `{ id, code, name }` para login / user.
     *
     * @return array{id: int, code: string, name: string}
     */
    public function toApiArray()
    {
        return [
            'id' => (int) $this->id,
            'code' => $this->code,
            'name' => $this->displayName(),
        ];
    }

    /**
     * @param string $code
     * @return CedenteRole|null
     */
    public static function findByCode($code)
    {
        return static::where('code', $code)->first();
    }

    /**
     * @param int $employeeId
     * @return CedenteRole|null
     */
    public static function forEmployee($employeeId)
    {
        if ($employeeId === null) {
            return null;
        }

        return static::query()
            ->whereIn('id', function ($q) use ($employeeId) {
                $q->select('cedente_role_id')
                    ->from('cedente_role_employee')
                    ->where('employee_id', (int) $employeeId);
            })
            ->first();
    }

    /**
     * @param int $employeeId
     * @return int|null
     */
    public static function idForEmployee($employeeId)
    {
        $role = static::forEmployee($employeeId);

        return $role ? (int) $role->id : null;
    }

    /**
     * @param int $employeeId
     * @return string|null
     */
    public static function codeForEmployee($employeeId)
    {
        $role = static::forEmployee($employeeId);

        return $role ? $role->code : null;
    }

    /**
     * Atrela (ou troca) o papel de cedente do employee.
     *
     * @param int $employeeId
     * @param string $code
     * @return CedenteRole
     */
    public static function assignToEmployee($employeeId, $code)
    {
        $role = static::findByCode($code);
        if (!$role) {
            throw new \InvalidArgumentException('Papel de cedente invalido');
        }

        $now = date('Y-m-d H:i:s');
        $exists = DB::table('cedente_role_employee')
            ->where('employee_id', (int) $employeeId)
            ->first();

        if ($exists) {
            DB::table('cedente_role_employee')
                ->where('employee_id', (int) $employeeId)
                ->update([
                    'cedente_role_id' => $role->id,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('cedente_role_employee')->insert([
                'cedente_role_id' => $role->id,
                'employee_id' => (int) $employeeId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $role;
    }
}
