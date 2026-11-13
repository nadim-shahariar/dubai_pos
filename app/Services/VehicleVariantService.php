<?php

namespace App\Services;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class VehicleVariantService
{
    /**
     * Normalise and validate variant rows. Empty rows are ignored.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function normalise(array $rows): array
    {
        $normalised = [];

        foreach (array_values($rows) as $index => $row) {
            $brand = trim((string) ($row['vehicle_brand'] ?? ''));
            $model = trim((string) ($row['vehicle_model'] ?? ''));

            if ($brand === '' && $model === '') {
                continue;
            }

            if ($brand === '' || $model === '') {
                throw ValidationException::withMessages([
                    "variants.{$index}.vehicle_brand" => 'Enter both a vehicle brand and model for this variant, or leave the row blank.',
                ]);
            }

            $yearFrom = (int) ($row['year_from'] ?? 2000);
            $yearTo = (int) ($row['year_to'] ?? now()->year);

            if ($yearFrom < 1950 || $yearFrom > 2100 || $yearTo < 1950 || $yearTo > 2100 || $yearTo < $yearFrom) {
                throw ValidationException::withMessages([
                    "variants.{$index}.year_from" => 'The model year range is invalid.',
                ]);
            }

            $mapped = trim((string) ($row['mapped_part_number'] ?? ''));

            if ($mapped === '') {
                throw ValidationException::withMessages([
                    "variants.{$index}.mapped_part_number" => 'A part number is required for each vehicle variant.',
                ]);
            }

            $cost = $row['cost_price'] ?? null;
            if ($cost !== null && $cost !== '' && (! is_numeric($cost) || (float) $cost < 0)) {
                throw ValidationException::withMessages([
                    "variants.{$index}.cost_price" => 'The buying price must be zero or more.',
                ]);
            }

            $normalised[] = [
                'vehicle_brand' => $brand,
                'vehicle_model' => $model,
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
                'mapped_part_number' => $mapped,
                'cost_price' => ($cost === null || $cost === '') ? null : round((float) $cost, 2),
            ];
        }

        return $normalised;
    }

    /**
     * Create one fitment per variant row, reusing existing brands/models.
     */
    public function createMany(Product $product, array $rows): int
    {
        $created = 0;

        foreach ($this->normalise($rows) as $row) {
            $brand = CarBrand::findOrCreateByName($row['vehicle_brand']);
            $model = CarModel::findOrCreateForBrand($brand, $row['vehicle_model']);

            $exists = $product->fitments()
                ->where('car_brand_id', $brand->id)
                ->where('car_model_id', $model->id)
                ->where('year_from', $row['year_from'])
                ->where('year_to', $row['year_to'])
                ->exists();

            if ($exists) {
                continue;
            }

            $product->fitments()->create([
                'car_brand_id' => $brand->id,
                'car_model_id' => $model->id,
                'year_from' => $row['year_from'],
                'year_to' => $row['year_to'],
                'mapped_part_number' => $row['mapped_part_number'],
                'cost_price' => $row['cost_price'] ?? null,
            ]);
            $created++;
        }

        return $created;
    }
}
