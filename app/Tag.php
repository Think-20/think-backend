<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Tag extends Model
{
    protected $table = 'tag';

    protected $fillable = [
        'description'
    ];

    /**
     * @param string|null $description
     * @return string|null texto útil ou null se vazio após trim
     */
    public static function normalizeDescription($description)
    {
        if ($description === null) {
            return null;
        }
        $t = trim((string) $description);

        return $t === '' ? null : $t;
    }

    public static function edit(array $data)
    {
        if (!isset($data['id'])) {
            return false;
        }
        $tag = Tag::find($data['id']);
        if (!$tag) {
            return false;
        }
        if (array_key_exists('description', $data)) {
            $desc = static::normalizeDescription($data['description']);
            if ($desc === null) {
                throw new InvalidArgumentException('Descrição é obrigatória.');
            }
            return $tag->update(['description' => $desc]);
        }

        return true;
    }

    public static function insert(array $data)
    {
        $desc = static::normalizeDescription(isset($data['description']) ? $data['description'] : null);
        if ($desc === null) {
            throw new InvalidArgumentException('Descrição é obrigatória.');
        }
        $tag = new Tag(['description' => $desc]);
        $tag->save();

        return $tag;
    }

    /**
     * @return bool false se a tag não existir
     */
    public static function remove($id)
    {
        $tag = Tag::find($id);
        if (!$tag) {
            return false;
        }
        $tag->delete();

        return true;
    }

    public static function list(array $data)
    {
        $paginate = isset($data['paginate'])
            ? filter_var($data['paginate'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $search = isset($data['search']) ? static::normalizeDescription($data['search']) : null;
        $query = Tag::query()->withCount('transactions');

        if (!is_null($search) && $search !== '') {
            $query->where('description', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('description', 'asc');

        if ($paginate) {
            $tags = $query->paginate(20);
        } else {
            $tags = ['data' => $query->get()];
        }

        return [
            'pagination' => $tags,
            'updatedInfo' => Tag::updatedInfo()
        ];
    }

    public static function filter(array $data)
    {
        return Tag::list($data);
    }

    public static function updatedInfo()
    {
        $last = Tag::orderBy('updated_at', 'desc')->first();
        if ($last === null) {
            return [];
        }

        return [
            'date' => (new DateTime($last->updated_at))->format('d/m/Y'),
        ];
    }

    public static function get(int $id)
    {
        return Tag::withCount('transactions')->find($id);
    }

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'transaction_tag', 'tag_id', 'transaction_id');
    }
}
