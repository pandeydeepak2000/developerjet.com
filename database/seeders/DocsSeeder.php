<?php

namespace Database\Seeders;

use App\Models\DocArticle;
use App\Models\DocCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Documentation content shipped with the platform. Articles live as Markdown files with a small front-matter block in
 * database/seeders/docs/{category-slug}/{slug}.md:
 *
 *   ---
 *   title: Article title           (rendered as the page's only <h1>; do not repeat it as "# Title" in the body)
 *   excerpt: One-sentence summary  (also used as the meta description)
 *   order: 1                       (position inside the category)
 *   legacy: old-slug               (optional: an earlier slug this article replaces; see config/docs.php redirects)
 *   ---
 *
 * Safe to re-run. Edits made in Admin → Documentation are never overwritten: an existing article is replaced only
 * when its body is byte-identical to a version we shipped earlier (SHIPPED_BODY_HASHES). When you change a shipped
 * article in a later release, add the sha1 of the previous body here so installs that still have it are upgraded.
 */
class DocsSeeder extends Seeder
{
    /** slug => [name, sort order, earlier names that may be renamed automatically]. */
    public const CATEGORIES = [
        'getting-started' => ['Getting started', 1, []],
        'billing' => ['Plans & billing', 2, ['Billing']],
        'products' => ['Products', 3, []],
        'infrastructure' => ['Access & security', 4, ['Infrastructure']],
        'help' => ['Help & support', 5, ['Help']],
    ];

    /** sha1 of every article body shipped by earlier versions of this seeder (v0.1 starter content). */
    public const SHIPPED_BODY_HASHES = [
        'f1e5a3dd999be7550af7a140e364431952009cf9', // payment-methods before PTSure was removed (2026-11-24)
        // Bodies before the self-hosted rewording (2026-11-24): every product is handed over with root access.
        '39c271f58f3dc9bb7db28ec3dcb15310eec1a31f', 'c67064a99b6c4cf83f844ac0b897dff3fce036a0', '00e62bafa60a1d500c9d0faaf69d07db5eb7b74b',
        'b10ec159f1bf551e99766151757136a4a2fd994c', '9fb7224970c3b8e1e208809d3172b4fc96ae7360', '7f5c43852eac5421753c14588d6e10c99a76caa5',
        '6a95327aca6790754a83f6e8346394337a1ac512', '10b75b6bc0e00afbb62d88f0c645ce7e588c09a3', '1970503f5b1615fe193b816fe3c64a16725ef63b',
        '6e25657aa456a81508ad102adcea6ff15954edbd', '2cd0a6e67d8af4a3f594a79eeff5c4f0164c5929',
        '9db903b2818f4ef095609931e12146ca9c10190f', '33c16e8762f601ae0417b7f9261e7074c4d163b8', '8205098ba1eb3da7322eaa32cab40ad6fca18abb',
        '7ee74545f90332d804caddc88d6959dcaf43a11a', 'd154ce1c2ba7f108bddefcbaf9845a0013021ae5', '8171cb3639c6b40a4b5dc347baa488d33c172215',
        'd7efbf21b6f8e8872dc194702580b900607c60c1', '76327baf55d3e3a0bdfadcd21cb29bb0a6764a45', '928731afdc19de9fcc83fe0e80b6e945208edb09',
        '010662becf1e8bebdd1b179f01997a424b5ae622', '61aaf8480562edc6fd91db84cec178d44ef8b389', '1e59d0caca4d014d5bf4c89b9180e4a5b3f52316',
        'f708d6ca7bb3f9ace3153566c39b76fb88b71c3e', '7c276f853e3d26df450aec8fd6e5fcdc53bf6d90', '7c2d5fb70aabcf038d0796d3840d10ae495e1b2f',
        'b6d655070cec236070221b064d3916013e26b662', '4f6552a7b9c4245b4b4f9fe2ba4041057f09d106', '5df064d7949e1fc47d0e0d62bb479abff9f17b3e',
        'f7973f03b383307cb8c9acd216362f921103d8e7',
        // v0.3: custom-domain-setup before the HTTPS section was added.
        '8b64840bc4bc09647f4d5ccc8c0d875521ec43c5',
        // v0.3: customer docs before internal supplier wording was removed.
        'df933ed78c6d8ed12ea42a6fc2c995c398c1dfd1', '6c35208305897825ea9e619afd8ac6757e265e4d', '1d0c3e00c10b90aefee5cc84ceb4888534b7e2a2',
        '591d963717b2e23e0af9c6a95017df9593ac50b2',
        // v0.6: ssh-and-security before the everyday-commands table and the UFW walkthrough were added.
        '07ab39eaa81800b113819b3b2b355979dcb1b42d',
        // 2026-11-27: ai-all-access "coming soon / bring your own key" article, before the API-credit plans launched.
        'a5b9fe25089a1f4ea158a736c432f9adb8a0c3f1',
    ];

    /** @var array{created: int, updated: int, kept: int} */
    public array $stats = ['created' => 0, 'updated' => 0, 'kept' => 0];

    public function run(): void
    {
        foreach (self::CATEGORIES as $slug => [$name, $order, $oldNames]) {
            $category = DocCategory::firstOrCreate(['slug' => $slug], ['name' => $name, 'sort_order' => $order]);
            if ($category->name !== $name && in_array($category->name, $oldNames, true)) {
                $category->update(['name' => $name]);
            }

            foreach (self::articles($slug) as $article) {
                $this->sync($category, $article);
            }
        }

        $this->command?->info("Docs: {$this->stats['created']} created, {$this->stats['updated']} upgraded, {$this->stats['kept']} kept (edited in admin or already current).");
    }

    /** @return list<array{slug: string, title: string, excerpt: string, order: int, legacy: ?string, body: string}> */
    public static function articles(string $categorySlug): array
    {
        $dir = database_path('seeders/docs/'.$categorySlug);
        if (! is_dir($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => $f->getExtension() === 'md')
            ->map(fn ($f) => self::parse($f->getFilenameWithoutExtension(), File::get($f->getPathname())))
            ->sortBy('order')->values()->all();
    }

    /** @return array{slug: string, title: string, excerpt: string, order: int, legacy: ?string, body: string} */
    public static function parse(string $slug, string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $m)) {
            throw new \RuntimeException("Doc file {$slug}.md is missing its front-matter block.");
        }

        $meta = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));
                $meta[$key] = $value;
            }
        }

        if (empty($meta['title'])) {
            throw new \RuntimeException("Doc file {$slug}.md has no title.");
        }

        return [
            'slug' => $slug,
            'title' => $meta['title'],
            'excerpt' => $meta['excerpt'] ?? '',
            'order' => (int) ($meta['order'] ?? 0),
            'legacy' => $meta['legacy'] ?? null,
            'body' => rtrim($m[2])."\n",
        ];
    }

    private function sync(DocCategory $category, array $doc): void
    {
        $values = [
            'doc_category_id' => $category->id, 'title' => $doc['title'], 'excerpt' => $doc['excerpt'],
            'meta_description' => mb_substr($doc['excerpt'], 0, 200), 'body' => $doc['body'], 'sort_order' => $doc['order'],
        ];

        $existing = DocArticle::where('slug', $doc['slug'])->first();
        $legacy = ! $existing && $doc['legacy'] ? DocArticle::where('slug', $doc['legacy'])->first() : null;
        $target = $existing ?? $legacy;

        if (! $target) {
            DocArticle::create($values + ['slug' => $doc['slug'], 'is_published' => true]);
            $this->stats['created']++;

            return;
        }

        if ($target->body === $doc['body'] || ! in_array(sha1($target->body), self::SHIPPED_BODY_HASHES, true)) {
            // Already current, or customised by staff: leave it exactly as it is.
            $this->stats['kept']++;

            return;
        }

        // Unedited shipped content: upgrade in place (keeps id, publish state and, for a legacy slug, renames it).
        $target->update($values + ['slug' => $doc['slug']]);
        $this->stats['updated']++;
    }
}
