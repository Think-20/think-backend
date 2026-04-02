<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Category extends Model
{
    protected $table = 'category';

    protected $fillable = [
        'name', 'theme'
    ];

    /**
     * @param string|null $name
     * @return string|null
     */
    public static function normalizeName($name)
    {
        if ($name === null) {
            return null;
        }
        $t = trim((string) $name);

        return $t === '' ? null : $t;
    }

    public static function edit(array $data)
    {
        if (!isset($data['id'])) {
            return false;
        }
        $category = Category::find($data['id']);
        if (!$category) {
            return false;
        }
        $updates = [];
        if (array_key_exists('name', $data)) {
            $n = static::normalizeName($data['name']);
            if ($n === null) {
                throw new InvalidArgumentException('Nome invalido');
            }
            $updates['name'] = $n;
        }
        if (array_key_exists('theme', $data)) {
            $theme = (int) $data['theme'];
            if ($theme < 1 || $theme > 18) {
                throw new InvalidArgumentException('Tema invalido');
            }
            $updates['theme'] = $theme;
        }
        if (empty($updates)) {
            return true;
        }

        return $category->update($updates);
    }

    public static function insert(array $data)
    {
        $name = static::normalizeName(isset($data['name']) ? $data['name'] : null);
        if ($name === null) {
            throw new InvalidArgumentException('Nome invalido');
        }
        $theme = isset($data['theme']) ? (int) $data['theme'] : 1;
        if ($theme < 1 || $theme > 18) {
            throw new InvalidArgumentException('Tema invalido');
        }
        $category = new Category(['name' => $name, 'theme' => $theme]);
        $category->save();

        return $category;
    }

    /**
     * @return bool
     */
    public static function remove($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return false;
        }
        $category->delete();

        return true;
    }

    public static function list(array $data)
    {
        $paginate = isset($data['paginate'])
            ? filter_var($data['paginate'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $search = isset($data['search']) ? static::normalizeName($data['search']) : null;
        $query = Category::query()->withCount('transactions');

        if (!is_null($search) && $search !== '') {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('name', 'asc');

        if ($paginate) {
            $categories = $query->paginate(20);
        } else {
            $categories = ['data' => $query->get()];
        }

        return [
            'pagination' => $categories,
            'updatedInfo' => Category::updatedInfo()
        ];
    }

    public static function filter(array $data)
    {
        return Category::list($data);
    }

    public static function updatedInfo()
    {
        $last = Category::orderBy('updated_at', 'desc')->first();
        if ($last === null) {
            return [];
        }

        return [
            'date' => (new DateTime($last->updated_at))->format('d/m/Y'),
        ];
    }

    public static function get(int $id)
    {
        return Category::withCount('transactions')->find($id);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'category_id');
    }
}
