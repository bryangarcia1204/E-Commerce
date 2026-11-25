<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateClick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AffiliateController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        // FIX: filtro de red normalizado (acepta 'Amazon'/'amazon' y coincide
        // con el slug minúsculo guardado en affiliate_clicks.network).
        $network = $request->input('network');

        $baseQuery = AffiliateClick::query()
            ->betweenDates($from, $to)
            ->forNetwork($network);

        // KPIs
        $totalClicks   = (clone $baseQuery)->count();
        $todayClicks   = AffiliateClick::whereDate('created_at', today())->count();
        $uniqueProducts= (clone $baseQuery)->distinct('product_id')->count('product_id');
        $estimatedComm = (float) (clone $baseQuery)->sum('commission_amount');

        // Serie diaria (rellenamos días sin datos con 0)
        $daily = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $series = $this->fillDateSeries($daily, $from, $to);

        // Top productos
        $topProducts = (clone $baseQuery)
            ->select('product_id', DB::raw('COUNT(*) as total'))
            ->with('product:id,name,image,external_store')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        // Top tiendas (por red del clic, con respaldo en products.external_store)
        $topStores = (clone $baseQuery)
            ->selectRaw("COALESCE(affiliate_clicks.network, products.external_store) as store, COUNT(*) as total")
            ->leftJoin('products', 'products.id', '=', 'affiliate_clicks.product_id')
            ->where(function ($q) {
                $q->whereNotNull('affiliate_clicks.network')->orWhereNotNull('products.external_store');
            })
            ->groupBy('store')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        // Tabla paginada
        $clicks = (clone $baseQuery)
            ->with(['product:id,name,slug,external_store', 'user:id,name,email'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // Opciones de red para el filtro de la vista.
        $networkOptions = app(\App\Services\Affiliate\AffiliateNetworkManager::class)->options();

        return view('admin.affiliate.index', compact(
            'from', 'to', 'network', 'networkOptions',
            'totalClicks', 'todayClicks', 'uniqueProducts', 'estimatedComm',
            'series', 'topProducts', 'topStores', 'clicks'
        ));
    }

    public function reconciliation(Request $request)
    {
        $network = $request->input('network');
        $status  = $request->input('status');
        $from    = $request->input('from', now()->subDays(90)->toDateString());
        $to      = $request->input('to',   now()->toDateString());

        // FIX: el filtro de red se normaliza por slug ('Amazon' → 'amazon',
        // que es como se guarda en affiliate_clicks.network). Antes el
        // filtro capitalizado devolvía siempre 0 resultados.
        $clicks = AffiliateClick::query()
            ->with('product:id,name,external_store')
            ->betweenDates($from, $to)
            ->forNetwork($network)
            ->when($status,  fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $totals = AffiliateClick::query()
            ->betweenDates($from, $to)
            ->forNetwork($network)
            ->selectRaw('status, COUNT(*) as cnt, SUM(commission_amount) as sum')
            ->groupBy('status')
            ->get();

        // Opciones de red para el filtro: slug → nombre legible.
        $networkOptions = app(\App\Services\Affiliate\AffiliateNetworkManager::class)->options();

        return view('admin.affiliate.reconciliation', compact(
            'clicks', 'totals', 'network', 'status', 'from', 'to', 'networkOptions'
        ));
    }

    public function markAsPaid(AffiliateClick $click)
    {
        if ($click->status !== 'converted') {
            return back()->with('error', 'Solo se pueden marcar como pagados los clics convertidos.');
        }

        $click->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Clic marcado como pagado.');
    }

    public function reject(AffiliateClick $click)
    {
        $click->update([
            'status' => 'rejected',
        ]);

        return back()->with('success', 'Clic rechazado.');
    }
    public function export(Request $request): StreamedResponse
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        $filename = "affiliate_clicks_{$from}_{$to}.csv";

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Fecha', 'Producto', 'Tienda', 'Red', 'Estado', 'Usuario', 'IP', 'Referer', 'Comisión']);

            AffiliateClick::with(['product:id,name,external_store', 'user:id,email'])
                ->betweenDates($from, $to)
                ->chunk(500, function ($clicks) use ($out) {
                    foreach ($clicks as $c) {
                        fputcsv($out, [
                            $c->id,
                            $c->created_at?->toDateTimeString(),
                            $c->product?->name,
                            $c->product?->external_store,
                            $c->network,
                            $c->status,
                            $c->user?->email,
                            $c->ip_address,
                            $c->referer,
                            $c->commission_amount,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(int $id)
    {
        AffiliateClick::findOrFail($id)->delete();
        return back()->with('success', 'Clic eliminado.');
    }

    /** Rellena los días sin datos con 0 entre $from y $to */
    private function fillDateSeries($daily, string $from, string $to): array
    {
        $labels = [];
        $data   = [];
        $cursor = \Carbon\Carbon::parse($from);
        $end    = \Carbon\Carbon::parse($to);

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('d/m');
            $data[]   = (int) ($daily[$key] ?? 0);
            $cursor->addDay();
        }

        return ['labels' => $labels, 'data' => $data];
    }
}