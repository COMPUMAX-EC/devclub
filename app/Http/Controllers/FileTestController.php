<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\UploadedFileService;
use Illuminate\Http\Request;

class FileTestController extends Controller
{
    public function __construct(
        protected UploadedFileService $files
    ) {
    }

    /**
     * Muestra formulario de subida y lista de archivos.
     */
    public function index()
    {
        // Puedes paginar si quieres, para pruebas lo dejo simple
        $files = File::orderByDesc('id')->get();

        return view('file_test.index', compact('files'));
    }

    /**
     * Sube un archivo nuevo usando UploadedFileService::store().
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file'],
            // opcionalmente podrías validar 'path_key'
        ]);

        // Clave de ruta en config/uploads.php
        // Por defecto usamos 'plans.terms' como ejemplo
        $pathKey = $request->input('path_key', 'plans.terms');

        $stored = $this->files->store(
            $request->file('file'),
            $pathKey,
            [
                'context' => 'manual_test_upload',
            ]
        );

        return redirect()
            ->route('test.files.index')
            ->with('status', "Archivo subido correctamente. ID={$stored->id}, UUID={$stored->uuid}");
    }

    /**
     * Reemplaza el archivo físico manteniendo el mismo registro (id y uuid).
     */
    public function replace(Request $request, File $file)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $updated = $this->files->replace(
            $file,
            $request->file('file'),
            [
                'context' => 'manual_test_replace',
            ]
        );

        return redirect()
            ->route('test.files.index')
            ->with(
                'status',
                "Archivo ID={$updated->id} reemplazado. Nuevo nombre: {$updated->original_name}"
            );
    }

    /**
     * Elimina el archivo físico y el registro de la tabla files.
     */
    public function destroy(File $file)
    {
        $id = $file->id;

        $this->files->delete($file);

        return redirect()
            ->route('test.files.index')
            ->with('status', "Archivo ID={$id} eliminado.");
    }
}
