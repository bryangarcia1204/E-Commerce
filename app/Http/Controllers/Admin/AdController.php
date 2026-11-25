<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\AdsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdController extends Controller
{
    public function __construct(private AdsService $ads) {}

    public function index()
    {
        $ads = Ad::query()
            ->orderBy('position')
            ->orderBy('sort_order')
            ->paginate(25)
            ->withQueryString();

        // Totales agregados por posición para el resumen superior.
        $byPosition = Ad::query()
            ->selectRaw('position, COUNT(*) as total, SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->where('is_active', true)
            ->groupBy('position')
            ->get();

        return view('admin.ads.index', compact('ads', 'byPosition'));
    }

    public function create()
    {
        return view('admin.ads.form', ['ad' => new Ad(), 'creating' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $ad = Ad::create($data);

        $this->ads->flush();

        return redirect()
            ->route('admin.ads.index')
            ->with('success', 'Anuncio "'.$ad->name.'" creado.');
    }

    public function edit(Ad $ad)
    {
        return view('admin.ads.form', ['ad' => $ad, 'creating' => false]);
    }

    public function update(Request $request, Ad $ad)
    {
        $data = $this->validated($request, $ad->id);

        $ad->update($data);

        $this->ads->flush();

        return redirect()
            ->route('admin.ads.index')
            ->with('success', 'Anuncio "'.$ad->name.'" actualizado.');
    }

    public function toggle(Ad $ad)
    {
        $ad->update(['is_active' => ! $ad->is_active]);

        $this->ads->flush();

        return back()->with('success', $ad->is_active
            ? 'Anuncio "'.$ad->name.'" activado.'
            : 'Anuncio "'.$ad->name.'" desactivado.');
    }

    public function destroy(Ad $ad)
    {
        $name = $ad->name;
        $ad->delete();

        $this->ads->flush();

        return back()->with('success', 'Anuncio "'.$name.'" eliminado.');
    }

    private function validated(Request $request, ?int $adId = null): array
    {
        $rules = [
            'name'       => ['required', 'string', 'max:120'],
            'provider'   => ['nullable', 'string', 'max:60'],
            'position'   => ['required', 'string', 'max:40'],
            'type'       => ['required', 'in:'.implode(',', array_keys(Ad::TYPES))],
            'image_url'  => ['nullable', 'string', 'max:2048'],
            'target_url' => ['nullable', 'string', 'max:2048'],
            'html'       => ['nullable', 'string', 'max:10000'],
            'starts_at'  => ['nullable', 'date'],
            'ends_at'    => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'between:0,255'],
            'is_active'  => ['nullable', 'boolean'],
        ];

        $data = $request->validate($rules);

        // Coherencia type ↔ contenido.
        if ($data['type'] === Ad::TYPE_HTML && empty($data['html'])) {
            throw ValidationException::withMessages(['html' => 'Los anuncios tipo HTML necesitan el snippet del proveedor.']);
        }
        if ($data['type'] === Ad::TYPE_IMAGE && empty($data['image_url'])) {
            throw ValidationException::withMessages(['image_url' => 'Los anuncios tipo imagen necesitan una URL de imagen.']);
        }

        $data['provider'] = ($data['provider'] ?? null) ?: 'internal';
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        // El target de las imágenes debe ser interno (path) o http(s) externo.
        if (! empty($data['target_url'])
            && ! str_starts_with($data['target_url'], '/')
            && (! filter_var($data['target_url'], FILTER_VALIDATE_URL)
                || ! in_array(parse_url($data['target_url'], PHP_URL_SCHEME), ['http', 'https'], true))) {
            throw ValidationException::withMessages(['target_url' => 'La URL de destino debe ser una ruta interna (/...) o una URL http(s).']);
        }

        return $data;
    }
}
