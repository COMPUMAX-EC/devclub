<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pruebas de archivos</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="p-4">
<div class="container">
    <h1 class="mb-4">Pruebas de UploadedFileService</h1>

    {{-- Mensajes de estado --}}
    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    {{-- Errores de validación --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Formulario de subida --}}
    <div class="card mb-4">
        <div class="card-header">
            Subir nuevo archivo
        </div>
        <div class="card-body">
            <form action="{{ route('test.files.store') }}" method="post" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Archivo</label>
                    <input type="file" name="file" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Clave de ruta (config/uploads.php)
                        <small class="text-muted">(ej: plans.terms)</small>
                    </label>
                    <input
                        type="text"
                        name="path_key"
                        class="form-control"
                        value="{{ old('path_key', 'plans.terms') }}"
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    Subir archivo
                </button>
            </form>
        </div>
    </div>

    {{-- Listado de archivos --}}
    <div class="card">
        <div class="card-header">
            Archivos almacenados (tabla files)
        </div>
        <div class="card-body p-0">
            @if ($files->isEmpty())
                <p class="p-3 mb-0 text-muted">
                    No hay archivos registrados todavía.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>UUID</th>
                            <th>Nombre original</th>
                            <th>Disk</th>
                            <th>Path</th>
                            <th>Tamaño (bytes)</th>
                            <th>Pruebas de rutas</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($files as $file)
                            @php
                                // 1) URL normal con UUID
                                $uuidUrl = $file->url();

                                // 2) URL temporal (ID + firma, por defecto 1 minuto o lo que diga config/uploads)
                                $tempUrl = $file->temporaryUrl();

                                // 3) Ruta local absoluta
                                try {
                                    $localPath = $file->localPath();
                                } catch (\Throwable $e) {
                                    $localPath = 'Error al obtener ruta local: ' . $e->getMessage();
                                }
                            @endphp

                            <tr>
                                <td>{{ $file->id }}</td>
                                <td class="text-monospace small">{{ $file->uuid }}</td>
                                <td>{{ $file->original_name }}</td>
                                <td>{{ $file->disk }}</td>
                                <td class="small">{{ $file->path }}</td>
                                <td>{{ $file->size }}</td>

                                {{-- Columna: Pruebas de rutas --}}
                                <td style="min-width: 260px;">
                                    {{-- URL normal con UUID --}}
                                    <div class="mb-2">
                                        <div class="small fw-semibold">URL (UUID, sin expiración):</div>
                                        @if ($uuidUrl)
                                            <a
                                                href="{{ $uuidUrl }}"
                                                target="_blank"
                                                class="small d-block text-break"
                                            >
                                                {{ $uuidUrl }}
                                            </a>
                                        @else
                                            <span class="badge bg-secondary">
                                                Ruta files.show no definida
                                            </span>
                                        @endif
                                    </div>

                                    {{-- URL temporal con ID --}}
                                    <div class="mb-2">
                                        <div class="small fw-semibold">URL temporal (ID, {{ config('uploads.temporary_url_minutes', 1) }} min):</div>
                                        @if ($tempUrl)
                                            <a
                                                href="{{ $tempUrl }}"
                                                target="_blank"
                                                class="small d-block text-break"
                                            >
                                                {{ $tempUrl }}
                                            </a>
                                        @else
                                            <span class="badge bg-secondary">
                                                Ruta files.temp no definida
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Ruta local absoluta --}}
                                    <div class="mb-0">
                                        <div class="small fw-semibold">Ruta local absoluta:</div>
                                        <code class="small text-break d-block">
                                            {{ $localPath }}
                                        </code>
                                    </div>
                                </td>

                                {{-- Columna: Acciones --}}
                                <td style="min-width: 260px;">
                                    {{-- Descargar rápido con URL UUID (si existe) --}}
                                    @if ($uuidUrl)
                                        <a
                                            href="{{ $uuidUrl }}"
                                            class="btn btn-sm btn-outline-secondary mb-1"
                                            target="_blank"
                                        >
                                            Descargar (UUID)
                                        </a>
                                    @endif

                                    {{-- Reemplazar archivo --}}
                                    <form
                                        action="{{ route('test.files.replace', $file) }}"
                                        method="post"
                                        enctype="multipart/form-data"
                                        class="d-inline-block mb-1"
                                    >
                                        @csrf
                                        <div class="input-group input-group-sm">
                                            <input
                                                type="file"
                                                name="file"
                                                class="form-control form-control-sm"
                                                required
                                            >
                                            <button class="btn btn-outline-warning" type="submit">
                                                Reemplazar
                                            </button>
                                        </div>
                                    </form>

                                    {{-- Eliminar archivo --}}
                                    <form
                                        action="{{ route('test.files.destroy', $file) }}"
                                        method="post"
                                        class="d-inline-block mb-1"
                                        onsubmit="return confirm('¿Eliminar archivo ID={{ $file->id }}?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
