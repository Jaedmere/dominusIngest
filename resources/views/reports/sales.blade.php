<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Ventas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    
    <style>
        body { background-color: #f1f5f9; font-family: sans-serif; }
        .card { width: 600px; background: white; margin: 0 auto; overflow: hidden; border-radius: 1rem; }
    </style>
</head>
<body class="p-4">

    <div class="card shadow-2xl">
        <div class="bg-blue-900 text-white p-6 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-400 to-blue-600"></div>
            <h1 class="text-2xl font-bold tracking-widest uppercase mb-1">Combu•Ventas</h1>
            <p class="text-blue-200 text-sm font-medium">{{ $periodoTexto }}</p>
        </div>

        <div class="p-6">
            <div class="text-center mb-8">
                <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Venta Total</p>
                <div class="text-5xl font-black text-gray-800 tracking-tight">
                    ${{ number_format($totalDinero, 0, ',', '.') }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-8">
                <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-100">
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($totalGalones, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-400 font-bold uppercase mt-1">Galones</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-100">
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($totalTx, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-400 font-bold uppercase mt-1">Transacciones</p>
                </div>
            </div>

            <div class="mb-4">
                <p class="text-center text-xs font-bold text-gray-400 uppercase mb-4">Distribución por Producto</p>
                <div class="relative h-64 w-full">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="mt-6 text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    ⏱ Operación: {{ $horasOperacion }}
                </span>
            </div>
        </div>

        <div class="bg-gray-100 p-3 text-center border-t border-gray-200">
            <p class="text-[10px] text-gray-400 uppercase font-bold">Generado el {{ date('d/m/Y H:i A') }} • Monitor Combured</p>
        </div>
    </div>

    <script>
        Chart.register(ChartDataLabels);
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                    data: {!! json_encode($chartValues) !!},
                    backgroundColor: ['#1e40af', '#3b82f6', '#93c5fd'],
                    borderRadius: 6,
                    barThickness: 50,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 25 } },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#475569',
                        font: { weight: 'bold', size: 14 },
                        formatter: (val) => {
                            if(val >= 1000000) return '$' + (val/1000000).toFixed(1) + 'M';
                            return '$' + new Intl.NumberFormat('es-CO').format(val);
                        }
                    }
                },
                scales: {
                    y: { display: false, grid: { display: false } },
                    x: { grid: { display: false }, ticks: { font: { weight: 'bold', size: 12 }, color: '#64748b' } }
                },
                animation: false 
            }
        });
    </script>
</body>
</html>