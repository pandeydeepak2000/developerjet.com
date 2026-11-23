<?php

namespace App\Http\Controllers;

use App\Models\DocArticle;
use App\Models\DocCategory;
use App\Support\DocRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocsController extends Controller
{
    /** Maximum number of words taken from a search query. */
    private const MAX_TERMS = 6;

    public function index(Request $request): View
    {
        $term = Str::limit(trim((string) $request->query('q')), 100, '');
        $categories = $this->navigation();

        return view('pages.docs-index', [
            'categories' => $categories,
            'term' => $term,
            'results' => $term !== '' ? $this->search($term) : null,
            'popular' => $this->popular($categories),
            'searchIndex' => $this->searchIndex($categories),
        ]);
    }

    public function show(string $slug, DocRenderer $renderer): View|RedirectResponse
    {
        $article = DocArticle::with('category')->where('slug', $slug)->where('is_published', true)->first();

        if (! $article) {
            $target = config('docs.redirects.'.$slug);
            abort_unless($target && DocArticle::where('slug', $target)->where('is_published', true)->exists(), 404);

            return redirect()->route('docs.show', $target, 301);
        }

        $categories = $this->navigation();
        $flat = $categories->flatMap->articles->values();
        $position = $flat->search(fn ($a) => $a->id === $article->id);
        $rendered = $renderer->render($article->body);

        return view('pages.docs-show', [
            'article' => $article,
            'html' => $rendered['html'],
            'toc' => $rendered['toc'],
            'minutes' => $rendered['minutes'],
            'categories' => $categories,
            'previous' => $position !== false && $position > 0 ? $flat[$position - 1] : null,
            'next' => $position !== false ? $flat->get($position + 1) : null,
            'related' => $flat->where('doc_category_id', $article->doc_category_id)->where('id', '!=', $article->id)->take(3)->values(),
            'searchIndex' => $this->searchIndex($categories),
        ]);
    }

    /** Published articles grouped by category, both in sort order. Empty categories are omitted. */
    private function navigation(): Collection
    {
        return DocCategory::with(['articles' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order')->orderBy('title')
            ->select('id', 'doc_category_id', 'slug', 'title', 'excerpt', 'sort_order')])
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($c) => $c->articles->isNotEmpty())->values();
    }

    /**
     * Every word must appear in the title, excerpt or body. Ranked so that title matches come first, then excerpt
     * matches, then body-only matches. Returns each hit with a highlighted plain-text snippet.
     */
    private function search(string $term): Collection
    {
        $words = collect(preg_split('/\s+/', Str::lower($term)))->filter(fn ($w) => mb_strlen($w) >= 2)->unique()->take(self::MAX_TERMS)->values();
        if ($words->isEmpty()) {
            $words = collect([Str::lower($term)]);
        }

        $query = DocArticle::with('category')->where('is_published', true);
        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like));
        }

        return $query->limit(60)->get()
            ->map(function (DocArticle $a) use ($words) {
                $title = Str::lower($a->title);
                $excerpt = Str::lower((string) $a->excerpt);
                $score = 0;
                foreach ($words as $w) {
                    $score += (str_contains($title, $w) ? 10 : 0) + (str_contains($excerpt, $w) ? 3 : 0) + min(5, substr_count(Str::lower($a->body), $w));
                }
                $a->setAttribute('score', $score);
                $a->setAttribute('snippet', $this->snippet(DocRenderer::plain($a->body), $words));

                return $a;
            })
            ->sortByDesc('score')->take(30)->values();
    }

    /** An HTML-safe excerpt around the first matching word, with every query word wrapped in <mark>. */
    private function snippet(string $text, Collection $words): string
    {
        $lower = Str::lower($text);
        $pos = $words->map(fn ($w) => mb_strpos($lower, $w))->filter(fn ($p) => $p !== false)->min() ?? 0;
        $start = max(0, $pos - 70);
        if ($start > 0 && ($space = mb_strpos($text, ' ', $start)) !== false && $space < $pos) {
            $start = $space + 1; // begin on a word boundary
        }
        $chunk = mb_substr($text, $start, 220);
        if (mb_strlen($text) > $start + 220 && ($cut = mb_strrpos($chunk, ' ')) > 150) {
            $chunk = mb_substr($chunk, 0, $cut);
        }
        $chunk = ($start > 0 ? '… ' : '').$chunk.(mb_strlen($text) > $start + 220 ? ' …' : '');

        // Split on the raw text first, then escape each part, so highlighting can never break an HTML entity.
        $pattern = $words->map(fn ($w) => preg_quote($w, '/'))->implode('|');
        $parts = preg_split('/('.$pattern.')/iu', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$chunk];

        return collect($parts)->map(fn ($part, $i) => $i % 2 ? '<mark>'.e($part).'</mark>' : e($part))->implode('');
    }

    private function popular(Collection $categories): Collection
    {
        $all = $categories->flatMap->articles->keyBy('slug');

        return collect(config('docs.popular', []))->map(fn ($slug) => $all->get($slug))->filter()->values();
    }

    /** Small client-side index for instant results; the server-side search above works without JavaScript. */
    private function searchIndex(Collection $categories): array
    {
        return $categories->flatMap(fn ($c) => $c->articles->map(fn ($a) => [
            't' => $a->title, 'e' => Str::limit((string) $a->excerpt, 120), 'c' => $c->name, 'u' => route('docs.show', $a->slug),
        ]))->values()->all();
    }
}
