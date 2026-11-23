<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SeoReport;
use App\Models\SeoTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SEO service workspace. Everything here is staff-entered or imported from a genuinely connected source. No SEO data
 * source (Search Console, rank tracker) is connected yet, so nothing is estimated or fabricated.
 */
class SeoController extends Controller
{
    private function can(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission('seo.'.$action), 403);
    }

    public function index(Request $request): View
    {
        $this->can($request, 'view');

        return view('admin.seo.index', ['projects' => Project::where('kind', 'seo')->with('user', 'assignee')->withCount(['seoTasks', 'seoAudits', 'seoReports'])->latest('id')->paginate(15)]);
    }

    public function show(Request $request, Project $project): View
    {
        $this->can($request, 'view');
        abort_unless($project->kind === 'seo', 404);

        return view('admin.seo.show', ['project' => $project->load('user', 'seoAudits', 'seoTasks', 'seoReports')]);
    }

    public function audit(Request $request, Project $project): RedirectResponse
    {
        $this->can($request, 'create');
        abort_unless($project->kind === 'seo', 404);
        $data = $request->validate(['website_url' => ['required', 'url', 'max:200'], 'type' => ['required', 'in:technical,meta_tags,sitemap'], 'findings' => ['required', 'string', 'max:6000']]);

        $project->seoAudits()->create(['website_url' => $data['website_url'], 'type' => $data['type'], 'source' => 'manual',
            'findings' => array_values(array_filter(array_map('trim', preg_split('/\R/', $data['findings']))))]);

        return back()->with('success', 'Audit recorded (manual entry).');
    }

    public function task(Request $request, Project $project): RedirectResponse
    {
        $this->can($request, 'create');
        abort_unless($project->kind === 'seo', 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'kind' => ['required', 'in:task,recommendation'], 'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/']]);
        $project->seoTasks()->create($data);

        return back()->with('success', 'Added.');
    }

    public function taskStatus(Request $request, Project $project, SeoTask $task): RedirectResponse
    {
        $this->can($request, 'edit');
        abort_unless($task->project_id === $project->id && $task->kind === 'task', 404);
        $task->update($request->validate(['status' => ['required', 'in:pending,completed']]));

        return back();
    }

    public function report(Request $request, Project $project): RedirectResponse
    {
        $this->can($request, 'create');
        abort_unless($project->kind === 'seo', 404);
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'title' => ['required', 'string', 'max:160'], 'summary' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'], 'publish' => ['nullable', 'boolean']]);

        $path = $request->file('file')->storeAs('seo-reports/'.$project->id, bin2hex(random_bytes(16)).'.pdf', 'local');
        $project->seoReports()->create(['period' => $data['period'], 'title' => $data['title'], 'summary' => $data['summary'] ?? null, 'file_path' => $path,
            'published_at' => $request->boolean('publish') ? now() : null]);

        return back()->with('success', 'Report uploaded'.($request->boolean('publish') ? ' and published to the customer.' : ' (unpublished).'));
    }

    public function publish(Request $request, Project $project, SeoReport $report): RedirectResponse
    {
        $this->can($request, 'edit');
        abort_unless($report->project_id === $project->id, 404);
        $report->update(['published_at' => now()]);

        return back()->with('success', 'Report published.');
    }
}
