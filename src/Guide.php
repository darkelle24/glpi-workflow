<?php
declare(strict_types=1);
namespace WorkflowBuilder;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/** Read the bundled guides; only their presentation and known local URLs change in GLPI. */
final class Guide
{
    public const PAGES = [
        'user' => ['file' => 'user-guide.md', 'title' => 'Guide utilisateur'],
        'pdf' => ['file' => 'pdf-guide.md', 'title' => 'Tutoriel PDF'],
    ];
    public const EXAMPLES = ['fiche-connexion-automatique', 'fiche-connexion-salarie'];

    public static function page(string $key): array
    {
        return self::PAGES[$key] ?? throw new ValidationException('Guide inconnu.');
    }

    public static function example(string $name): string
    {
        if (!in_array($name, self::EXAMPLES, true)) { throw new ValidationException('Exemple PDF inconnu.'); }
        $path = dirname(__DIR__) . '/examples/' . $name . '.json';
        if (!is_file($path) || filesize($path) > Definition::MAX_BYTES) { throw new \RuntimeException('Bundled example missing or too large'); }
        $json = file_get_contents($path);
        if ($json === false) { throw new \RuntimeException('Bundled example unavailable'); }
        Definition::decode($json);
        return $json;
    }
    public static function requireAccess(): void
    {
        if (!Access::has('view') && !Access::has('template')) {
            throw new ValidationException('Accès au guide Workflow Builder non autorisé.');
        }
    }

    public static function source(string $key = 'user'): string
    {
        $path = dirname(__DIR__) . '/docs/' . self::page($key)['file'];
        if (!is_file($path) || filesize($path) > 1048576) {
            throw new \RuntimeException('User guide missing or too large');
        }
        $content = file_get_contents($path);
        if ($content === false || !preg_match('//u', $content)) {
            throw new \RuntimeException('Unable to read UTF-8 user guide');
        }
        return $content;
    }

    public static function render(string $markdown, string $rootDoc = ''): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $html = (string) (new MarkdownConverter($environment))->convert($markdown);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $used = [];
        foreach ((new \DOMXPath($dom))->query('//h1|//h2|//h3|//h4') as $heading) {
            // These IDs match GitHub's anchors for the ordinary French headings used in the guide.
            $base = str_replace(' ', '-', trim((string) preg_replace('/[^\p{L}\p{N}_ -]/u', '', mb_strtolower($heading->textContent, 'UTF-8'))));
            $base = $base !== '' ? $base : 'section';
            $slug = $base;
            $suffix = 0;
            while (isset($used[$slug])) { $slug = $base . '-' . ++$suffix; }
            $used[$slug] = true;
            $heading->setAttribute('id', $slug);
        }
        foreach (iterator_to_array($dom->getElementsByTagName('img')) as $img) {
            $src = $img->getAttribute('src');
            if (!preg_match('~^\.\./public/images/guide/([a-z0-9][a-z0-9-]*\.png)$~D', $src, $match)) {
                $img->parentNode->replaceChild($dom->createTextNode($img->getAttribute('alt')), $img);
                continue;
            }
            $img->setAttribute('src', rtrim($rootDoc, '/') . '/plugins/workflowbuilder/images/guide/' . $match[1]);
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('decoding', 'async');
            $zoom = $dom->createElement('a');
            $zoom->setAttribute('class', 'wb-guide-image');
            $zoom->setAttribute('href', $img->getAttribute('src'));
            $zoom->setAttribute('target', '_blank');
            $zoom->setAttribute('rel', 'noopener noreferrer');
            $zoom->setAttribute('title', 'Ouvrir la capture en grand');
            $img->parentNode->replaceChild($zoom, $img);
            $zoom->appendChild($img);
        }
        foreach ($dom->getElementsByTagName('a') as $link) {
            if ($link->getAttribute('class') === 'wb-guide-image') { continue; }
            $href = $link->getAttribute('href');
            if (str_starts_with($href, '#')) { continue; }
            // Resolve only bundled documents and examples, never an arbitrary relative path.
            $parts = explode('#', $href, 2);
            $local = str_starts_with($parts[0], './') ? substr($parts[0], 2) : $parts[0];
            foreach (self::PAGES as $key => $page) {
                if ($local === $page['file']) {
                    $link->setAttribute('href', rtrim($rootDoc, '/') . '/plugins/workflowbuilder/front/guide.php?guide=' . $key . (isset($parts[1]) ? '#' . $parts[1] : ''));
                    continue 2;
                }
            }
            foreach (self::EXAMPLES as $example) {
                if ($local === '../examples/' . $example . '.json') {
                    $link->setAttribute('href', rtrim($rootDoc, '/') . '/plugins/workflowbuilder/front/example.php?name=' . $example);
                    continue 2;
                }
            }
            if (preg_match('~^\.\./public/images/guide/([a-z0-9][a-z0-9-]*\.png)$~D', $local, $match)) {
                $link->setAttribute('href', rtrim($rootDoc, '/') . '/plugins/workflowbuilder/images/guide/' . $match[1]);
                continue;
            }
            if (!preg_match('~^https?://~i', $href)) { $link->removeAttribute('href'); continue; }
            $link->setAttribute('rel', 'noopener noreferrer');
        }
        foreach (iterator_to_array($dom->getElementsByTagName('table')) as $table) {
            $table->setAttribute('class', 'table table-bordered');
            $wrapper = $dom->createElement('div');
            $wrapper->setAttribute('class', 'table-responsive');
            $table->parentNode->replaceChild($wrapper, $table);
            $wrapper->appendChild($table);
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        $output = '';
        foreach ($body->childNodes as $child) { $output .= $dom->saveHTML($child); }
        return $output;
    }
}
