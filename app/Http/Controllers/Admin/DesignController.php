<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Licensing\Features;
use App\Models\Setting;
use App\Support\Design\Favicon;
use App\Support\Design\SiteDesign;
use App\Support\Media\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Aussehen der Website: Vorlage, Farben, Schrift, Logo und Effekte, mit Live-Vorschau. */
class DesignController extends Controller
{
    public function index(): View
    {
        return view('admin.design', [
            'design' => SiteDesign::values(),
            'pro' => Features::on('design-pro'),
        ]);
    }

    public function update(Request $request, MediaLibrary $media): RedirectResponse
    {
        $pro = Features::on('design-pro');
        $data = $request->validate([
            'theme' => ['required', Rule::in(array_keys(SiteDesign::THEMES))],
            'brand' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['nullable', Rule::in(array_keys(SiteDesign::FONTS))],
            'heading_font' => ['nullable', Rule::in(array_keys(SiteDesign::FONTS))],
            'radius' => ['required', Rule::in(array_keys(SiteDesign::RADII))],
            'header' => ['required', Rule::in(array_keys(SiteDesign::HEADERS))],
            'footer' => ['required', Rule::in(['light', 'dark'])],
            'animation' => ['nullable', Rule::in(array_keys(SiteDesign::ANIMATIONS))],
            'logo_height' => ['nullable', 'integer', 'between:20,96'],
            'show_name' => ['nullable', 'boolean'],
            'logo_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'favicon_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:4096', 'dimensions:min_width=32,min_height=32'],
            'remove_favicon' => ['nullable', 'boolean'],
        ], ['brand.regex' => 'Bitte wählen Sie eine gültige Farbe.', 'favicon_file.dimensions' => 'Das Favicon braucht mindestens 32 × 32 Pixel, besser 512 × 512.', 'favicon_file.mimes' => 'Bitte laden Sie für das Favicon ein PNG-, JPG-, WebP- oder GIF-Bild hoch.']);

        if (! $pro && empty(SiteDesign::THEMES[$data['theme']]['free'])) {
            return back()->withErrors(['theme' => 'Diese Vorlage gehört zu Design Pro.']);
        }

        $values = [
            'theme' => $data['theme'], 'brand' => strtolower($data['brand']), 'radius' => $data['radius'],
            'header' => $data['header'], 'footer' => $data['footer'],
            'logo_height' => (string) ($data['logo_height'] ?? 36), 'show_name' => $request->boolean('show_name') ? '1' : '0',
        ];
        if ($pro) {
            $values += ['accent' => ! empty($data['accent']) ? strtolower($data['accent']) : null, 'font' => $data['font'] ?? null,
                'heading_font' => $data['heading_font'] ?? null, 'animation' => $data['animation'] ?? 'rise'];
        }
        if ($request->hasFile('logo_file')) {
            $values['logo'] = $media->store($request->file('logo_file'), 'image', $request->user()->id)->url();
        } elseif ($request->boolean('remove_logo')) {
            $values['logo'] = null;
        }

        if ($request->hasFile('favicon_file')) {
            try {
                $old = Favicon::current();
                $values['favicon'] = Favicon::make($request->file('favicon_file'));
                Favicon::remove($old);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['favicon_file' => $e->getMessage()]);
            }
        } elseif ($request->boolean('remove_favicon')) {
            Favicon::remove(Favicon::current());
            $values['favicon'] = null;
        }

        Setting::putMany(collect($values)->mapWithKeys(fn ($v, $k) => ['design_'.$k => $v])->all());

        return back()->with('status', 'Das Design wurde gespeichert und ist jetzt auf Ihrer Website zu sehen.');
    }
}
