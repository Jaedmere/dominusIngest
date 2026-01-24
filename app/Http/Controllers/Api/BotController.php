<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BotController extends Controller
{
    public function consultarVentas(Request $request)
    {
        // 1. Verificación de Seguridad
        if ($request->header('x-api-key') !== env('API_COMBURED_KEY')) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado'], 401);
        }

        $reportPath = public_path('reportes');

        try {
            // Asegurar que la carpeta existe
            if (!file_exists($reportPath)) {
                mkdir($reportPath, 0775, true);
            }

            // ======= PROCESAMIENTO DE FECHAS =======
            $periodo = (string) $request->input('period', 'today');
            $inicio = Carbon::today()->startOfDay();
            $fin    = Carbon::today()->endOfDay();

            if ($periodo === 'custom') {
                $inicio = Carbon::parse($request->input('start_date'))->startOfDay();
                $fin    = Carbon::parse($request->input('end_date'))->endOfDay();
            } elseif ($periodo === 'yesterday') {
                $inicio = Carbon::yesterday()->startOfDay();
                $fin    = Carbon::yesterday()->endOfDay();
            } elseif ($periodo === 'current_month') {
                $inicio = Carbon::now()->startOfMonth()->startOfDay();
                $fin    = Carbon::now()->endOfDay();
            }

            // ======= CONSULTA A BASE DE DATOS =======
            $query = DB::table('dominus_sales')
                ->whereBetween('fecha_factura', [$inicio, $fin]);

            // Filtro por EDS (Estación)
            $edsId = $request->input('eds_id', 'all');
            if ($edsId !== 'all' && !empty($edsId)) {
                $query->where('id_eds', $edsId);
            }

            // Filtro por Productos
            $prodMode = $request->input('products.mode', 'all');
            $prodRefs = $request->input('products.refs', []);
            if (is_string($prodRefs)) $prodRefs = json_decode($prodRefs, true)['refs'] ?? [];

            if ($prodMode === 'include' && !empty($prodRefs)) $query->whereIn('referencia', $prodRefs);
            elseif ($prodMode === 'exclude' && !empty($prodRefs)) $query->whereNotIn('referencia', $prodRefs);

            $rawData = $query->select(
                'producto as nombre',
                DB::raw("SUM(total) as total_dinero"),
                DB::raw("SUM(cantidad) as total_galones"),
                DB::raw("COUNT(*) as tx")
            )->groupBy('producto')->get();

            if ($rawData->isEmpty()) {
                return response()->json(['status' => 'success', 'mode' => 'text_only', 'message' => "Sin datos para el rango: " . $inicio->format('d/m')]);
            }

            // ======= PREPARAR DATOS PARA EL GRÁFICO =======
            $labels = [];
            $dataVentas = [];
            $dataTx = [];
            $totalDinero = 0;
            $totalGalones = 0;
            $totalTx = 0;

            foreach ($rawData as $row) {
                $labels[] = strtoupper($row->nombre);
                $dataVentas[] = (float)$row->total_dinero;
                $dataTx[] = (int)$row->tx;
                $totalDinero += (float)$row->total_dinero;
                $totalGalones += (float)$row->total_galones;
                $totalTx += (int)$row->tx;
            }

            $fDinero = "$" . number_format($totalDinero, 0, ',', '.');
            $fRango = $inicio->format('d M') . " - " . $fin->format('d M');

            // ======= CONFIGURACIÓN DASHBOARD QUICKCHART =======
            // Creamos un gráfico mixto: Barras para Ventas ($) y Línea para Transacciones (Tx)
            $chartConfig = [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Ventas ($)',
                            'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                            'data' => $dataVentas,
                            'yAxisID' => 'y',
                        ],
                        [
                            'label' => 'Transacciones',
                            'type' => 'line',
                            'fill' => false,
                            'borderColor' => 'rgb(255, 99, 132)',
                            'data' => $dataTx,
                            'yAxisID' => 'y1',
                        ]
                    ]
                ],
                'options' => [
                    'title' => [
                        'display' => true,
                        'text' => "REPORTE COMBU-VENTAS ($fRango)",
                        'fontSize' => 18
                    ],
                    'scales' => [
                        'yAxes' => [
                            ['id' => 'y', 'type' => 'linear', 'position' => 'left', 'ticks' => ['beginAtZero' => true]],
                            ['id' => 'y1', 'type' => 'linear', 'position' => 'right', 'gridLines' => ['display' => false]]
                        ]
                    ]
                ]
            ];

            $chartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartConfig)) . "&w=800&h=450&bkg=white";

            // Descargar y Guardar Imagen
            $img = file_get_contents($chartUrl);
            $fileName = 'report_' . time() . '.png';
            $path = $reportPath . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($path, $img);

            $publicUrl = url('reportes/' . $fileName);
            if (!str_starts_with($publicUrl, 'https://')) $publicUrl = str_replace('http://', 'https://', $publicUrl);

            return response()->json([
                'status' => 'success',
                'mode' => 'image',
                'image_url' => $publicUrl,
                'data' => [
                    'dinero' => $totalDinero,
                    'galones' => $totalGalones,
                    'tx' => $totalTx,
                    'rango' => $fRango
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}