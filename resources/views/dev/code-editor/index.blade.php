@extends('layouts.craft')

@section('content')
	<div class="container py-4">
		<div class="d-flex align-items-center justify-content-between mb-3">
			<h1 class="h4 mb-0">Playground: CodeEditor</h1>
		</div>

		<div class="card">
			<div class="card-body">
				<p class="text-muted mb-3">
					Selecciona un tipo para abrir el editor en una nueva página.
				</p>

				<div class="list-group">
					@foreach($languages as $key => $label)
						<a
							class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
							href="{{ route('admin.tools.code-editor.edit', ['language' => $key]) }}"
							rel="noopener"
						>
							<span>{{ $label }}</span>
							<span class="badge bg-secondary">{{ $key }}</span>
						</a>
					@endforeach
				</div>
			</div>
		</div>
	</div>
@endsection
