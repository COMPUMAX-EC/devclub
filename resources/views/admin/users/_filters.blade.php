@php
    $filters = $filters ?? [];
    $q = $filters['q'] ?? request('q');
    $role = $filters['role'] ?? request('role');
    $status = $filters['status'] ?? request('status');
    $perPage = $filters['per_page'] ?? request('per_page', 15);
@endphp

<form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end mb-6">
    <div class="col-md-4">
        <label class="form-label">Buscar</label>
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, Apellido o Email">
    </div>
    <div class="col-md-3">
        <label class="form-label">Rol</label>
        <select class="form-select" name="role">
            <option value="">— Todos —</option>
            @foreach($availableRoles as $r)
                <option value="{{ $r }}" @selected($role === $r)>{{ $r }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Estado</label>
        <select class="form-select" name="status">
            <option value="">— Todos —</option>
            @foreach($statuses as $k=>$v)
                <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Por página</label>
        <select class="form-select" name="per_page">
            @foreach([15,25,50,100] as $n)
            <option value="{{ $n }}" @selected($perPage == $n)>{{ $n }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">Filtrar</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-light">Limpiar</a>
    </div>
</form>
