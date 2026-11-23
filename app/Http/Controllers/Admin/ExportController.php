<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin → Exports. Each type checks its own `<module>.export` permission on the server. */
class ExportController extends Controller
{
    public function __construct(private ExportService $exports) {}

    public function index(Request $request): View
    {
        $types = $this->exports->allowedTypes($request->user());
        abort_if($types === [], 403, 'You do not have permission to export data.');

        return view('admin.exports', ['types' => $types]);
    }

    public function download(Request $request, string $type): StreamedResponse
    {
        abort_unless($this->exports->can($request->user(), $type), 403, 'You do not have permission to export this data.');
        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:30', 'regex:/^[a-z_]+$/'],
        ]);
        $actor = $request->user();

        Audit::log('export.downloaded', null, ['type' => $type, 'filters' => array_filter($filters)]);

        return response()->streamDownload(function () use ($type, $actor, $filters) {
            $out = fopen('php://output', 'w');
            $this->exports->stream($type, $actor, $filters, $out);
            fclose($out);
        }, 'developerjet-'.$type.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
