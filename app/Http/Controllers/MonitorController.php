<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MonitorController extends Controller
{
    public function index()
    {
        // 1) LOG DEL CRON (no laravel.log)
        $logPath = env('DOMINUS_INGEST_LOG_PATH', storage_path('logs/dominus_ingest.log'));

        $lastLines = [];
        if (File::exists($logPath)) {
            // simple y suficiente por ahora: últimas 250 líneas
            $content = File::get($logPath);
            $lines = preg_split("/\r\n|\n|\r/", $content);
            $lastLines = array_slice($lines, -250);

            // lo último arriba
            $lastLines = array_values(array_filter(array_reverse($lastLines), fn($l) => trim($l) !== ''));
        }

        // 2) ESTADO BD
        $dbOk = true;
        $dbError = null;
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }

        // 3) ÚLTIMOS RUNS
        $runs = DB::table('dominus_runs')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        // 4) KPIs rápidos (sobre esos 15)
        $totals = [
            'ok' => $runs->where('status', 'ok')->count(),
            'failed' => $runs->where('status', 'failed')->count(),
            'running' => $runs->where('status', 'running')->count(),
            'inserted' => (int)$runs->sum('inserted'),
            'updated'  => (int)$runs->sum('updated'),
            'skipped'  => (int)$runs->sum('skipped'),
        ];

        return view('monitor.index', compact(
            'dbOk', 'dbError',
            'logPath', 'lastLines',
            'runs', 'totals'
        ));
    }
}
