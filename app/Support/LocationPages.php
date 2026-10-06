<?php

namespace App\Support;

use App\Models\Location;
use App\Models\ServiceDetail;
use Illuminate\Support\Str;

/**
 * Builds the area pages (Locations) from App\Support\SingaporeTowns and this site's trade
 * (config admin.location_pages). Adds the towns that are missing and fills pages that have
 * (almost) no text yet. A page someone has written is never changed; nothing is deleted.
 */
class LocationPages
{
    /** Below this many words a page counts as empty and is filled. */
    private const EMPTY_WORDS = 30;

    /** Misspelt names already saved, matched to the town. */
    private const ALIASES = ['tampanies' => 'Tampines', 'payar lebar' => 'Paya Lebar', 'newton town' => 'Newton'];

    /** @return array{created: int, filled: int, kept: int, services: int, faqs: int} */
    public static function sync(bool $dryRun = false): array
    {
        $trade = (array) config('admin.location_pages');
        if (!$trade) {
            throw new \RuntimeException('config admin.location_pages is not set for this site.');
        }
        $services = ServiceDetail::where('is_active', true)->orderBy('order')->take(8)->pluck('id');
        $out = ['created' => 0, 'filled' => 0, 'kept' => 0, 'services' => 0, 'faqs' => 0];

        foreach (SingaporeTowns::all() as $i => $town) {
            $location = self::find($town['name']);
            $isNew = !$location;
            $empty = $isNew || str_word_count(strip_tags((string) $location->description)) < self::EMPTY_WORDS;
            $out[$isNew ? 'created' : ($empty ? 'filled' : 'kept')]++;
            if ($dryRun) {
                continue;
            }

            $location ??= new Location(['is_active' => true, 'sort_order' => $i]);
            if ($empty) {
                $location->fill([
                    'name' => $town['name'],
                    'slug' => Str::slug($town['name']),
                    'region' => $town['region'],
                    'intro' => self::intro($town, $trade),
                    'description' => self::description($town, $trade),
                    'property_types' => $town['types'],
                    'nearby_areas' => $town['nearby'],
                    'latitude' => $location->latitude ?: $town['lat'],
                    'longitude' => $location->longitude ?: $town['lng'],
                    'meta_title' => self::metaTitle($town, $trade),
                    'meta_desc' => self::metaDesc($town, $trade),
                ]);
                $location->save();
            }

            if ($location->services()->count() === 0 && $services->isNotEmpty()) {
                $location->services()->syncWithoutDetaching($services->mapWithKeys(fn ($id) => [$id => ['is_published' => true]])->all());
                $out['services']++;
            }
            if ($location->faqs()->count() === 0) {
                $location->syncFaqs(self::faqs($town, $trade));
                $out['faqs']++;
            }
        }

        return $out;
    }

    private static function find(string $name): ?Location
    {
        $want = Str::lower($name);
        foreach (Location::all() as $l) {
            $saved = Str::lower(trim($l->name));
            if ($saved === $want || $l->slug === Str::slug($name) || (self::ALIASES[$saved] ?? null) === $name) {
                return $l;
            }
        }

        return null;
    }

    public static function intro(array $town, array $trade): string
    {
        $where = $town['estates'] ? ' in ' . implode(', ', array_slice($town['estates'], 0, 3)) . ' and the rest of ' . $town['name'] : ' across ' . $town['name'];

        return "{$trade['title']} in {$town['name']}: {$trade['short']}{$where}. Send us photos of the job and we reply with a price.";
    }

    public static function description(array $town, array $trade): string
    {
        $e = fn ($s) => e($s);
        $work = collect(['HDB' => 'hdb', 'Condominium' => 'condo', 'Landed' => 'landed', 'Commercial' => 'commercial', 'Industrial' => 'commercial'])
            ->filter(fn ($key, $type) => in_array($type, $town['types'], true))->values()->unique()
            ->map(fn ($key) => '<p>' . $e($trade['work'][$key]) . '</p>')->implode("\n");
        $estates = self::listing($town['estates']);
        $nearby = self::listing($town['nearby']);

        return implode("\n", array_filter([
            '<h2>' . $e("About {$town['name']}") . '</h2>',
            '<p>' . $e($town['about']) . ' ' . $e($town['homes']) . '</p>',
            '<h2>' . $e("What we do in {$town['name']}") . '</h2>',
            $work,
            '<h2>' . $e("Areas we cover in and around {$town['name']}") . '</h2>',
            '<p>' . $e("We cover all of {$town['name']}" . ($estates ? ", including {$estates}" : '') . ($nearby ? ", and the nearby areas of {$nearby}." : '.')) . '</p>',
            '<h2>How to get a quote</h2>',
            '<p>' . $e($trade['quote']) . '</p>',
        ]));
    }

    public static function metaTitle(array $town, array $trade): string
    {
        $title = "{$trade['title']} in {$town['name']} | {$trade['brand']}";

        return mb_strlen($title) <= 60 ? $title : "{$trade['title']} in {$town['name']}, Singapore";
    }

    public static function metaDesc(array $town, array $trade): string
    {
        $types = Str::lower(self::listing(array_map(fn ($t) => $t === 'HDB' ? 'HDB flats' : ($t === 'Condominium' ? 'condos' : ($t === 'Landed' ? 'landed homes' : ($t === 'Commercial' ? 'shops and offices' : 'factories'))), $town['types'])));
        $types = str_replace('hdb', 'HDB', $types);
        $desc = "{$trade['title']} in {$town['name']}, Singapore: {$trade['short']} for {$types}. Covering " . self::listing(array_slice($town['estates'], 0, 3)) . '. Send photos for a price.';

        return mb_strlen($desc) <= 160 ? $desc : rtrim(mb_substr($desc, 0, 157), ' ,.') . '…';
    }

    /** @return list<array{question: string, answer: string}> */
    public static function faqs(array $town, array $trade): array
    {
        $estates = self::listing($town['estates']);
        $nearby = self::listing($town['nearby']);
        $faqs = [[
            'question' => "Do you cover all of {$town['name']}?",
            'answer' => "Yes. We work across {$town['name']}" . ($estates ? ", including {$estates}" : '') . ($nearby ? ", and in nearby {$nearby}." : '.'),
        ]];
        $type = collect(['HDB' => 'hdb', 'Condominium' => 'condo', 'Landed' => 'landed', 'Commercial' => 'commercial', 'Industrial' => 'commercial'])
            ->first(fn ($key, $t) => in_array($t, $town['types'], true));
        $place = ['hdb' => 'HDB flats', 'condo' => 'condominiums', 'landed' => 'landed houses', 'commercial' => 'shops, offices and factories'][$type ?? 'hdb'];
        $faqs[] = [
            'question' => "Do you work in {$place} in {$town['name']}?",
            'answer' => $trade['work'][$type ?? 'hdb'],
        ];
        $faqs[] = [
            'question' => "How do I get a price for a job in {$town['name']}?",
            'answer' => $trade['quote'],
        ];

        return $faqs;
    }

    private static function listing(array $items): string
    {
        $items = array_values(array_filter($items));

        return count($items) > 1 ? implode(', ', array_slice($items, 0, -1)) . ' and ' . end($items) : ($items[0] ?? '');
    }
}
