<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MonitorController extends Controller
{
    public function index()
    {
        $logPath = storage_path('logs/laravel.log');

        $lastLines = [];
        if (File::exists($logPath)) {
            // leer últimas ~200 líneas sin reventar memoria
            $content = File::get($logPath);
            $lines = preg_split("/\r\n|\n|\r/", $content);
            $lastLines = array_slice($lines, -200);
        }

        $dbOk = true;
        $dbError = null;
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }

        return view('monitor.index', [
            'dbOk' => $dbOk,
            'dbError' => $dbError,
            'lastLines' => $lastLines,
            'logPath' => $logPath,
        ]);
    }
}
