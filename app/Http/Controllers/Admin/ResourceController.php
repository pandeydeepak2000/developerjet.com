<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Generic, config-driven admin CRUD (config/admin_resources.php). Each request is authorised against
 * "<module>.<action>" on the backend; the sidebar is only a convenience.
 */
class ResourceController extends Controller
{
    private function config(string $resource): array
    {
        $cfg = config('admin_resources.'.$resource) ?? abort(404);

        // Field-level restriction ('restricted' => [field => permission]): columns and form fields are removed for
        // staff without the permission, so they are neither displayed nor validated/written (values are preserved).
        // Used for supplier purchase costs, which Technical Support must not see by default.
        $user = request()->user();
        $hidden = array_keys(array_filter($cfg['restricted'] ?? [], fn ($permission) => ! $user?->hasPermission($permission)));
        if ($hidden) {
            $cfg['columns'] = array_values(array_filter($cfg['columns'] ?? [], fn ($c) => ! in_array($c[0], $hidden, true)));
            $cfg['fields'] = array_values(array_filter($cfg['fields'] ?? [], fn ($f) => ! in_array($f[0], $hidden, true)));
            $cfg['search'] = array_values(array_diff($cfg['search'] ?? [], $hidden));
        }

        return $cfg;
    }

    private function allow(Request $request, array $cfg, string $action): void
    {
        // Optional per-resource override, e.g. departments: create/edit need tickets.assign.
        $perm = $cfg['module'].'.'.($cfg['perms'][$action] ?? $action);
        abort_unless($request->user()->hasPermission($perm), 403, 'You do not have permission to do that.');
        abort_unless($action === 'view' || in_array($action, $cfg['actions'] ?? [], true), 404);
    }

    private function query(array $cfg)
    {
        $query = $cfg['model']::query()->with($cfg['with'] ?? []);

        if (! empty($cfg['scope'])) {
            $query->where($cfg['scope'][0], $cfg['scope'][1]);
        }

        return $query;
    }

    public function index(Request $request, string $resource): View
    {
        $cfg = $this->config($resource);
        $this->allow($request, $cfg, 'view');

        $query = $this->query($cfg);

        if (($term = trim((string) $request->query('q'))) !== '' && ! empty($cfg['search'])) {
            $query->where(function ($q) use ($cfg, $term) {
                foreach ($cfg['search'] as $col) {
                    $q->orWhere($col, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }

        foreach ($cfg['filters'] ?? [] as [$col, , $options]) {
            $value = $request->query($col);
            if ($value !== null && $value !== '' && array_key_exists($value, $options)) {
                $query->where($col, $value);
            }
        }

        return view('admin.resource.index', [
            'resource' => $resource, 'cfg' => $cfg,
            'rows' => $query->latest('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function create(Request $request, string $resource): View
    {
        $cfg = $this->config($resource);
        $this->allow($request, $cfg, 'create');

        return view('admin.resource.form', ['resource' => $resource, 'cfg' => $cfg, 'row' => new ($cfg['model']), 'options' => $this->options($cfg)]);
    }

    /**
     * Some fields need an extra permission to change (e.g. add-on prices need pricing approval, plans.approve):
     * config 'approval_fields' => ['price_minor' => 'plans.approve'].
     */
    private function guardApprovalFields(Request $request, array $cfg, $row, array $data): void
    {
        foreach ($cfg['approval_fields'] ?? [] as $field => $permission) {
            if (array_key_exists($field, $data) && (string) ($row->getAttribute($field) ?? '') !== (string) ($data[$field] ?? '')) {
                abort_unless($request->user()->hasPermission($permission), 403, 'Changing this price needs pricing approval rights.');
            }
        }
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $cfg = $this->config($resource);
        $this->allow($request, $cfg, 'create');

        $row = new ($cfg['model']);
        $data = $this->validated($request, $cfg, $row);
        $this->guardApprovalFields($request, $cfg, $row, $data);
        $row->forceFill($data)->save();
        Audit::log("admin.{$resource}.created", $row);

        return redirect()->route('admin.resource.index', $resource)->with('success', 'Created.');
    }

    public function edit(Request $request, string $resource, int $id): View
    {
        $cfg = $this->config($resource);
        $this->allow($request, $cfg, 'edit');

        return view('admin.resource.form', ['resource' => $resource, 'cfg' => $cfg, 'row' => $this->query($cfg)->findOrFail($id), 'options' => $this->options($cfg)]);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $cfg = $this->config($resource);
        $this->allow($request, $cfg, 'edit');

        $row = $this->query($cfg)->findOrFail($id);
        $data = $this->validated($request, $cfg, $row);
        $this->guardApprovalFields($request, $cfg, $row, $data);
        $row->forceFill($data)->save();
        Audit::log("admin.{$resource}.updated", $row);

        return redirect()->route('admin.resource.index', $resource)->with('success', 'Saved.');
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $cfg = $this->config($resource);
        $this->allow($request, $cfg, 'delete');

        $row = $this->query($cfg)->findOrFail($id);

        if ($resource === 'servers' && ($row->assigned_order_id || $row->status === 'assigned')) {
            return back()->with('error', 'An assigned server cannot be deleted. Release it first.');
        }

        try {
            $row->delete();
        } catch (QueryException) {
            return back()->with('error', 'This record is in use and cannot be deleted.');
        }

        Audit::log("admin.{$resource}.deleted", null, ['id' => $id]);

        return redirect()->route('admin.resource.index', $resource)->with('success', 'Deleted.');
    }

    private function options(array $cfg): array
    {
        $out = [];
        foreach ($cfg['fields'] ?? [] as $field) {
            if (isset($field[5])) {
                [$model, $label] = $field[5];
                $out[$field[0]] = $model::orderBy($label)->pluck($label, 'id')->all();
            }
        }

        return $out;
    }

    /** @return array<string, mixed> attributes ready for forceFill */
    private function validated(Request $request, array $cfg, Model $row): array
    {
        $rules = [];
        foreach ($cfg['fields'] as $field) {
            [$name, , $type, $ruleString] = $field;
            $parts = array_map(fn ($r) => $r === 'unique' ? Rule::unique($row->getTable(), $name)->ignore($row->getKey()) : $r, explode('|', $ruleString));
            $rules[$name] = $parts;
        }

        $data = $request->validate($rules);
        $attrs = [];

        foreach ($cfg['fields'] as $field) {
            [$name, , $type] = $field;
            $value = $data[$name] ?? null;

            switch ($type) {
                case 'checkbox':
                    $attrs[$name] = $request->boolean($name);
                    break;
                case 'money':
                    $attrs[$name] = ($value === null || $value === '') ? null : (int) round($value * 100);
                    break;
                case 'lines':
                    $attrs[$name] = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value))));
                    break;
                case 'secret':
                    // Write-only: blank keeps the stored value; the model's encrypted cast protects it at rest.
                    if ($value !== null && $value !== '') {
                        $attrs[$name] = $value;
                    }
                    break;
                case 'number':
                    if ($value !== null && $value !== '') {
                        $attrs[$name] = (int) $value;
                    }
                    break;
                default:
                    $attrs[$name] = ($value === '') ? null : $value;
            }
        }

        return $attrs;
    }
}
