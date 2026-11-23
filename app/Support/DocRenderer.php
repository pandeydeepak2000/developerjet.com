<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Renders a documentation article body (Markdown written in Admin → Documentation) into safe HTML for the
 * three-column docs layout.
 *
 * - Raw HTML in the body is stripped and unsafe links are blocked (admin content is still treated as untrusted).
 * - The page template owns the only <h1>: a leading "# Title" line is removed and any other level-1 heading is
 *   demoted to <h2>, so an article title is never rendered twice.
 * - <h2>/<h3> receive stable, unique ids and are returned as the "On this page" table of contents.
 * - "> **Warning:** …", "> **Note:** …", "> **Tip:** …" and "> **Important:** …" blockquotes become callouts.
 * - A paragraph containing only "[diagram: name]" is replaced by the whitelisted SVG partial docs/diagrams/{name}.
 */
class DocRenderer
{
    public const CALLOUTS = ['warning' => 'warning', 'important' => 'warning', 'note' => 'note', 'tip' => 'tip'];

    /** @return array{html: string, toc: list<array{level: int, id: string, text: string}>, minutes: int} */
    public function render(string $markdown): array
    {
        $markdown = $this->stripLeadingTitle(str_replace("\r\n", "\n", $markdown));

        $html = (string) Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        // Only the page template may render an <h1>.
        $html = preg_replace('#<h1>(.*?)</h1>#s', '<h2>$1</h2>', $html);

        [$html, $toc] = $this->anchorHeadings($html);
        $html = $this->callouts($html);
        $html = $this->diagrams($html);
        $html = preg_replace('#<table>#', '<div class="dj-doc-table"><table>', $html);
        $html = preg_replace('#</table>#', '</table></div>', $html);

        $words = str_word_count(strip_tags($html));

        return ['html' => $html, 'toc' => $toc, 'minutes' => max(1, (int) ceil($words / 200))];
    }

    /** Plain text of a Markdown body (for search snippets). */
    public static function plain(string $markdown): string
    {
        $text = preg_replace('/\[diagram:\s*[a-z0-9-]+\]/i', ' ', $markdown);
        $text = preg_replace('/```.*?```/s', ' ', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
        $text = preg_replace('/^\s*\|?[\s:|-]*-{3,}[\s:|-]*$/m', ' ', $text);   // table separator rows
        $text = preg_replace('/^\s*(?:[-*+]|\d+\.)\s+/m', '', $text);            // list markers
        $text = preg_replace('/^\s*-{3,}\s*$/m', ' ', $text);                    // horizontal rules
        $text = preg_replace('/[#>*_`|]+/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function stripLeadingTitle(string $markdown): string
    {
        return preg_replace('/\A\s*#[ \t]+[^\n]*\n?/', '', $markdown, 1);
    }

    /** @return array{0: string, 1: list<array{level: int, id: string, text: string}>} */
    private function anchorHeadings(string $html): array
    {
        $toc = [];
        $used = [];

        $html = preg_replace_callback('#<h([23])>(.*?)</h\1>#s', function (array $m) use (&$toc, &$used) {
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            $base = Str::slug($text) ?: 'section';
            $id = $base;
            for ($i = 2; isset($used[$id]); $i++) {
                $id = $base.'-'.$i;
            }
            $used[$id] = true;
            $toc[] = ['level' => (int) $m[1], 'id' => $id, 'text' => $text];

            return '<h'.$m[1].' id="'.e($id).'">'.$m[2].'<a class="dj-doc-anchor" href="#'.e($id).'" aria-label="Link to this section">#</a></h'.$m[1].'>';
        }, $html);

        return [$html, $toc];
    }

    private function callouts(string $html): string
    {
        return preg_replace_callback('#<blockquote>\s*<p><strong>(Warning|Important|Note|Tip):?</strong>:?#i', function (array $m) {
            $type = self::CALLOUTS[strtolower($m[1])];

            return '<blockquote role="note" class="dj-callout dj-callout--'.$type.'"><p><strong>'.e(ucfirst(strtolower($m[1]))).':</strong>';
        }, $html);
    }

    private function diagrams(string $html): string
    {
        return preg_replace_callback('#<p>\[diagram:\s*([a-z0-9-]+)\]</p>#i', function (array $m) {
            $view = 'docs.diagrams.'.strtolower($m[1]);

            return view()->exists($view) ? '<figure class="dj-doc-figure">'.view($view)->render().'</figure>' : '';
        }, $html);
    }
}
