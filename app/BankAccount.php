<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Model;
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
        if (array_key_exists('bank_id', $data)) {
            $bid = (int) $data['bank_id'];
            if ($bid <= 0) {
                throw new InvalidArgumentException('Banco invalido');
            }
            $updates['bank_id'] = $bid;
        }
        if (array_key_exists('bank_account_type_id', $data)) {
            $updates['bank_account_type_id'] = (int) $data['bank_account_type_id'] ?: 1;
        }
        if (array_key_exists('registration_date', $data)) {
            $updates['registration_date'] = $data['registration_date'] ?: null;
        }
        if (empty($updates)) {
            return true;
        }

        return $bankAccount->update($updates);
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
        $bankId = isset($data['bank_id']) ? (int) $data['bank_id'] : 0;
        if ($bankId <= 0) {
            throw new InvalidArgumentException('Banco invalido');
        }
        $typeId = isset($data['bank_account_type_id']) && $data['bank_account_type_id'] !== ''
            ? (int) $data['bank_account_type_id']
            : 1;

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
