<?php

namespace Services;

use Models\Shift;

class ShiftService
{
    /**
     * Get all shifts
     */
    public function getAll(): array
    {
        return Shift::getAll();
    }

    /**
     * Get shift by ID
     */
    public function getById(int $id): ?array
    {
        return Shift::find($id);
    }

    /**
     * Create a new shift
     */
    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check duplicate code
        if (Shift::findByCode($data['shift_code'])) {
            return ['success' => false, 'errors' => ['Shift code already exists.']];
        }

        Shift::create($data);
        return ['success' => true];
    }

    /**
     * Update an existing shift
     */
    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $existing = Shift::findByCode($data['shift_code']);
        if ($existing && $existing['id'] !== $id) {
            return ['success' => false, 'errors' => ['Shift code already in use by another shift.']];
        }

        Shift::update($id, $data);
        return ['success' => true];
    }

    /**
     * Delete a shift
     */
    public function delete(int $id): bool
    {
        return Shift::delete($id);
    }

    /**
     * Toggle shift active/inactive
     */
    public function toggleActive(string $shiftCode, bool $active = true): array
    {
        try {
            Shift::toggleActive($shiftCode, $active);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add the default shifts that are still missing from the database.
     */
    public function restoreDefaults(): int
    {
        return \Config\Database::seedDefaultShifts(true);
    }

    /**
     * Get all shift codes for dropdowns
     */
    public function getAllCodes(): array
    {
        return Shift::getAllCodes();
    }

    /**
     * Get female-restricted shift codes
     */
    public function getFemaleRestrictedCodes(): array
    {
        return Shift::getFemaleRestrictedCodes();
    }

    /**
     * Get inactive shift codes
     */
    public function getInactiveCodes(): array
    {
        return Shift::getInactiveCodes();
    }

    /**
     * Get shifts by category
     */
    public function getByCategory(string $category): array
    {
        return Shift::getByCategory($category);
    }
}
