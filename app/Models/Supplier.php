<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'contact_person', 'phone', 'email', 'address'])]
class Supplier extends Model
{
    public function medicines(): HasMany
    {
        return $this->hasMany(Medicine::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
