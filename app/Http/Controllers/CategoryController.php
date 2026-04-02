<?php

namespace App\Http\Controllers;

use App\Category;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class CategoryController extends Controller
{
    public static function all(Request $request)
    {
        return Category::list($request->all());
    }

    public static function filter(Request $request)
    {
        return Category::filter($request->all());
    }

    public static function get(int $id)
    {
        return Category::get($id);
    }

    public static function save(Request $request)
    {
        try {
            Category::insert($request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $queryException) {
            if ($queryException->getCode() == 23000) {
                return response()->json(['error' => 'true', 'message' => 'Categoria ja cadastrada'], 200);
            }

            return response()->json(['error' => 'true', 'message' => 'Erro ao cadastrar no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao cadastrar'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Categoria cadastrada com sucesso']);
    }

    public static function edit(Request $request)
    {
        try {
            $ok = Category::edit($request->all());
            if ($ok === false) {
                $id = isset($request->id) ? $request->id : '';

                return response()->json(['error' => 'true', 'message' => 'Categoria ' . $id . ' nao encontrada'], 400);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (QueryException $queryException) {
            if ($queryException->getCode() == 23000) {
                return response()->json(['error' => 'true', 'message' => 'Categoria ja cadastrada'], 200);
            }

            return response()->json(['error' => 'true', 'message' => 'Erro ao atualizar no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao atualizar'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Categoria atualizada com sucesso']);
    }

    public static function remove(int $id)
    {
        try {
            if (!Category::remove($id)) {
                return response()->json(['error' => 'true', 'message' => 'Categoria ' . $id . ' nao encontrada'], 400);
            }
        } catch (QueryException $queryException) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao excluir no banco de dados'], 400);
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Erro desconhecido ao excluir'], 400);
        }

        return response()->json(['error' => 'false', 'message' => 'Categoria excluida com sucesso']);
    }
}
