<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class Fund extends Model
{
    protected $table = 'fund';

    protected $fillable = [
        'name',
        'type',
        'code',
        'cnpj',
        'quantidade_cedente',
        'is_active',
        'deactivated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'quantidade_cedente' => 'decimal:4',
        'deactivated_at' => 'datetime',
    ];

    /**
     * @param string|null $value
     * @return string|null
     */
    public static function normalizeString($value)
    {
        if ($value === null) {
            return null;
        }
        $t = trim((string) $value);

        return $t === '' ? null : $t;
    }

    /**
     * @param string|null $value
     * @return string|null
     */
    public static function normalizeCnpj($value)
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    public static function insert(array $data)
    {
        $name = static::normalizeString(isset($data['name']) ? $data['name'] : null);
        if ($name === null) {
            throw new InvalidArgumentException('Nome invalido');
        }
        $type = static::normalizeString(isset($data['type']) ? $data['type'] : null);
        if ($type === null) {
            throw new InvalidArgumentException('Tipo invalido');
        }
        $code = static::normalizeString(isset($data['code']) ? $data['code'] : null);
        if ($code === null) {
            throw new InvalidArgumentException('Codigo invalido');
        }
        $cnpj = static::normalizeCnpj(isset($data['cnpj']) ? $data['cnpj'] : null);
        $quantidadeCedente = isset($data['quantidade_cedente']) ? $data['quantidade_cedente'] : 0;

        $fund = new Fund([
            'name' => $name,
            'type' => $type,
            'code' => $code,
            'cnpj' => $cnpj,
            'quantidade_cedente' => $quantidadeCedente,
            'is_active' => true,
            'deactivated_at' => null,
        ]);
        $fund->save();

        return $fund;
    }

    public static function edit(array $data)
    {
        if (!isset($data['id'])) {
            return false;
        }
        $fund = Fund::find($data['id']);
        if (!$fund) {
            return false;
        }

        $updates = [];
        if (array_key_exists('name', $data)) {
            $n = static::normalizeString($data['name']);
            if ($n === null) {
                throw new InvalidArgumentException('Nome invalido');
            }
            $updates['name'] = $n;
        }
        if (array_key_exists('type', $data)) {
            $t = static::normalizeString($data['type']);
            if ($t === null) {
                throw new InvalidArgumentException('Tipo invalido');
            }
            $updates['type'] = $t;
        }
        if (array_key_exists('code', $data)) {
            $c = static::normalizeString($data['code']);
            if ($c === null) {
                throw new InvalidArgumentException('Codigo invalido');
            }
            $updates['code'] = $c;
        }
        if (array_key_exists('cnpj', $data)) {
            $updates['cnpj'] = static::normalizeCnpj($data['cnpj']);
        }
        if (array_key_exists('quantidade_cedente', $data)) {
            $updates['quantidade_cedente'] = $data['quantidade_cedente'];
        }
        if (array_key_exists('is_active', $data)) {
            $active = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active === null) {
                throw new InvalidArgumentException('is_active invalido');
            }
            $updates['is_active'] = $active;
            $updates['deactivated_at'] = $active ? null : Carbon::now();
        }
        if (empty($updates)) {
            return $fund->fresh();
        }

        if (!$fund->update($updates)) {
            return false;
        }

        return $fund->fresh();
    }

    /**
     * Desativa o fundo (nao remove o registro).
     *
     * @param int|string $id
     * @return bool
     */
    public static function deactivate($id)
    {
        $fund = Fund::find($id);
        if (!$fund) {
            return false;
        }
        if (!$fund->is_active) {
            return true;
        }

        return $fund->update([
            'is_active' => false,
            'deactivated_at' => Carbon::now(),
        ]);
    }

    public static function list(array $data)
    {
        $paginate = isset($data['paginate'])
            ? filter_var($data['paginate'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $search = isset($data['search']) ? static::normalizeString($data['search']) : null;
        $includeInactive = isset($data['include_inactive'])
            ? filter_var($data['include_inactive'], FILTER_VALIDATE_BOOLEAN)
            : false;

        $query = Fund::query()->orderBy('name', 'asc');

        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        if (!is_null($search) && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('code', 'LIKE', '%' . $search . '%')
                    ->orWhere('type', 'LIKE', '%' . $search . '%')
                    ->orWhere('cnpj', 'LIKE', '%' . preg_replace('/\D+/', '', $search) . '%')
                    ->orWhere('cnpj', 'LIKE', '%' . $search . '%');
            });
        }

        if ($paginate) {
            $funds = $query->paginate(20);
        } else {
            $funds = ['data' => $query->get()];
        }

        return [
            'pagination' => $funds,
            'updatedInfo' => Fund::updatedInfo(),
        ];
    }

    public static function filter(array $data)
    {
        return Fund::list($data);
    }

    public static function updatedInfo()
    {
        $last = Fund::orderBy('updated_at', 'desc')->first();
        if ($last === null) {
            return [];
        }

        return [
            'date' => (new DateTime($last->updated_at))->format('d/m/Y'),
        ];
    }

    public static function get(int $id)
    {
        return Fund::find($id);
    }

    public function cedentes()
    {
        return $this->hasMany(Cedente::class, 'fund_id');
    }
}
