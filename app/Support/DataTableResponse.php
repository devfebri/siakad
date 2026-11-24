<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jawaban JSON untuk jQuery DataTables mode server-side.
 *
 * Hanya halaman yang diminta yang diambil dari database (OFFSET/LIMIT), jadi tetap cepat
 * walaupun tabel berisi ratusan ribu baris. Kolom pencarian/urutan dibatasi whitelist.
 */
class DataTableResponse
{
    public const MAX_PAGE_SIZE = 100;

    /**
     * @param  array<int, string>  $searchable  kolom yang dicari dengan LIKE
     * @param  array<int, string>  $orderable  kolom yang boleh dipakai untuk mengurutkan
     * @param  Closure(mixed): array  $map  mengubah satu model menjadi baris JSON
     * @param  array<string, array<int, string>>  $searchRelations  pencarian lewat relasi, mis. ['user' => ['email']]
     */
    public static function make(
        Request $request,
        Builder $query,
        array $searchable,
        array $orderable,
        Closure $map,
        ?string $defaultOrder = null,
        string $defaultDirection = 'desc',
        array $searchRelations = [],
    ): JsonResponse {
        $start = max(0, (int) $request->input('start', 0));
        $length = min(self::MAX_PAGE_SIZE, max(1, (int) $request->input('length', 10)));

        $total = (clone $query)->count();

        $term = trim((string) data_get($request->input('search'), 'value', ''));
        if ($term !== '' && ($searchable || $searchRelations)) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $q) use ($searchable, $searchRelations, $like) {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'like', $like);
                }
                foreach ($searchRelations as $relation => $columns) {
                    $q->orWhereHas($relation, function (Builder $r) use ($columns, $like) {
                        $r->where(function (Builder $w) use ($columns, $like) {
                            foreach ($columns as $column) {
                                $w->orWhere($column, 'like', $like);
                            }
                        });
                    });
                }
            });
        }
        $filtered = $term === '' ? $total : (clone $query)->count();

        [$column, $direction] = self::resolveOrder($request, $orderable, $defaultOrder ?? $query->getModel()->getKeyName(), $defaultDirection);
        $query->orderBy($column, $direction);
        $key = $query->getModel()->getKeyName();
        if ($column !== $key) {
            $query->orderBy($key, 'desc'); // urutan stabil antar halaman
        }

        $rows = $query->offset($start)->limit($length)->get()->map($map)->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    /** @return array{0: string, 1: string} */
    private static function resolveOrder(Request $request, array $orderable, string $default, string $defaultDirection): array
    {
        $order = $request->input('order.0');
        $index = is_array($order) ? ($order['column'] ?? null) : null;
        $column = $index !== null ? data_get($request->input('columns'), "$index.data") : null;

        if (! is_string($column) || ! in_array($column, $orderable, true)) {
            return [$default, $defaultDirection];
        }

        return [$column, ($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc'];
    }
}
