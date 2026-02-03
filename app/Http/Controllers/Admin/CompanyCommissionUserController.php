<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyCommissionUser;
use App\Models\User;
use Illuminate\Http\Request;

class CompanyCommissionUserController extends Controller
{

	/**
	 * Lista de usuarios de comisiones ya asociados a la empresa.
	 */
	public function index(Company $company)
	{
		$rows = CompanyCommissionUser::with('user')
			->where('company_id', $company->id)
			->orderBy('id')
			->get()
			->map(function (CompanyCommissionUser $row)
			{
				$user = $row->user;

				return [
					'id'         => $row->id,
					'user_id'    => $row->user_id,
					'commission' => number_format((float) $row->commission, 2, '.', ''),
					'user'       => $user ? [
						'id'           => $user->id,
						'email'        => $user->email,
						'display_name' => $user->displayName(),
					] : null,
				];
			});

		return response()->json([
			'data' => $rows,
		]);
	}

	/**
	 * Lista paginada de usuarios disponibles para el modal
	 * (marca cuáles ya están adjuntos).
	 */
	public function available(Company $company, Request $request)
	{
		// Solo usuarios activos del sistema
		$query = User::query()
			->where('status', 'active');

		$search = trim((string) $request->input('q', ''));

		if ($search !== '')
		{
			$query->where(function ($q) use ($search)
			{
				$q->where('email', 'like', '%' . $search . '%');

				// Si es numérico, permitir buscar por ID
				if (ctype_digit($search))
				{
					$q->orWhere('id', (int) $search);
				}
			});
		}

		$assigned = CompanyCommissionUser::where('company_id', $company->id)
			->get()
			->keyBy('user_id');

		$perPage   = (int) $request->input('per_page', 20);
		$perPage   = $perPage > 0 ? $perPage : 20;
		$paginator = $query
			->orderBy('first_name')
			->orderBy('last_name')
			->orderBy('email')
			->paginate($perPage)
			->appends($request->query());

		$rows = collect($paginator->items())->map(function (User $user) use ($assigned)
		{
			$pivot = $assigned->get($user->id);

			return [
				'id'                 => $user->id,
				'email'              => $user->email,
				'display_name'       => $user->displayName(),
				'attached'           => $pivot !== null,
				'commission_user_id' => $pivot?->id,
			];
		});

		return response()->json([
			'data' => $rows,
			'meta' => [
				'current_page' => $paginator->currentPage(),
				'last_page'    => $paginator->lastPage(),
				'per_page'     => $paginator->perPage(),
				'total'        => $paginator->total(),
			],
		]);
	}

	/**
	 * Añadir un usuario a la lista de comisiones (sin confirmación desde el modal).
	 */
	public function store(Company $company, Request $request)
	{
		$validated = $request->validate([
			'user_id' => ['required', 'integer', 'exists:users,id'],
		]);

		$userId = (int) $validated['user_id'];

		$already = CompanyCommissionUser::where('company_id', $company->id)
			->where('user_id', $userId)
			->first();

		if ($already)
		{
			return response()->json([
				'message' => 'El usuario ya está asociado como beneficiario de comisiones en esta empresa.',
				'toast'   => [
					'type'    => 'info',
					'message' => 'Este usuario ya está en la lista de comisiones.',
				],
			], 422);
		}

		$pivot             = new CompanyCommissionUser();
		$pivot->company_id = $company->id;
		$pivot->user_id    = $userId;
		$pivot->commission = 0;
		$pivot->save();

		$pivot->load('user');

		$user = $pivot->user;

		$row = [
			'id'         => $pivot->id,
			'user_id'    => $pivot->user_id,
			'commission' => number_format((float) $pivot->commission, 2, '.', ''),
			'user'       => $user ? [
				'id'           => $user->id,
				'email'        => $user->email,
				'display_name' => $user->displayName(),
			] : null,
		];

		return response()->json([
			'data'  => $row,
			'toast' => [
				'type'    => 'success',
				'message' => 'Usuario añadido a la lista de comisiones.',
			],
		]);
	}

	/**
	 * Actualizar la comisión de un usuario (autosave, decimal con 2 dígitos).
	 */
	public function update(Company $company, CompanyCommissionUser $commissionUser, Request $request)
	{
		if ($commissionUser->company_id !== $company->id)
		{
			abort(404);
		}

		$validated = $request->validate([
			'commission' => ['required', 'numeric', 'min:0', 'max:100'],
		]);

		$commissionUser->commission = $validated['commission'];
		$commissionUser->save();

		$commissionUser->refresh()->load('user');

		$user = $commissionUser->user;

		$row = [
			'id'         => $commissionUser->id,
			'user_id'    => $commissionUser->user_id,
			'commission' => number_format((float) $commissionUser->commission, 2, '.', ''),
			'user'       => $user ? [
				'id'           => $user->id,
				'email'        => $user->email,
				'display_name' => $user->displayName(),
			] : null,
		];

		return response()->json([
			'data'  => $row,
			'toast' => [
				'type'    => 'success',
				'message' => 'Comisión actualizada correctamente.',
			],
		]);
	}

	/**
	 * Eliminar un usuario de la lista de comisiones.
	 * Esta operación se dispara SOLO desde la tabla principal y requiere confirmación en el front.
	 */
	public function destroy(Company $company, CompanyCommissionUser $commissionUser)
	{
		if ($commissionUser->company_id !== $company->id)
		{
			abort(404);
		}

		$commissionUser->delete();

		return response()->json([
			'toast' => [
				'type'    => 'success',
				'message' => 'Usuario eliminado de la lista de comisiones.',
			],
		]);
	}
}
