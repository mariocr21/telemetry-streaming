<?php
namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Routing\Controller;

class LogMonitorController extends Controller
{
    public function index()
    {
        $user = User::find(auth()->id());
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized');
        }

        // Carga la vista de Vue/Inertia.
        return Inertia::render('LogMonitor', [
            // Puedes pasar logs iniciales (ej. los últimos 10) al cargar la página
            'initialLogs' => [], 
        ]);
    }
}