<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        // Auto-fill company_id on create (when admin is logged in)
        static::creating(function ($model) {
            if (is_null($model->company_id)) {
                $user = Auth::user();
                if ($user && $user->company_id) {
                    $model->company_id = $user->company_id;
                }
            }
        });

        // Global scope for admins; super_admin sees all; skip in console (migrate/seed)
        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->runningInConsole()) return;

            $user = Auth::user();
            if (! $user || $user->role === 'super_admin') return;

            if ($user->company_id) {
                $builder->where($builder->getModel()->getTable().'.company_id', $user->company_id);
            }
        });
    }

    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        $companyId ??= optional(Auth::user())->company_id;
        if ($companyId) {
            $query->where($this->getTable().'.company_id', $companyId);
        }
        return $query;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
