<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactContent;
use App\Models\SiteSetting;
use App\Support\SiteSchema;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The settings in config/seo.php, split over two screens:
 *  - business: identity and opening hours (Business settings → Identity & hours)
 *  - seo:      search appearance and crawlers (SEO → Schema & robots)
 */
class SeoSettingController extends Controller
{
    private const SCOPES = [
        'business' => ['groups' => ['business', 'hours'], 'title' => 'Identity & hours', 'action' => '/admin/settings/business'],
        'seo' => ['groups' => ['search', 'crawlers'], 'title' => 'Schema & robots', 'action' => '/admin/seo/settings'],
    ];

    public function index(string $scope = 'seo')
    {
        $contact = ContactContent::first();

        return Inertia::render('Admin/Seo/Settings', [
            'scope' => $scope,
            'title' => self::SCOPES[$scope]['title'],
            'action' => self::SCOPES[$scope]['action'],
            'groups' => self::groups($scope),
            'values' => SiteSetting::values(),
            'nap' => [
                'phone' => $contact?->phone,
                'email' => $contact?->email,
                'address' => $contact?->address,
            ],
            'preview' => [
                'schema' => json_encode(SiteSchema::graph(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'robots' => SiteSchema::robots(),
            ],
            'environment' => app()->environment(),
        ]);
    }

    public function business()
    {
        return $this->index('business');
    }

    public function update(Request $request, string $scope = 'seo')
    {
        $rules = [];
        foreach (self::groups($scope) as $group) {
            foreach ($group['fields'] as $key => $field) {
                $rules[str_replace('.', '__', $key)] = match ($field['type']) {
                    'toggle' => 'nullable|boolean',
                    'select' => 'nullable|in:' . implode(',', $field['options']),
                    'textarea' => 'nullable|string|max:5000',
                    default => 'nullable|string|max:500',
                };
            }
        }

        // Dots in keys would be read as nested arrays by the validator, so the form sends "__".
        $validated = $request->validate($rules);
        $values = [];
        foreach ($validated as $formKey => $value) {
            $values[str_replace('__', '.', $formKey)] = is_bool($value) ? ($value ? '1' : '0') : $value;
        }
        SiteSetting::putMany($values);

        return redirect()->back()->with('success', 'Settings saved. Schema and robots.txt are updated.');
    }

    public function updateBusiness(Request $request)
    {
        return $this->update($request, 'business');
    }

    private static function groups(string $scope): array
    {
        return array_intersect_key(config('seo.settings'), array_flip(self::SCOPES[$scope]['groups']));
    }
}
