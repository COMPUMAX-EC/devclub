<?php

namespace App\Models;

use App\Notifications\Admin\ResetPasswordAdmin;
use App\Notifications\Customer\ResetPasswordCustomer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{

	use Notifiable,
		SoftDeletes,
		HasRoles;

	/**
	 * Spatie usará este guard por defecto al asignar roles/permisos.
	 * Si el usuario es del portal cliente, usará 'customer'; si no, 'admin'.
	 */
	public function getDefaultGuardName(): string
	{
		return $this->realm === 'customer' ? 'customer' : 'admin';
	}

	protected $fillable = [
		'realm', 'email', 'password', 'first_name', 'last_name', 'display_name', 'status', 'locale', 'timezone', 'ui_settings_json'
	];
	protected $hidden	= ['password', 'remember_token'];
	protected $casts	= [
		'email_verified_at'		=> 'datetime',
		'last_login_at'			=> 'datetime',
		'ui_settings_json'		=> 'array',
		'password'				=> 'hashed',
		'force_password_change' => 'bool',
	];

	// Accessor
	protected function fullName(): Attribute
	{
		return Attribute::get(fn() => trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')));
	}

	// Mutator: emails siempre en minúsculas
	protected function email(): Attribute
	{
		return Attribute::set(fn($value) => Str::lower($value));
	}

	// Relaciones 1:1 (PK=FK)
	public function staffProfile(): HasOne
	{
		return $this->hasOne(StaffProfile::class, 'user_id');
	}

	public function customerProfile(): HasOne
	{
		return $this->hasOne(CustomerProfile::class, 'user_id');
	}

	// Notificación de reset por realm
	public function sendPasswordResetNotification($token): void
	{
		if ($this->realm === 'admin')
		{
			$url = route('admin.password.reset', ['token' => $token, 'email' => $this->email]);
			$this->notify(new ResetPasswordAdmin($url, minutes: config('auth.passwords.admin.expire', 30)));
		}
		else
		{
			$url = route('customer.password.reset', ['token' => $token, 'email' => $this->email]);
			$this->notify(new ResetPasswordCustomer($url, minutes: config('auth.passwords.customer.expire', 30)));
		}
	}

	public function forcePasswordChange(): bool
	{
		return (bool) $this->force_password_change;
	}

	public function displayName(): string
	{
		$full = $this->fullName;
		if ($full !== '')
		{
			return $full;
		}

		// 3) Fallback final: email
		return (string) $this->email;
	}

	public function companies(): BelongsToMany
	{
		return $this
						->belongsToMany(Company::class, 'company_user')
						->using(CompanyUser::class)
						->withPivot('basic_functions')
						->withTimestamps();
	}
	
    /**
     * Membresías del usuario en unidades de negocio.
     */
    public function businessUnitMemberships(): HasMany
    {
        return $this->hasMany(
            BusinessUnitMembership::class,
            'user_id'
        );
    }

    /**
     * Unidades de negocio a las que pertenece el usuario.
     */
    public function businessUnits(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessUnit::class,
            'memberships_business_unit',
            'user_id',
            'business_unit_id'
        )->withPivot('role_id')
         ->withTimestamps();
    }

}
