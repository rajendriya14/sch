/**
 * Dashboard Tabunganku — grafik portofolio.
 *
 * Data dikirim dari PHP lewat window.SV_DASHBOARD (lihat savings_dashboard.php).
 * Chart.js dimuat sebelum berkas ini; bila gagal dimuat, grafik dilewati
 * tanpa mengganggu bagian halaman lain.
 */
(function () {
    'use strict';

    var payload = window.SV_DASHBOARD || {};
    if (!window.Chart) return;

    var COLORS = {
        ink: '#212529',
        muted: '#6c757d',
        grid: 'rgba(33, 37, 41, 0.08)',
        value: '#198754',
        cost: '#adb5bd',
        gain: '#20c997',
        loss: '#dc3545',
        flat: '#6c757d'
    };

    Chart.defaults.font.family = "'Segoe UI', system-ui, -apple-system, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = COLORS.muted;

    function rupiah(value) {
        return 'Rp ' + Math.round(Number(value) || 0).toLocaleString('id-ID');
    }

    function rupiahShort(value) {
        var n = Number(value) || 0;
        var abs = Math.abs(n);
        function trim(x) {
            return String(Number(x.toFixed(2))).replace('.', ',');
        }
        if (abs >= 1000000000) return trim(n / 1000000000) + ' M';
        if (abs >= 1000000) return trim(n / 1000000) + ' jt';
        if (abs >= 1000) return trim(n / 1000) + ' rb';
        return String(Math.round(n));
    }

    function axisMoney(axis) {
        return {
            beginAtZero: true,
            grid: { color: COLORS.grid, drawBorder: false },
            border: { display: false },
            ticks: {
                callback: function (value) { return rupiahShort(value); },
                maxTicksLimit: 6
            }
        };
    }

    function tooltip(callback) {
        return {
            backgroundColor: 'rgba(33, 37, 41, 0.94)',
            padding: 10,
            cornerRadius: 6,
            displayColors: true,
            boxPadding: 4,
            callbacks: { label: callback }
        };
    }

    var charts = [];

    function mount(id, config) {
        var canvas = document.getElementById(id);
        if (!canvas) return null;
        var chart = new Chart(canvas, config);
        charts.push(chart);
        return chart;
    }

    /**
     * Penanda arus dana pada grafik tren.
     * Garis putus-putus + label kecil "↓ tarik" / "↑ setor" pada tanggal
     * transaksi, supaya penurunan nilai tidak terlihat seperti kerugian
     * investasi padahal uangnya ditarik.
     */
    var flowMarkerPlugin = {
        id: 'flowMarkers',
        afterDatasetsDraw: function (chart, args, opts) {
            var events = (opts && opts.events) || [];
            if (!events.length || !chart.chartArea) return;

            var ctx = chart.ctx;
            var area = chart.chartArea;
            ctx.save();

            events.forEach(function (event) {
                var x = chart.scales.x.getPixelForValue(event.index);
                if (x === null || x === undefined || isNaN(x)) return;

                var isOut = event.direction === 'out';
                ctx.beginPath();
                ctx.setLineDash([3, 4]);
                ctx.lineWidth = 1;
                ctx.strokeStyle = isOut ? 'rgba(220, 53, 69, 0.45)' : 'rgba(25, 135, 84, 0.45)';
                ctx.moveTo(x, area.top + 12);
                ctx.lineTo(x, area.bottom);
                ctx.stroke();

                ctx.setLineDash([]);
                ctx.font = '9px "Segoe UI", system-ui, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillStyle = isOut ? 'rgba(180, 35, 50, 0.95)' : 'rgba(20, 110, 68, 0.95)';
                ctx.fillText((isOut ? '↓ ' : '↑ ') + rupiahShort(event.amount), x, area.top + 9);
            });

            ctx.restore();
        }
    };

    // ---------- Tren nilai portofolio ----------
    var trend = payload.trend || { labels: [], values: [], costs: [] };
    if (trend.labels.length >= 2) {
        mount('svChartTrend', {
            type: 'line',
            plugins: [flowMarkerPlugin],
            data: {
                labels: trend.labels,
                datasets: [
                    {
                        label: 'Nilai portofolio',
                        data: trend.values,
                        borderColor: COLORS.value,
                        backgroundColor: 'rgba(25, 135, 84, 0.12)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHoverBackgroundColor: COLORS.value,
                        order: 1
                    },
                    {
                        label: 'Modal',
                        data: trend.costs,
                        borderColor: COLORS.cost,
                        borderDash: [5, 4],
                        borderWidth: 1.5,
                        fill: false,
                        tension: 0.3,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                        order: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } },
                    tooltip: tooltip(function (ctx) {
                        return ctx.dataset.label + ': ' + rupiah(ctx.parsed.y);
                    }),
                    flowMarkers: { events: trend.events || [] }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { maxTicksLimit: 10, autoSkip: true }
                    },
                    y: axisMoney()
                }
            }
        });
    }

    // ---------- Alokasi per jenis ----------
    var allocation = payload.allocation || { labels: [], values: [], colors: [] };
    if (allocation.labels.length) {
        mount('svChartAllocation', {
            type: 'doughnut',
            data: {
                labels: allocation.labels,
                datasets: [{
                    data: allocation.values,
                    backgroundColor: allocation.colors,
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: tooltip(function (ctx) {
                        var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0) || 1;
                        var pct = (ctx.parsed / total) * 100;
                        return ctx.label + ': ' + rupiah(ctx.parsed) + ' (' + pct.toFixed(1).replace('.', ',') + '%)';
                    })
                }
            }
        });
    }

    // ---------- Perbandingan nilai per aset ----------
    var assets = payload.assets || { labels: [], values: [], gains: [] };
    if (assets.labels.length) {
        mount('svChartAssets', {
            type: 'bar',
            data: {
                labels: assets.labels,
                datasets: [{
                    label: 'Nilai kini',
                    data: assets.values,
                    backgroundColor: assets.gains.map(function (gain) {
                        if (gain > 0.5) return 'rgba(25, 135, 84, 0.85)';
                        if (gain < -0.5) return 'rgba(220, 53, 69, 0.85)';
                        return 'rgba(108, 117, 125, 0.75)';
                    }),
                    borderRadius: 4,
                    maxBarThickness: 18
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: tooltip(function (ctx) {
                        return rupiah(ctx.parsed.x);
                    })
                },
                scales: {
                    x: axisMoney(),
                    y: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            autoSkip: false,
                            callback: function (value) {
                                var label = this.getLabelForValue(value);
                                return label.length > 22 ? label.slice(0, 21) + '…' : label;
                            }
                        }
                    }
                }
            }
        });
    }

    // ---------- Nilai vs modal per jenis ----------
    var category = payload.category || { labels: [], values: [], costs: [], colors: [] };
    if (category.labels.length) {
        mount('svChartCategory', {
            type: 'bar',
            data: {
                labels: category.labels,
                datasets: [
                    {
                        label: 'Nilai kini',
                        data: category.values,
                        backgroundColor: category.colors,
                        borderRadius: 4,
                        maxBarThickness: 20
                    },
                    {
                        label: 'Modal',
                        data: category.costs,
                        backgroundColor: 'rgba(173, 181, 189, 0.55)',
                        borderRadius: 4,
                        maxBarThickness: 20
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } },
                    tooltip: tooltip(function (ctx) {
                        return ctx.dataset.label + ': ' + rupiah(ctx.parsed.x);
                    })
                },
                scales: {
                    x: axisMoney(),
                    y: { grid: { display: false }, border: { display: false } }
                }
            }
        });
    }

    // ---------- Tren per jenis (bertumpuk) ----------
    var trendCat = payload.categoryTrend || { labels: [], datasets: [] };
    if (trendCat.labels.length >= 2 && trendCat.datasets.length) {
        mount('svChartCategoryTrend', {
            type: 'bar',
            data: {
                labels: trendCat.labels,
                datasets: trendCat.datasets.map(function (ds) {
                    return {
                        label: ds.label,
                        data: ds.values,
                        backgroundColor: ds.color,
                        borderColor: '#fff',
                        borderWidth: 0.5,
                        borderRadius: 2,
                        maxBarThickness: 26
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } },
                    tooltip: tooltip(function (ctx) {
                        return ctx.dataset.label + ': ' + rupiah(ctx.parsed.y);
                    })
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        border: { display: false },
                        ticks: { maxTicksLimit: 10, autoSkip: true }
                    },
                    y: Object.assign(axisMoney(), { stacked: true })
                }
            }
        });
    }

    // Pastikan grafik mengikuti lebar kontainer saat jendela diubah ukuran
    // (mis. sidebar disembunyikan atau rotasi layar ponsel).
    if (charts.length) {
        var resizeTimer = null;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                charts.forEach(function (chart) { chart.resize(); });
            }, 120);
        });
    }
})();
