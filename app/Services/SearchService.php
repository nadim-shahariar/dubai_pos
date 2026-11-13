<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class SearchService
{
    public function search(string $term = '', int $limit = 24): Collection
    {
        $term = trim($term);
        $query = Product::query()
            ->active()
            ->with(['category', 'fitments.brand', 'fitments.model', 'aliases']);

        if ($term === '') {
            return $query->orderByDesc('stock_qty')->limit($limit)->get();
        }

        $tokens = array_values(array_filter(preg_split('/\s+/', $term) ?: []));
        $year = null;
        foreach ($tokens as $token) {
            if (preg_match('/^(19|20)\d{2}$/', $token)) {
                $year = (int) $token;
            }
        }

        $like = '%'.$term.'%';

        $query->where(function ($q) use ($tokens, $like, $year) {
            $q->where('primary_part_number', 'like', $like)
                ->orWhere('barcode', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('brand', 'like', $like)
                ->orWhereHas('aliases', fn ($a) => $a->where('reference_number', 'like', $like))
                ->orWhereHas('fitments', fn ($f) => $f->where('mapped_part_number', 'like', $like));

            // All search tokens must appear in the name (e.g. "brake pad").
            $q->orWhere(function ($nameQuery) use ($tokens) {
                foreach ($tokens as $token) {
                    $nameQuery->where('name', 'like', '%'.$token.'%');
                }
            });

            $fitmentTokens = array_values(array_filter(
                $tokens,
                fn ($token) => ! preg_match('/^(19|20)\d{2}$/', $token)
            ));

            if ($fitmentTokens || $year) {
                $q->orWhereHas('fitments', function ($fitmentQuery) use ($fitmentTokens, $year) {
                    foreach ($fitmentTokens as $token) {
                        $tokenLike = '%'.$token.'%';
                        $fitmentQuery->where(function ($vehicleQuery) use ($tokenLike) {
                            $vehicleQuery->whereHas('model', fn ($m) => $m->where('name', 'like', $tokenLike))
                                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $tokenLike));
                        });
                    }
                    if ($year) {
                        $fitmentQuery->where('year_from', '<=', $year)->where('year_to', '>=', $year);
                    }
                });
            }
        });

        $query->orderByRaw(
            'CASE
                WHEN primary_part_number = ? THEN 0
                WHEN barcode = ? THEN 1
                WHEN primary_part_number LIKE ? THEN 2
                WHEN name LIKE ? THEN 3
                ELSE 4
            END',
            [$term, $term, $term.'%', $term.'%']
        )->orderByDesc('stock_qty');

        return $query->limit($limit)->get();
    }
}
