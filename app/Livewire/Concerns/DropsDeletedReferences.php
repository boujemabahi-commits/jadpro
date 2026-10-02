<?php

namespace App\Livewire\Concerns;

/**
 * Edit forms load a record's course/group/teacher ids as they are stored. When
 * one of those was deleted since, keeping the id would make the form fail
 * validation ("selected value is invalid") with nothing to pick in the select,
 * so the form starts with the field empty instead.
 */
trait DropsDeletedReferences
{
    /** The id if that row still exists for this center, otherwise null. */
    protected function existingId(string $model, $id): ?int
    {
        return $id && $model::whereKey($id)->exists() ? (int) $id : null;
    }
}
