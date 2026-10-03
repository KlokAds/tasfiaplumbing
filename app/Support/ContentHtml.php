<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Cache;

/**
 * Prepares stored rich text for the public site without changing what is saved:
 *  - drops inline styles, classes and fonts pasted from Word/ChatGPT (they break dark
 *    mode and the site typography); only text alignment and the editor's photo
 *    position and width are kept, rebuilt from fixed values,
 *  - removes scripts, event handlers and javascript: links,
 *  - turns runs of image-only paragraphs into a gallery grid,
 *  - lazy-loads images and fills a missing alt text,
 *  - opens external links in a new tab with rel="noopener".
 */
class ContentHtml
{
    /** Bump when the output changes, so cached pages are rebuilt. */
    private const VERSION = 6;

    private const DROP_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'meta', 'link'];
    private const KEEP_ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'title'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'ol' => ['start'],
        '*' => ['id'],
    ];

    public static function render(?string $html, string $altFallback = ''): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        return Cache::remember('content_html.' . self::VERSION . '.' . md5($html . '|' . $altFallback), now()->addDay(), fn () => self::process($html, $altFallback));
    }

    private static function process(string $html, string $altFallback): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $root = $doc->getElementById('__root');
        if (!$root) {
            return strip_tags($html, '<p><a><strong><em><ul><ol><li><h2><h3><h4><h5><h6><img><br>');
        }

        foreach (self::DROP_TAGS as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Unwrap presentational wrappers (<span>, <font>) but keep their text.
        foreach (['span', 'font'] as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $node) {
                self::unwrap($node);
            }
        }

        // Attribute whitelist.
        foreach ($xp->query('.//*', $root) as $el) {
            /** @var DOMElement $el */
            // Text alignment chosen in the editor survives as a fixed class, never as free CSS.
            $align = in_array(strtolower($el->tagName), ['p', 'h2', 'h3', 'h4', 'h5', 'h6'], true)
                && preg_match('/(?:^|;)\s*text-align\s*:\s*(center|right|justify)\s*(?:;|$)/i', $el->getAttribute('style'), $m)
                ? strtolower($m[1]) : null;
            $imgAlign = strtolower($el->tagName) === 'img' && in_array($el->getAttribute('data-align'), ['left', 'center', 'right'], true)
                ? $el->getAttribute('data-align') : null;
            $allowed = array_merge(self::KEEP_ATTRS['*'], self::KEEP_ATTRS[strtolower($el->tagName)] ?? []);
            foreach (iterator_to_array($el->attributes) as $attr) {
                if (!in_array(strtolower($attr->name), $allowed, true)) {
                    $el->removeAttribute($attr->name);
                }
            }
            if ($align) {
                $el->setAttribute('class', 'ta-' . $align);
            }
            if ($imgAlign && $imgAlign !== 'center') {
                $el->setAttribute('data-align', $imgAlign);
            }
            if ($el->tagName === 'a') {
                $href = trim($el->getAttribute('href'));
                if (preg_match('/^\s*(javascript|data|vbscript):/i', $href)) {
                    $el->removeAttribute('href');
                } elseif (preg_match('#^https?://#i', $href) && !str_contains($href, parse_url(config('app.url'), PHP_URL_HOST) ?: '#')) {
                    $el->setAttribute('target', '_blank');
                    $el->setAttribute('rel', 'noopener');
                }
            }
            if ($el->tagName === 'img') {
                if (preg_match('/^\s*(javascript|data:text)/i', $el->getAttribute('src'))) {
                    $el->parentNode?->removeChild($el);
                    continue;
                }
                $el->setAttribute('loading', 'lazy');
                $el->setAttribute('decoding', 'async');
                // A photo resized in the editor keeps its width. The style is built here from a
                // number only, so nothing from the stored HTML reaches the style attribute.
                $raw = trim($el->getAttribute('width'));
                $width = ctype_digit($raw) ? (int) $raw : 0;
                $el->removeAttribute('height');
                if ($width >= 40 && $width <= 2000) {
                    $el->setAttribute('width', (string) $width);
                    $el->setAttribute('style', "width:{$width}px");
                } else {
                    $el->removeAttribute('width');
                }
                if (trim($el->getAttribute('alt')) === '' && $altFallback !== '') {
                    $el->setAttribute('alt', $altFallback);
                }
            }
        }

        // Empty paragraphs left behind by pasted content.
        foreach (iterator_to_array($root->getElementsByTagName('p')) as $p) {
            if (trim($p->textContent) === '' && !$p->getElementsByTagName('img')->length) {
                $p->parentNode?->removeChild($p);
            }
        }

        self::fixHeadings($doc, $root);
        self::buildGalleries($doc, $root);
        self::wrapJobNotes($doc, $root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /**
     * The page title is the only H1, and levels never skip (H2 → H4 becomes H2 → H3),
     * which is what screen readers and search engines expect.
     */
    private static function fixHeadings(DOMDocument $doc, DOMElement $root): void
    {
        $xp = new DOMXPath($doc);
        $previous = 1;
        foreach (iterator_to_array($xp->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $root)) as $h) {
            $level = (int) substr($h->tagName, 1);
            $wanted = max(2, min($level, $previous + 1));
            if ($wanted !== $level) {
                $new = $doc->createElement('h' . $wanted);
                foreach (iterator_to_array($h->attributes) as $attr) {
                    $new->setAttribute($attr->name, $attr->value);
                }
                while ($h->firstChild) {
                    $new->appendChild($h->firstChild);
                }
                $h->parentNode->replaceChild($new, $h);
            }
            $previous = $wanted;
        }
    }

    /** Two or more image-only blocks in a row become one responsive grid. */
    /**
     * "What we see on real jobs" (the first-hand experience section added from the editor) and
     * everything up to the next H2 go into <section class="job-notes">, shown as a card.
     */
    private static function wrapJobNotes(DOMDocument $doc, DOMElement $root): void
    {
        foreach (iterator_to_array($root->childNodes) as $node) {
            if (!($node instanceof DOMElement) || strtolower($node->tagName) !== 'h2' || !preg_match('/what we see on real jobs/i', $node->textContent)) {
                continue;
            }
            $section = $doc->createElement('section');
            $section->setAttribute('class', 'job-notes');
            $root->insertBefore($section, $node);
            $next = $node;
            do {
                $after = $next->nextSibling;
                $section->appendChild($next);
                $next = $after;
            } while ($next && !($next instanceof DOMElement && strtolower($next->tagName) === 'h2'));

            return;
        }
    }

    private static function buildGalleries(DOMDocument $doc, DOMElement $root): void
    {
        $run = [];
        $flush = function () use (&$run, $doc) {
            $images = array_merge(...array_map(fn ($n) => self::imagesOf($n), $run));
            if (count($images) >= 2) {
                $grid = $doc->createElement('div');
                $grid->setAttribute('class', 'content-gallery');
                $grid->setAttribute('data-count', (string) min(count($images), 4));
                $run[0]->parentNode->insertBefore($grid, $run[0]);
                // Show at most 9 tiles; the 9th says "+N" and the rest open in the photo viewer.
                foreach ($images as $i => $img) {
                    $fig = $doc->createElement('figure');
                    if (count($images) > 9 && $i === 8) {
                        $fig->setAttribute('data-more', '+' . (count($images) - 8));
                    } elseif ($i > 8) {
                        $fig->setAttribute('hidden', 'hidden');
                    }
                    // Gallery tiles are sized and placed by the grid, not by the editor.
                    $img->removeAttribute('style');
                    $img->removeAttribute('width');
                    $img->removeAttribute('data-align');
                    $fig->appendChild($img);
                    $grid->appendChild($fig);
                }
                foreach ($run as $n) {
                    $n->parentNode?->removeChild($n);
                }
            } elseif (count($images) === 1) {
                // A single photo: keep it, but mark it so it is shown uncropped.
                $images[0]->setAttribute('class', 'content-single');
            }
            $run = [];
        };

        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof DOMElement && self::isImageOnly($node)) {
                $run[] = $node;
            } elseif ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
                continue;
            } else {
                $flush();
            }
        }
        $flush();
    }

    private static function isImageOnly(DOMElement $el): bool
    {
        if (strtolower($el->tagName) === 'img') {
            return true;
        }
        if (!in_array(strtolower($el->tagName), ['p', 'div', 'figure'], true)) {
            return false;
        }

        return $el->getElementsByTagName('img')->length > 0 && trim(preg_replace('/\s+/u', '', $el->textContent)) === '';
    }

    /** @return DOMElement[] */
    private static function imagesOf(DOMNode $node): array
    {
        if ($node instanceof DOMElement && strtolower($node->tagName) === 'img') {
            return [$node];
        }

        return $node instanceof DOMElement ? iterator_to_array($node->getElementsByTagName('img')) : [];
    }

    private static function unwrap(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }
}
