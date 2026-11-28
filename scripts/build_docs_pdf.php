<?php

/*
 | Builds the PDFs from the editable Markdown sources (diagrams inlined from docs/diagrams/*.svg) using headless Edge/Chrome.
 | Usage: php scripts/build_docs_pdf.php
 | Env: BROWSER_BIN to override the browser path.
 */
require __DIR__.'/../vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;

$root = dirname(__DIR__);
$browser = getenv('BROWSER_BIN') ?: 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
$jobs = [
    ['docs/03_COMPLETE_WORKFLOW.md', 'docs/generated/DeveloperJet_Complete_Workflow.pdf', 'DeveloperJet Complete Workflow', 'Client to Admin - complete feature workflow, automation, manual and gated steps'],
    ['docs/06_ADMIN_GUIDE.md', 'docs/generated/DeveloperJet_Admin_Guide.pdf', 'Admin Panel Guide', 'Admin dashboard ka har section: kis liye hai, kaun se button hain, aur roz ka workflow — screenshots ke saath',
        'Screenshots ek local test database ke hain (naam, email aur IP sab nakli). Asli passwords, keys ya client data is document mein nahi hai. Jo cheez abhi band ya baad ke liye hai, wo saaf likhi hai.'],
];

$css = <<<'CSS'
@page { size: A4; margin: 18mm 15mm 18mm 15mm; }
* { box-sizing: border-box; }
body { font-family: "Segoe UI", "Nirmala UI", Arial, sans-serif; color: #1d2032; font-size: 10.5pt; line-height: 1.5; }
.cover { height: 255mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; padding: 0 8mm; background: linear-gradient(160deg, #f4f1ff 0%, #ffffff 60%); border-left: 8px solid #6649d1; }
.cover .brand { font-size: 13pt; font-weight: 700; color: #6649d1; letter-spacing: .12em; text-transform: uppercase; }
.cover h1 { font-size: 34pt; line-height: 1.1; margin: 10mm 0 4mm; }
.cover p { font-size: 13pt; color: #50536a; max-width: 140mm; }
.cover .note { margin-top: 14mm; font-size: 10pt; border: 1px solid #d9d4f3; background: #fff; padding: 4mm; border-radius: 6px; }
h1, h2 { color: #2b2166; } h2 { font-size: 17pt; border-bottom: 2px solid #e3defa; padding-bottom: 2mm; margin-top: 10mm; page-break-after: avoid; }
h3 { font-size: 12.5pt; color: #4b32ad; page-break-after: avoid; } h4 { page-break-after: avoid; }
h2.chapter { page-break-before: always; margin-top: 0; }
table { border-collapse: collapse; width: 100%; margin: 4mm 0; font-size: 9.5pt; page-break-inside: auto; }
tr { page-break-inside: avoid; }
th { background: #efecfa; color: #2b2166; text-align: left; } th, td { border: 1px solid #d9d4f3; padding: 2mm 2.5mm; vertical-align: top; }
code { background: #f1f0f7; padding: 0 3px; border-radius: 3px; font-family: Consolas, monospace; font-size: 9.5pt; }
pre { background: #f6f5fb; border: 1px solid #e3defa; padding: 3mm; border-radius: 6px; white-space: pre-wrap; font-size: 9pt; page-break-inside: avoid; }
blockquote { margin: 4mm 0; padding: 3mm 4mm; border-left: 4px solid #6649d1; background: #f7f5ff; page-break-inside: avoid; }
blockquote p { margin: 1mm 0; }
.diagram { margin: 5mm 0; page-break-inside: avoid; text-align: center; border: 1px solid #e3defa; border-radius: 8px; padding: 3mm; }
.diagram svg { width: 100%; height: auto; max-width: 100%; }
.caption { font-size: 9pt; color: #50536a; }
.toc { page-break-after: always; } .toc h2 { margin-top: 0; } .toc ol { padding-left: 6mm; columns: 1; } .toc li { margin: 1mm 0; }
ul, ol { padding-left: 6mm; }
.footer-note { font-size: 8.5pt; color: #666; margin-top: 8mm; }
.shot { margin: 4mm 0 5mm; page-break-inside: avoid; text-align: center; }
.shot img { max-width: 100%; max-height: 205mm; border: 1px solid #d9d4f3; border-radius: 6px; box-shadow: 0 2px 6px rgba(43,33,102,.08); }
.shot figcaption { font-size: 8.5pt; color: #50536a; margin-top: 1.5mm; }
CSS;

$converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);

$defaultNote = 'This document separates what is <em>built and tested</em> from what is <em>planned or gated</em>. Automatic provisioning is not live. No real credentials appear in this document.';

// Optional: build only the jobs whose source path contains this text, e.g. `php scripts/build_docs_pdf.php 06_`.
$only = $argv[1] ?? null;

foreach ($jobs as $job) {
    [$src, $out, $title, $subtitle] = $job;
    if ($only !== null && ! str_contains($src, $only)) {
        continue;
    }
    $note = isset($job[4]) ? htmlspecialchars($job[4]) : $defaultNote;
    $md = file_get_contents("$root/$src");
    $html = (string) $converter->convert($md);

    // Inline diagrams: <img src="diagrams/x.svg" alt="caption">
    $html = preg_replace_callback('/<img src="diagrams\/([a-z0-9-]+\.svg)" alt="([^"]*)"[^>]*>/', function ($m) use ($root) {
        $svg = file_get_contents("$root/docs/diagrams/$m[1]");
        $svg = preg_replace('/<\?xml.*?\?>/s', '', $svg);

        return '</p><div class="diagram">'.$svg.'<div class="caption">'.$m[2].'</div></div><p>';
    }, $html);
    // Inline screenshots: <img src="screens/admin/x.jpg" alt="caption"> (JPEG/PNG kept in docs/screens/).
    $html = preg_replace_callback('/<img src="(screens\/[a-z0-9\/._-]+\.(jpg|png))" alt="([^"]*)"[^>]*>/', function ($m) use ($root) {
        $data = base64_encode((string) file_get_contents("$root/docs/$m[1]"));
        $mime = $m[2] === 'png' ? 'image/png' : 'image/jpeg';

        return '</p><figure class="shot"><img src="data:'.$mime.';base64,'.$data.'" alt="'.$m[3].'"><figcaption>'.$m[3].'</figcaption></figure><p>';
    }, $html);
    $html = str_replace('<p></p>', '', $html);

    // Chapters (h2) start a new page; collect TOC.
    $toc = [];
    $html = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function ($m) use (&$toc) {
        $id = 'c'.(count($toc) + 1);
        $toc[] = [$id, strip_tags($m[1])];

        return '<h2 class="chapter" id="'.$id.'">'.$m[1].'</h2>';
    }, $html);
    $tocHtml = '<div class="toc"><h2>Contents</h2><ol>'.implode('', array_map(fn ($t) => "<li><a href=\"#{$t[0]}\">".htmlspecialchars($t[1]).'</a></li>', $toc)).'</ol></div>';

    $doc = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'.htmlspecialchars($title).'</title><style>'.$css.'</style></head><body>'
        .'<section class="cover"><div class="brand">DeveloperJet · AmbitionHost Pvt. Ltd.</div><h1>'.htmlspecialchars($title).'</h1><p>'.htmlspecialchars($subtitle).'</p>'
        .'<div class="note"><strong>Honesty note.</strong> '.$note.' Generated '.date('d M Y').'.</div></section>'
        .$tocHtml.$html.'</body></html>';

    $buildDir = "$root/storage/app/docs-build";
    @mkdir($buildDir, 0775, true);
    $htmlFile = "$buildDir/".basename($out, '.pdf').'.html';
    file_put_contents($htmlFile, $doc);
    @mkdir(dirname("$root/$out"), 0775, true);
    @unlink("$root/$out");

    $cmd = '"'.$browser.'" --headless=new --disable-gpu --no-pdf-header-footer --print-to-pdf="'.str_replace('/', '\\', "$root/$out").'" "file:///'.str_replace('\\', '/', $htmlFile).'" 2>&1';
    exec($cmd, $output, $code);
    $ok = is_file("$root/$out") && filesize("$root/$out") > 10000;
    echo ($ok ? 'OK   ' : 'FAIL ').$out.($ok ? ' ('.round(filesize("$root/$out") / 1024).' KB)' : " exit=$code").PHP_EOL;
}
