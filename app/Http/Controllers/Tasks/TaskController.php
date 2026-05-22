<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class TaskController extends Controller
{
    public function importView(): Response
    {
        return Inertia::render('tasks/Import', [
            'defaultSourcePath' => 'imports/GPM-Designer_Tracker_example data.xlsx',
        ]);
    }

    public function index(Request $request): Response
    {
        $allowedProjectStatuses = ['DONE', 'in progress', 'Upcoming', 'Wait for FBs'];

        $validated = $request->validate([
            'sku' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in($allowedProjectStatuses)],
            'designer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'designer'))],
        ]);

        $tasks = Task::query()
            ->with(['gpm:id,name,email', 'designers:id,name,email'])
            ->when($validated['sku'] ?? null, fn ($query, $sku) => $query->where('sku', 'like', "%{$sku}%"))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('project_status', $status))
            ->when($validated['designer_id'] ?? null, fn ($query, $designerId) => $query->whereHas('designers', fn ($q) => $q->where('users.id', $designerId)))
            ->latest()
            ->get();

        return Inertia::render('tasks/Index', [
            'tasks' => $tasks,
            'designers' => User::query()
                ->where('role', 'designer')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'filters' => [
                'sku' => $validated['sku'] ?? '',
                'status' => $validated['status'] ?? '',
                'designer_id' => $validated['designer_id'] ?? null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'gpm', 403);

        $allowedProjectStatuses = ['DONE', 'in progress', 'Upcoming', 'Wait for FBs'];

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'phase_task' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in($allowedProjectStatuses)],
            'due_date' => ['nullable', 'date'],
            'designer_ids' => ['required', 'array', 'min:1'],
            'designer_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'designer')),
            ],
        ]);

        $task = Task::create([
            'sku' => $validated['sku'],
            'phase_task' => $validated['phase_task'],
            'description' => $validated['description'] ?? null,
            'project_status' => $validated['status'],
            'due_date' => $validated['due_date'] ?? null,
            'gpm_user_id' => $request->user()->id,
        ]);

        $task->designers()->sync($validated['designer_ids']);

        return back();
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        abort_unless($request->user()?->role === 'gpm', 403);

        $allowedProjectStatuses = ['DONE', 'in progress', 'Upcoming', 'Wait for FBs'];

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in($allowedProjectStatuses)],
        ]);

        $task->update(['project_status' => $validated['status']]);

        return back();
    }

    public function updateField(Request $request, Task $task): RedirectResponse
    {
        abort_unless($request->user()?->role === 'gpm', 403);

        $field = (string) $request->input('field');

        $rules = [
            'theme' => ['nullable', 'string', 'max:255'],
            'import_year' => ['nullable', 'string', 'max:255'],
            'batch' => ['nullable', 'string', 'max:255'],
            'artwork_type' => ['nullable', 'string', 'max:255'],
            'phase_task' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tasks', 'phase_task')
                    ->where(fn ($query) => $query->where('sku', $task->sku))
                    ->ignore($task->id),
            ],
            'quantity' => ['nullable', 'string', 'max:255'],
            'wf_plan_week' => ['nullable', 'string', 'max:255'],
            'pv_date_raw' => ['nullable', 'string', 'max:255'],
            'assets_status' => ['nullable', 'string', Rule::in(['Not ready', 'Ready', 'Blanks'])],
            'priority' => ['nullable', 'string', 'max:255'],
            'wip' => ['nullable', 'string', 'max:255'],
            'start_date_week' => ['nullable', 'string', 'max:255'],
            'ready_to_check_week' => ['nullable', 'string', 'max:255'],
            'gpm_note' => ['nullable', 'string', 'max:5000'],
            'gd_notes' => ['nullable', 'string', 'max:5000'],
            'job_number' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
        ];

        if (! array_key_exists($field, $rules)) {
            throw ValidationException::withMessages([
                'field' => 'This field cannot be edited inline.',
            ]);
        }

        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(array_keys($rules))],
            'value' => $rules[$field],
        ]);

        $task->update([
            $field => $validated['value'],
        ]);

        return back();
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'gpm', 403);

        $validated = $request->validate([
            'source_path' => ['nullable', 'string', 'max:500', 'required_without:source_file'],
            'source_file' => ['nullable', 'file', 'mimes:xlsx,csv', 'required_without:source_path'],
        ]);

        $filePath = $this->resolveImportFilePath(
            $validated['source_path'] ?? null,
            $request->file('source_file'),
        );

        if (! is_file($filePath)) {
            return back()->withErrors([
                'import' => 'Import file not found.',
            ]);
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            return back()->withErrors([
                'import' => 'Only .xlsx and .csv files are supported.',
            ]);
        }

        try {
            $rows = $extension === 'xlsx'
                ? $this->readXlsxRows($filePath)
                : $this->readCsvRows($filePath);
        } catch (Throwable $e) {
            return back()->withErrors([
                'import' => 'Unable to read import file: '.$e->getMessage(),
            ]);
        }

        if ($rows === []) {
            return back()->withErrors([
                'import' => 'Import file appears to be empty.',
            ]);
        }

        $designersByName = User::query()
            ->where('role', 'designer')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user) => [mb_strtolower(trim($user->name)) => (int) $user->id])
            ->toArray();

        $gpmsByName = User::query()
            ->where('role', 'gpm')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user) => [mb_strtolower(trim($user->name)) => (int) $user->id])
            ->toArray();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $designersByName, $gpmsByName, &$created, &$updated, &$skipped): void {
            foreach ($rows as $row) {
                $theme = $this->valueByHeader($row, ['theme']);
                $year = $this->valueByHeader($row, ['year']);
                $batch = $this->valueByHeader($row, ['batch']);
                $sku = $this->valueByHeader($row, ['sku/file', 'sku']);
                $phase = $this->valueByHeader($row, ['phase/task', 'phase', 'task']);
                $artworkType = $this->valueByHeader($row, ['artwork type']);
                $projectStatus = $this->valueByHeader($row, ['project status', 'status']);
                $quantity = $this->valueByHeader($row, ['quantity']);
                $wfPlanWeek = $this->valueByHeader($row, ['wf plan (week)', 'wf plan week']);
                $pvDate = $this->valueByHeader($row, ['pv date']);
                $assetsStatus = $this->valueByHeader($row, ['assets status']);
                $priority = $this->valueByHeader($row, ['priority']);
                $designerName = $this->valueByHeader($row, ['designer', 'deisgners']);
                $wip = $this->valueByHeader($row, ['wip']);
                $startDateWeek = $this->valueByHeader($row, ['start date (week)', 'start date week']);
                $readyToCheckWeek = $this->valueByHeader($row, ['ready to check (week)', 'ready to check week']);
                $gpmName = $this->valueByHeader($row, ['gpm']);
                $gpmNote = $this->valueByHeader($row, ['gpm note', 'note']);
                $gdNote = $this->valueByHeader($row, ['gd-notes', 'gd notes']);
                $jobNumber = $this->valueByHeader($row, ['job number']);

                if ($sku === '' || ($phase === '' && $artworkType === '')) {
                    $skipped++;
                    continue;
                }

                $phaseTask = trim($phase !== '' ? $phase : $artworkType);

                $status = $this->mapImportStatus($projectStatus);
                $gpmUserId = $this->ensureUserIdByName($gpmName, 'gpm', $gpmsByName);
                $designerIds = $this->resolveDesignerIds($designerName, $designersByName);

                $task = Task::query()->updateOrCreate(
                    [
                        'sku' => $sku,
                        'phase_task' => $phaseTask,
                    ],
                    [
                        'description' => null,
                        'theme' => $theme !== '' ? $theme : null,
                        'import_year' => $year !== '' ? $year : null,
                        'batch' => $batch !== '' ? $batch : null,
                        'artwork_type' => $artworkType !== '' ? $artworkType : null,
                        'phase_task' => $phaseTask !== '' ? $phaseTask : null,
                        'project_status' => $status,
                        'quantity' => $quantity !== '' ? $quantity : null,
                        'wf_plan_week' => $wfPlanWeek !== '' ? $wfPlanWeek : null,
                        'pv_date_raw' => $pvDate !== '' ? $pvDate : null,
                        'assets_status' => $this->mapImportAssetsStatus($assetsStatus),
                        'priority' => $priority !== '' ? $priority : null,
                        'wip' => $wip !== '' ? $wip : null,
                        'start_date_week' => $startDateWeek !== '' ? $startDateWeek : null,
                        'ready_to_check_week' => $readyToCheckWeek !== '' ? $readyToCheckWeek : null,
                        'gpm_note' => $gpmNote !== '' ? $gpmNote : null,
                        'gd_notes' => $gdNote !== '' ? $gdNote : null,
                        'job_number' => $jobNumber !== '' ? $jobNumber : null,
                        'gpm_user_id' => $gpmUserId,
                    ],
                );

                if ($task->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                if ($designerIds !== []) {
                    $task->designers()->syncWithoutDetaching($designerIds);
                }
            }
        });

        return back()->with('success', "Import complete. Created: {$created}, Updated: {$updated}, Skipped: {$skipped}.");
    }

    private function resolveImportFilePath(?string $sourcePath, ?UploadedFile $sourceFile): string
    {
        if ($sourceFile instanceof UploadedFile) {
            $storedPath = $sourceFile->storeAs(
                'imports',
                now()->format('Ymd_His').'_'.Str::random(8).'_'.$sourceFile->getClientOriginalName(),
            );

            if (! is_string($storedPath) || $storedPath === '') {
                return '';
            }

            return storage_path('app/'.$storedPath);
        }

        if (! is_string($sourcePath) || trim($sourcePath) === '') {
            return '';
        }

        return $this->resolveImportPath($sourcePath);
    }

    private function resolveImportPath(string $sourcePath): string
    {
        $normalized = str_replace('\\', '/', trim($sourcePath));
        $storageAppPath = rtrim(storage_path('app'), '/');

        if (str_starts_with($normalized, $storageAppPath.'/')) {
            return $normalized;
        }

        $relative = ltrim($normalized, '/');

        if (! str_starts_with($relative, 'imports/')) {
            $relative = 'imports/'.$relative;
        }

        return $storageAppPath.'/'.$relative;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');

        if (! $handle) {
            throw new \RuntimeException('Could not open CSV file.');
        }

        $headers = null;
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn (?string $header) => $this->normalizeHeader((string) $header), $values);
                continue;
            }

            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($values[$index] ?? ''));
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readXlsxRows(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Could not open XLSX archive.');
        }

        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new \RuntimeException('Missing worksheet data.');
        }

        $sharedStrings = [];
        if ($sharedStringsXml !== false) {
            $shared = simplexml_load_string($sharedStringsXml);
            if ($shared instanceof SimpleXMLElement) {
                $siList = $shared->xpath('//*[local-name()="si"]') ?: [];
                foreach ($siList as $si) {
                    $parts = $si->xpath('.//*[local-name()="t"]') ?: [];
                    $text = '';
                    foreach ($parts as $part) {
                        $text .= (string) $part;
                    }

                    $sharedStrings[] = $text;
                }
            }
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet instanceof SimpleXMLElement) {
            throw new \RuntimeException('Could not parse worksheet XML.');
        }

        $rowNodes = $sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [];

        $rows = [];
        $headers = null;

        foreach ($rowNodes as $rowNode) {
            $cells = [];
            $cellNodes = $rowNode->xpath('./*[local-name()="c"]') ?: [];
            foreach ($cellNodes as $cellNode) {
                $ref = (string) ($cellNode['r'] ?? '');
                if ($ref === '') {
                    continue;
                }

                $column = preg_replace('/\d+/', '', $ref) ?? '';
                $type = (string) ($cellNode['t'] ?? '');
                $value = (string) ($cellNode->v ?? '');

                if ($type === 's') {
                    $index = (int) $value;
                    $cells[$column] = $sharedStrings[$index] ?? '';
                } else {
                    $cells[$column] = $value;
                }
            }

            if ($cells === []) {
                continue;
            }

            if ($headers === null) {
                $headers = [];
                foreach ($cells as $column => $headerValue) {
                    $normalized = $this->normalizeHeader($headerValue);
                    if ($normalized !== '') {
                        $headers[$column] = $normalized;
                    }
                }
                continue;
            }

            $row = [];
            foreach ($headers as $column => $header) {
                $row[$header] = trim((string) ($cells[$column] ?? ''));
            }

            if (count(array_filter($row, fn ($value) => $value !== '')) === 0) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim(str_replace(["\n", "\r"], ' ', $value)));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return $value;
    }

    /**
     * @param array<string, string> $row
     * @param array<int, string> $headers
     */
    private function valueByHeader(array $row, array $headers): string
    {
        foreach ($headers as $header) {
            $normalized = $this->normalizeHeader($header);
            if (isset($row[$normalized]) && trim($row[$normalized]) !== '') {
                return trim($row[$normalized]);
            }
        }

        return '';
    }

    private function mapImportStatus(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            return 'Upcoming';
        }

        if (str_contains($normalized, 'done') || str_contains($normalized, 'delivered') || str_contains($normalized, 'approved')) {
            return 'DONE';
        }

        if (str_contains($normalized, 'wait') || str_contains($normalized, 'fb')) {
            return 'Wait for FBs';
        }

        if (str_contains($normalized, 'progress') || str_contains($normalized, 'work') || str_contains($normalized, 'check')) {
            return 'in progress';
        }

        return 'Upcoming';
    }

    private function mapImportAssetsStatus(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'blank')) {
            return 'Blanks';
        }

        if (str_contains($normalized, 'ready')) {
            return str_contains($normalized, 'not') ? 'Not ready' : 'Ready';
        }

        return null;
    }

    /**
     * @param array<string, int> $lookup
     */
    private function ensureUserIdByName(string $name, string $role, array &$lookup): ?int
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === '') {
            return null;
        }

        if (isset($lookup[$normalized])) {
            return $lookup[$normalized];
        }

        $existing = User::query()
            ->where('role', $role)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();

        if ($existing) {
            $lookup[$normalized] = (int) $existing->id;

            return (int) $existing->id;
        }

        $user = User::create([
            'name' => trim($name),
            'email' => $this->generateImportEmail(trim($name), $role),
            'role' => $role,
            'password' => Str::random(40),
        ]);

        $lookup[$normalized] = (int) $user->id;

        return (int) $user->id;
    }

    /**
     * @param array<string, int> $designerLookup
     * @return array<int, int>
     */
    private function resolveDesignerIds(string $value, array &$designerLookup): array
    {
        $names = preg_split('/[,;\/|&]+/', $value) ?: [];
        $ids = [];

        foreach ($names as $name) {
            $id = $this->ensureUserIdByName($name, 'designer', $designerLookup);
            if ($id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function generateImportEmail(string $name, string $role): string
    {
        $base = Str::slug($name, '.');
        if ($base === '') {
            $base = $role.'.user';
        }

        $candidate = "{$base}.{$role}@import.local";
        $index = 1;

        while (User::query()->where('email', $candidate)->exists()) {
            $candidate = "{$base}.{$role}.{$index}@import.local";
            $index++;
        }

        return $candidate;
    }
}
