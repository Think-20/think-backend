<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BankAccount extends Model
{
    protected $table = 'bank_account';

    protected $fillable = [
        'name', 'agency', 'account_number', 'bank_account_type_id', 'bank_id', 'registration_date'
    ];

    protected $casts = [
        'registration_date' => 'date',
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
     * Lê `id` de objeto/array vindo do JSON (array, stdClass ou Collection).
     *
     * @param mixed $value
     * @return int|null
     */
    private static function readIdFromNestedContainer($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            if (!array_key_exists('id', $value)) {
                return null;
            }
            $id = $value['id'];
        } elseif ($value instanceof Collection) {
            if (!$value->has('id')) {
                return null;
            }
            $id = $value->get('id');
        } elseif (is_object($value)) {
            if (!isset($value->id)) {
                return null;
            }
            $id = $value->id;
        } else {
            return null;
        }

        if ($id === null || $id === '') {
            return null;
        }

        return (int) $id;
    }

    /**
     * Prioriza `bank_id` explícito (edição no Postman / APIs); senão `bank.id` quando vier objeto aninhado.
     *
     * @param array $data
     * @return int|null null = não enviado
     */
    private static function resolveBankIdFromPayload(array $data)
    {
        if (array_key_exists('bank_id', $data) && $data['bank_id'] !== null && $data['bank_id'] !== '') {
            return (int) $data['bank_id'];
        }
        if (array_key_exists('bank', $data) && $data['bank'] !== null && $data['bank'] !== '') {
            $fromNested = static::readIdFromNestedContainer($data['bank']);
            if ($fromNested !== null && $fromNested > 0) {
                return $fromNested;
            }
        }

        return null;
    }

    /**
     * Prioriza `bank_account_type_id` explícito; senão `bank_account_type.id` quando vier objeto aninhado.
     *
     * @param array $data
     * @return int|null null = não enviado
     */
    private static function resolveBankAccountTypeIdFromPayload(array $data)
    {
        if (array_key_exists('bank_account_type_id', $data) && $data['bank_account_type_id'] !== null && $data['bank_account_type_id'] !== '') {
            return (int) $data['bank_account_type_id'];
        }
        if (array_key_exists('bank_account_type', $data) && $data['bank_account_type'] !== null && $data['bank_account_type'] !== '') {
            $fromNested = static::readIdFromNestedContainer($data['bank_account_type']);
            if ($fromNested !== null && $fromNested > 0) {
                return $fromNested;
            }
        }

        return null;
    }

    /**
     * @return BankAccount|false
     */
    public static function edit(array $data)
    {
        if (!isset($data['id'])) {
            return false;
        }
        $bankAccount = BankAccount::find($data['id']);
        if (!$bankAccount) {
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
        if (array_key_exists('agency', $data)) {
            $a = static::normalizeString($data['agency']);
            if ($a === null) {
                throw new InvalidArgumentException('Agencia invalida');
            }
            $updates['agency'] = $a;
        }
        if (array_key_exists('account_number', $data)) {
            $an = static::normalizeString($data['account_number']);
            if ($an === null) {
                throw new InvalidArgumentException('Conta invalida');
            }
            $updates['account_number'] = $an;
        }
        $bankId = static::resolveBankIdFromPayload($data);
        if ($bankId !== null) {
            if ($bankId <= 0) {
                throw new InvalidArgumentException('Banco invalido');
            }
            $updates['bank_id'] = $bankId;
        }
        $typeId = static::resolveBankAccountTypeIdFromPayload($data);
        if ($typeId !== null) {
            $updates['bank_account_type_id'] = $typeId ?: 1;
        }
        if (array_key_exists('registration_date', $data)) {
            $updates['registration_date'] = $data['registration_date'] ?: null;
        }
        if (empty($updates)) {
            return $bankAccount;
        }

        $bankAccount->update($updates);

        return $bankAccount;
    }

    public static function insert(array $data)
    {
        $name = static::normalizeString(isset($data['name']) ? $data['name'] : null);
        if ($name === null) {
            throw new InvalidArgumentException('Nome invalido');
        }
        $agency = static::normalizeString(isset($data['agency']) ? $data['agency'] : null);
        if ($agency === null) {
            throw new InvalidArgumentException('Agencia invalida');
        }
        $accountNumber = static::normalizeString(isset($data['account_number']) ? $data['account_number'] : null);
        if ($accountNumber === null) {
            throw new InvalidArgumentException('Conta invalida');
        }
        $bankId = static::resolveBankIdFromPayload($data);
        if ($bankId === null || $bankId <= 0) {
            throw new InvalidArgumentException('Banco invalido');
        }
        $typeId = static::resolveBankAccountTypeIdFromPayload($data);
        if ($typeId === null) {
            $typeId = 1;
        }
        $typeId = $typeId ?: 1;

        $attrs = [
            'name' => $name,
            'agency' => $agency,
            'account_number' => $accountNumber,
            'bank_id' => $bankId,
            'bank_account_type_id' => $typeId ?: 1,
        ];
        if (array_key_exists('registration_date', $data) && $data['registration_date'] !== null && $data['registration_date'] !== '') {
            $attrs['registration_date'] = $data['registration_date'];
        }

        $bankAccount = new BankAccount($attrs);
        $bankAccount->save();

        return $bankAccount;
    }

    /**
     * @return bool
     */
    public static function remove($id)
    {
        $bankAccount = BankAccount::find($id);
        if (!$bankAccount) {
            return false;
        }
        $bankAccount->delete();

        return true;
    }

    public static function list(array $data)
    {
        $paginate = isset($data['paginate'])
            ? filter_var($data['paginate'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $search = isset($data['search']) ? static::normalizeString($data['search']) : null;
        $query = BankAccount::with('bank', 'bankAccountType')->withCount('transactions');

        if (!is_null($search) && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('agency', 'LIKE', '%' . $search . '%')
                    ->orWhere('account_number', 'LIKE', '%' . $search . '%');
            });
        }

        $query->orderBy('name', 'asc');

        if ($paginate) {
            $bankAccounts = $query->paginate(20);
        } else {
            $bankAccounts = ['data' => $query->get()];
        }

        return [
            'pagination' => $bankAccounts,
            'updatedInfo' => BankAccount::updatedInfo()
        ];
    }

    public static function filter(array $data)
    {
        return BankAccount::list($data);
    }

    public static function updatedInfo()
    {
        $last = BankAccount::orderBy('updated_at', 'desc')->first();
        if ($last === null) {
            return [];
        }

        return [
            'date' => (new DateTime($last->updated_at))->format('d/m/Y'),
        ];
    }

    public static function get(int $id)
    {
        return BankAccount::with('bank', 'bankAccountType')->withCount('transactions')->find($id);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function bankAccountType()
    {
        return $this->belongsTo(BankAccountType::class, 'bank_account_type_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'bank_account_id');
    }
}
