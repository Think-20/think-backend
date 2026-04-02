<?php

namespace App\Http\Controllers;

use App\Tag;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class TagController extends Controller
{
    public static function all(Request $request)
    {
        return Tag::list($request->all());
    }

    public static function filter(Request $request)
    {
        return Tag::filter($request->all());
    }

    public static function get(int $id)
    {
        return Tag::get($id);
    }

    public static function save(Request $request)
    {
        try {
            Tag::insert($request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => 'Descricao invalida'], 400);
        } catch (QueryException $queryException) {
            if ($queryException->getCode() == 23000) {
                return response()->json(['error' => 'true', 'message' => 'Tag ja cadastrada'], 200);
            }

            return response()->json(['error' => 'true', 'message' => 'Erro ao cadastrar no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao cadastrar'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Tag cadastrada com sucesso']);
    }

    public static function edit(Request $request)
    {
        try {
            $ok = Tag::edit($request->all());
            if ($ok === false) {
                $id = isset($request->id) ? $request->id : '';

                return response()->json(['error' => 'true', 'message' => 'Tag ' . $id . ' nao encontrada'], 400);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => 'Descricao invalida'], 400);
        } catch (QueryException $queryException) {
            if ($queryException->getCode() == 23000) {
                return response()->json(['error' => 'true', 'message' => 'Tag ja cadastrada'], 200);
            }

            return response()->json(['error' => 'true', 'message' => 'Erro ao atualizar no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao atualizar'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Tag atualizada com sucesso']);
    }

    public static function remove(int $id)
    {
        try {
            if (!Tag::remove($id)) {
                return response()->json(['error' => 'true', 'message' => 'Tag ' . $id . ' nao encontrada'], 400);
            }
        } catch (QueryException $queryException) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao excluir no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao excluir'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Tag excluida com sucesso']);
    }
}
