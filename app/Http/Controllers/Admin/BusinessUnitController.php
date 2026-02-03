<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Services\BusinessUnits\BusinessUnitPermissionResolver;
use App\Support\Breadcrumbs;
use Illuminate\Http\Request;

class BusinessUnitController extends Controller
{
	public function __construct(
		private readonly BusinessUnitPermissionResolver $resolver
		//private readonly UploadedFileService $uploadedFileService
	) {}

	
	public function indexConsolidators(Request $request)
	{
		return view('admin.business_units.consolidators.index');
	}

	public function indexOffices(Request $request)
	{
		return view('admin.business_units.offices.index');
	}

	public function indexFreelancers(Request $request)
	{
		return view('admin.business_units.freelancers.index');
	}

	public function show(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);


		$chain = $unit->loadMissing('parent')->ancestorChain();
		
		foreach ($chain as $c)
		{
			$abilities = $this->resolver->forUnit($user, $c);
			
			$route = null;
			if($abilities->can('can_access'))
			{
				$route = route('admin.business-units.show', $c);
			}
			Breadcrumbs::add($c->name, $route);
		}
		
		// La autorización real la hace el API resolver al cargar la data en Show.vue.
		return view('admin.business_units.show', [
			'unit' => $unit,
		]);
	}
}
