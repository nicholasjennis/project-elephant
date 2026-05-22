<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    
    FlexRender,
    createColumnHelper,
    functionalUpdate,
    getCoreRowModel,
    getFilteredRowModel,
    getSortedRowModel,
    useVueTable
    
} from '@tanstack/vue-table';
import type {ColumnFiltersState, SortingState} from '@tanstack/vue-table';
import { Check, Copy } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import type { User } from '@/types';

type Designer = Pick<User, 'id' | 'name' | 'email'>;

type TaskItem = {
    id: number;
    sku: string;
    description: string | null;
    theme: string | null;
    import_year: string | null;
    batch: string | null;
    artwork_type: string | null;
    phase_task: string | null;
    project_status: 'DONE' | 'in progress' | 'Upcoming' | 'Wait for FBs' | null;
    quantity: string | null;
    wf_plan_week: string | null;
    pv_date_raw: string | null;
    assets_status: 'Not ready' | 'Ready' | 'Blanks' | null;
    priority: string | null;
    wip: string | null;
    start_date_week: string | null;
    ready_to_check_week: string | null;
    gpm_note: string | null;
    gd_notes: string | null;
    job_number: string | null;
    due_date: string | null;
    gpm: Pick<User, 'id' | 'name' | 'email'> | null;
    designers: Designer[];
};

const props = defineProps<{
    tasks: {
        data: TaskItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    designers: Designer[];
    filters: {
        sku: string;
        status: '' | 'DONE' | 'in progress' | 'Upcoming' | 'Wait for FBs';
        designer_id: number | null;
        per_page: number;
    };
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user as User);
const canCreate = computed(() => currentUser.value.role === 'gpm');
const isCreateModalOpen = ref(false);
const designerSearch = ref('');

const createForm = useForm({
    sku: '',
    phase_task: '',
    description: '',
    theme: '',
    import_year: '',
    batch: '',
    artwork_type: '',
    status: 'Upcoming' as 'DONE' | 'in progress' | 'Upcoming' | 'Wait for FBs',
    quantity: '',
    wf_plan_week: '',
    pv_date_raw: '',
    assets_status: '' as '' | 'Not ready' | 'Ready' | 'Blanks',
    priority: '',
    wip: '',
    start_date_week: '',
    ready_to_check_week: '',
    gpm_note: '',
    gd_notes: '',
    job_number: '',
    due_date: '',
    designer_ids: [] as number[],
});

const filterForm = useForm({
    sku: props.filters.sku ?? '',
    status: props.filters.status ?? '',
    designer_id: props.filters.designer_id ?? '',
});

type TaskColumn = {
    key:
        | 'sku'
        | 'theme'
        | 'import_year'
        | 'batch'
        | 'artwork_type'
        | 'phase_task'
        | 'project_status'
        | 'quantity'
        | 'wf_plan_week'
        | 'pv_date_raw'
        | 'assets_status'
        | 'priority'
        | 'wip'
        | 'start_date_week'
        | 'ready_to_check_week'
        | 'gpm'
        | 'gpm_note'
        | 'gd_notes'
        | 'job_number'
        | 'designers'
        | 'due_date'
        | 'description';
    label: string;
};

const allColumns: TaskColumn[] = [
    { key: 'sku', label: 'SKU' },
    { key: 'theme', label: 'Theme' },
    { key: 'import_year', label: 'Year' },
    { key: 'batch', label: 'Batch' },
    { key: 'artwork_type', label: 'Artwork Type' },
    { key: 'phase_task', label: 'Phase/Task' },
    { key: 'project_status', label: 'Project Status' },
    { key: 'description', label: 'Description' },
    { key: 'quantity', label: 'Quantity' },
    { key: 'wf_plan_week', label: 'WF Plan (Week)' },
    { key: 'pv_date_raw', label: 'PV Date' },
    { key: 'assets_status', label: 'Assets Status' },
    { key: 'priority', label: 'Priority' },
    { key: 'wip', label: 'WIP' },
    { key: 'start_date_week', label: 'Start Date (Week)' },
    { key: 'ready_to_check_week', label: 'Ready To Check (Week)' },
    { key: 'gpm', label: 'GPM' },
    { key: 'gpm_note', label: 'GPM Note' },
    { key: 'gd_notes', label: 'GD Notes' },
    { key: 'job_number', label: 'Job Number' },
    { key: 'designers', label: 'Designers' },
    { key: 'due_date', label: 'Due' },
];

const rowsToShow = ref<string>(String(props.filters.per_page ?? props.tasks.per_page ?? 50));
const selectedColumnKeys = ref<TaskColumn['key'][]>(allColumns.map((column) => column.key));

const visibleColumns = computed(() => {
    if (selectedColumnKeys.value.length === 0) {
        return allColumns;
    }

    return allColumns.filter((column) => selectedColumnKeys.value.includes(column.key));
});

const sorting = ref<SortingState>([]);
const columnFilters = ref<ColumnFiltersState>([]);
const columnHelper = createColumnHelper<TaskItem>();
const editingCell = ref<{ taskId: number; columnKey: TaskColumn['key'] } | null>(null);
const editingValue = ref('');
const isSavingInlineEdit = ref(false);
const copiedField = ref<null | 'sku' | 'job_number' | 'gpm_note' | 'gd_notes'>(null);
let copyFeedbackTimeout: ReturnType<typeof setTimeout> | null = null;

type EditableField =
    | 'theme'
    | 'import_year'
    | 'batch'
    | 'artwork_type'
    | 'phase_task'
    | 'quantity'
    | 'wf_plan_week'
    | 'pv_date_raw'
    | 'assets_status'
    | 'priority'
    | 'wip'
    | 'start_date_week'
    | 'ready_to_check_week'
    | 'gpm_note'
    | 'gd_notes'
    | 'job_number'
    | 'description'
    | 'due_date';

const editableColumnFieldMap: Partial<Record<TaskColumn['key'], EditableField>> = {
    theme: 'theme',
    import_year: 'import_year',
    batch: 'batch',
    artwork_type: 'artwork_type',
    phase_task: 'phase_task',
    quantity: 'quantity',
    wf_plan_week: 'wf_plan_week',
    pv_date_raw: 'pv_date_raw',
    assets_status: 'assets_status',
    priority: 'priority',
    wip: 'wip',
    start_date_week: 'start_date_week',
    ready_to_check_week: 'ready_to_check_week',
    gpm_note: 'gpm_note',
    gd_notes: 'gd_notes',
    job_number: 'job_number',
    description: 'description',
    due_date: 'due_date',
};

const sortedDesigners = computed(() => props.designers);
const selectedDesigners = computed(() =>
    props.designers.filter((designer) => createForm.designer_ids.includes(designer.id)),
);
const filteredDesigners = computed(() => {
    const query = designerSearch.value.trim().toLowerCase();

    if (!query) {
        return sortedDesigners.value;
    }

    return sortedDesigners.value.filter((designer) =>
        `${designer.name} ${designer.email}`.toLowerCase().includes(query),
    );
});

function toggleDesigner(designerId: number, checked: boolean): void {
    if (checked) {
        createForm.designer_ids = [...new Set([...createForm.designer_ids, designerId])];

        return;
    }

    createForm.designer_ids = createForm.designer_ids.filter((id) => id !== designerId);
}

async function copyFieldValue(field: 'sku' | 'job_number' | 'gpm_note' | 'gd_notes', value: string): Promise<void> {
    const textToCopy = value.trim();

    if (!textToCopy) {
        return;
    }

    try {
        await navigator.clipboard.writeText(textToCopy);
        copiedField.value = field;

        if (copyFeedbackTimeout) {
            clearTimeout(copyFeedbackTimeout);
        }

        copyFeedbackTimeout = setTimeout(() => {
            copiedField.value = null;
        }, 1200);
    } catch {
        copiedField.value = null;
    }
}

function submitCreate(): void {
    createForm.post('/tasks', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset(
                'sku',
                'phase_task',
                'description',
                'theme',
                'import_year',
                'batch',
                'artwork_type',
                'status',
                'quantity',
                'wf_plan_week',
                'pv_date_raw',
                'assets_status',
                'priority',
                'wip',
                'start_date_week',
                'ready_to_check_week',
                'gpm_note',
                'gd_notes',
                'job_number',
                'due_date',
                'designer_ids',
            );
            designerSearch.value = '';
            isCreateModalOpen.value = false;
        },
    });
}

function applyFilters(): void {
    router.get(
        '/tasks',
        {
            sku: filterForm.sku || undefined,
            status: filterForm.status || undefined,
            designer_id: filterForm.designer_id || undefined,
            per_page: Number.parseInt(rowsToShow.value, 10) || 50,
            page: 1,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters(): void {
    filterForm.reset();
    router.get(
        '/tasks',
        {
            per_page: Number.parseInt(rowsToShow.value, 10) || 50,
            page: 1,
        },
        { preserveState: true, replace: true },
    );
}

function goToPage(pageNumber: number): void {
    if (pageNumber < 1 || pageNumber > props.tasks.last_page || pageNumber === props.tasks.current_page) {
        return;
    }

    router.get(
        '/tasks',
        {
            sku: filterForm.sku || undefined,
            status: filterForm.status || undefined,
            designer_id: filterForm.designer_id || undefined,
            per_page: Number.parseInt(rowsToShow.value, 10) || 50,
            page: pageNumber,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function updatePerPage(): void {
    router.get(
        '/tasks',
        {
            sku: filterForm.sku || undefined,
            status: filterForm.status || undefined,
            designer_id: filterForm.designer_id || undefined,
            per_page: Number.parseInt(rowsToShow.value, 10) || 50,
            page: 1,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function updateStatus(taskId: number, status: Exclude<TaskItem['project_status'], null>): void {
    router.patch(`/tasks/${taskId}/status`, { status }, { preserveScroll: true });
}

function statusClasses(status: Exclude<TaskItem['project_status'], null>): string {
    if (status === 'DONE') {
return 'border-emerald-300 bg-emerald-50 text-emerald-800';
}

    if (status === 'in progress') {
return 'border-blue-300 bg-blue-50 text-blue-800';
}

    if (status === 'Wait for FBs') {
return 'border-rose-300 bg-rose-50 text-rose-800';
}

    return 'border-amber-300 bg-amber-50 text-amber-800';
}

function getColumnValue(task: TaskItem, key: TaskColumn['key']): string {
    if (key === 'gpm') {
return task.gpm?.name ?? 'Unassigned';
}

    if (key === 'designers') {
return task.designers.map((designer) => designer.name).join(', ');
}

    if (key === 'due_date') {
return task.due_date ?? '-';
}

    if (key === 'description') {
return task.description ?? '-';
}

    const value = task[key as keyof TaskItem];

    if (typeof value === 'string' && value !== '') {
        return value;
    }

    return '-';
}

function isEditableColumn(columnKey: TaskColumn['key']): boolean {
    return canCreate.value && Boolean(editableColumnFieldMap[columnKey]);
}

function beginCellEdit(task: TaskItem, columnKey: TaskColumn['key']): void {
    if (!isEditableColumn(columnKey)) {
        return;
    }

    editingCell.value = { taskId: task.id, columnKey };
    const currentValue = getColumnValue(task, columnKey);
    editingValue.value = currentValue === '-' ? '' : currentValue;
}

function cancelCellEdit(): void {
    editingCell.value = null;
    editingValue.value = '';
}

async function saveCellEdit(task: TaskItem, columnKey: TaskColumn['key']): Promise<void> {
    const field = editableColumnFieldMap[columnKey];

    if (!field || isSavingInlineEdit.value) {
        return;
    }

    isSavingInlineEdit.value = true;

    router.patch(
        `/tasks/${task.id}/field`,
        {
            field,
            value: editingValue.value.trim() === '' ? null : editingValue.value.trim(),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isSavingInlineEdit.value = false;
                cancelCellEdit();
            },
        },
    );
}

function isEditingCell(taskId: number, columnKey: TaskColumn['key']): boolean {
    return editingCell.value?.taskId === taskId && editingCell.value?.columnKey === columnKey;
}

function toggleColumn(key: TaskColumn['key'], checked: boolean): void {
    if (checked) {
        selectedColumnKeys.value = [...new Set([...selectedColumnKeys.value, key])];

        return;
    }

    if (selectedColumnKeys.value.length <= 1) {
        return;
    }

    selectedColumnKeys.value = selectedColumnKeys.value.filter((selectedKey) => selectedKey !== key);
}

const tableColumns = computed(() =>
    visibleColumns.value.map((column) => {
        if (column.key === 'project_status') {
            return columnHelper.accessor('project_status', {
                header: column.label,
                cell: ({ row }) => row.original.project_status ?? 'Upcoming',
                enableSorting: true,
                enableColumnFilter: true,
            });
        }

        return columnHelper.accessor((row) => getColumnValue(row, column.key), {
            id: column.key,
            header: column.label,
            cell: (info) => info.getValue(),
            enableSorting: true,
        });
    }),
);

const table = useVueTable({
    get data() {
        return props.tasks.data;
    },
    get columns() {
        return tableColumns.value;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
        get columnFilters() {
            return columnFilters.value;
        },
    },
    onSortingChange: (updater) => {
        sorting.value = functionalUpdate(updater, sorting.value);
    },
    onColumnFiltersChange: (updater) => {
        columnFilters.value = functionalUpdate(updater, columnFilters.value);
    },
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
});

const visibleRows = computed(() => {
    return table.getRowModel().rows;
});

const paginationRange = computed(() => {
    const current = props.tasks.current_page;
    const last = props.tasks.last_page;

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    if (current <= 4) {
        return [1, 2, 3, 4, 5, -1, last];
    }

    if (current >= last - 3) {
        return [1, -1, last - 4, last - 3, last - 2, last - 1, last];
    }

    return [1, -1, current - 1, current, current + 1, -1, last];
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Tasks',
                href: '/tasks',
            },
        ],
    },
});
</script>

<template>
    <Head title="Tasks" />

    <div class="space-y-6 p-4">
        <section class="rounded-xl border border-sidebar-border/70 bg-background p-4">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-lg font-semibold">Task Filters</h1>

                <Dialog v-if="canCreate" v-model:open="isCreateModalOpen">
                    <DialogTrigger as-child>
                        <Button>Create Task</Button>
                    </DialogTrigger>
                    <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Create Task</DialogTitle>
                            <DialogDescription>
                                Add task details and assign one or more designers.
                            </DialogDescription>
                        </DialogHeader>

                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitCreate">
                            <label class="grid gap-1 text-sm">
                                <span class="flex items-center justify-between gap-2">
                                    <span>SKU</span>
                                    <button
                                        class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                        type="button"
                                        @click="copyFieldValue('sku', createForm.sku)"
                                    >
                                        <Check v-if="copiedField === 'sku'" class="h-3.5 w-3.5" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                        <span>{{ copiedField === 'sku' ? 'Copied' : 'Copy' }}</span>
                                    </button>
                                </span>
                                <input v-model="createForm.sku" class="rounded-md border bg-white px-3 py-2" type="text" />
                                <span v-if="createForm.errors.sku" class="text-xs text-red-600">{{ createForm.errors.sku }}</span>
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Phase/Task</span>
                                <input v-model="createForm.phase_task" class="rounded-md border bg-white px-3 py-2" type="text" />
                                <span v-if="createForm.errors.phase_task" class="text-xs text-red-600">{{ createForm.errors.phase_task }}</span>
                            </label>

                            <label class="grid gap-1 text-sm md:col-span-2">
                                <span>Description</span>
                                <textarea v-model="createForm.description" class="min-h-24 rounded-md border bg-white px-3 py-2" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Theme</span>
                                <input v-model="createForm.theme" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Year</span>
                                <input v-model="createForm.import_year" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Batch</span>
                                <input v-model="createForm.batch" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Artwork Type</span>
                                <input v-model="createForm.artwork_type" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Project Status</span>
                                <select v-model="createForm.status" class="rounded-md border bg-white px-3 py-2">
                                    <option value="Upcoming">Upcoming</option>
                                    <option value="in progress">in progress</option>
                                    <option value="Wait for FBs">Wait for FBs</option>
                                    <option value="DONE">DONE</option>
                                </select>
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Due date</span>
                                <input v-model="createForm.due_date" class="rounded-md border bg-white px-3 py-2" type="date" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Quantity</span>
                                <input v-model="createForm.quantity" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>WF Plan (Week)</span>
                                <input v-model="createForm.wf_plan_week" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>PV Date</span>
                                <input v-model="createForm.pv_date_raw" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Assets Status</span>
                                <select v-model="createForm.assets_status" class="rounded-md border bg-white px-3 py-2">
                                    <option value="">-</option>
                                    <option value="Not ready">Not ready</option>
                                    <option value="Ready">Ready</option>
                                    <option value="Blanks">Blanks</option>
                                </select>
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Priority</span>
                                <input v-model="createForm.priority" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>WIP</span>
                                <input v-model="createForm.wip" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Start Date (Week)</span>
                                <input v-model="createForm.start_date_week" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span>Ready To Check (Week)</span>
                                <input v-model="createForm.ready_to_check_week" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span class="flex items-center justify-between gap-2">
                                    <span>Job Number</span>
                                    <button
                                        class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                        type="button"
                                        @click="copyFieldValue('job_number', createForm.job_number)"
                                    >
                                        <Check v-if="copiedField === 'job_number'" class="h-3.5 w-3.5" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                        <span>{{ copiedField === 'job_number' ? 'Copied' : 'Copy' }}</span>
                                    </button>
                                </span>
                                <input v-model="createForm.job_number" class="rounded-md border bg-white px-3 py-2" type="text" />
                            </label>

                            <label class="grid gap-1 text-sm md:col-span-2">
                                <span class="flex items-center justify-between gap-2">
                                    <span>GPM Note</span>
                                    <button
                                        class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                        type="button"
                                        @click="copyFieldValue('gpm_note', createForm.gpm_note)"
                                    >
                                        <Check v-if="copiedField === 'gpm_note'" class="h-3.5 w-3.5" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                        <span>{{ copiedField === 'gpm_note' ? 'Copied' : 'Copy' }}</span>
                                    </button>
                                </span>
                                <textarea v-model="createForm.gpm_note" class="min-h-20 rounded-md border bg-white px-3 py-2" />
                            </label>

                            <label class="grid gap-1 text-sm md:col-span-2">
                                <span class="flex items-center justify-between gap-2">
                                    <span>GD Notes</span>
                                    <button
                                        class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                        type="button"
                                        @click="copyFieldValue('gd_notes', createForm.gd_notes)"
                                    >
                                        <Check v-if="copiedField === 'gd_notes'" class="h-3.5 w-3.5" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                        <span>{{ copiedField === 'gd_notes' ? 'Copied' : 'Copy' }}</span>
                                    </button>
                                </span>
                                <textarea v-model="createForm.gd_notes" class="min-h-20 rounded-md border bg-white px-3 py-2" />
                            </label>

                            <fieldset class="grid gap-2 text-sm md:col-span-2">
                                <legend class="font-medium">Designers</legend>

                                <input
                                    v-model="designerSearch"
                                    class="rounded-md border bg-white px-3 py-2"
                                    type="text"
                                    placeholder="Search designers by name or email"
                                />

                                <div class="max-h-56 space-y-2 overflow-y-auto rounded-md border p-2">
                                    <label
                                        v-for="designer in filteredDesigners"
                                        :key="designer.id"
                                        class="flex items-center gap-2 rounded-md border px-3 py-2"
                                    >
                                        <input
                                            :checked="createForm.designer_ids.includes(designer.id)"
                                            type="checkbox"
                                            @change="toggleDesigner(designer.id, ($event.target as HTMLInputElement).checked)"
                                        />
                                        <span>{{ designer.name }} ({{ designer.email }})</span>
                                    </label>
                                    <div v-if="filteredDesigners.length === 0" class="px-2 py-3 text-xs text-muted-foreground">
                                        No designers match your search.
                                    </div>
                                </div>

                                <div v-if="selectedDesigners.length > 0" class="flex flex-wrap gap-2 pt-1">
                                    <span
                                        v-for="designer in selectedDesigners"
                                        :key="designer.id"
                                        class="rounded-full border px-2 py-1 text-xs"
                                    >
                                        {{ designer.name }}
                                    </span>
                                </div>

                                <span v-if="createForm.errors.designer_ids" class="text-xs text-red-600">{{ createForm.errors.designer_ids }}</span>
                            </fieldset>

                            <DialogFooter class="md:col-span-2">
                                <Button type="submit" :disabled="createForm.processing">Create Task</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <form class="mt-4 grid gap-4 md:grid-cols-4" @submit.prevent="applyFilters">
                <label class="grid gap-1 text-sm">
                    <span>SKU</span>
                    <input v-model="filterForm.sku" class="rounded-md border bg-white px-3 py-2" type="text" />
                </label>

                <label class="grid gap-1 text-sm">
                    <span>Project Status</span>
                    <select v-model="filterForm.status" class="rounded-md border bg-white px-3 py-2">
                        <option value="">All</option>
                        <option value="Upcoming">Upcoming</option>
                        <option value="in progress">in progress</option>
                        <option value="Wait for FBs">Wait for FBs</option>
                        <option value="DONE">DONE</option>
                    </select>
                </label>

                <label class="grid gap-1 text-sm">
                    <span>Designer</span>
                    <select v-model="filterForm.designer_id" class="rounded-md border bg-white px-3 py-2">
                        <option value="">All</option>
                        <option v-for="designer in sortedDesigners" :key="designer.id" :value="designer.id">
                            {{ designer.name }}
                        </option>
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button class="rounded-md bg-black px-4 py-2 text-sm font-medium text-white" type="submit">Apply</button>
                    <button class="rounded-md border px-4 py-2 text-sm font-medium" type="button" @click="resetFilters">Reset</button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-sidebar-border/70 bg-background p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <h2 class="text-lg font-semibold">Task List</h2>

                <div class="flex flex-col gap-3 text-sm md:flex-row md:items-end">
                    <label class="grid gap-1">
                        <span>Rows per page</span>
                        <select v-model="rowsToShow" class="rounded-md border px-3 py-2" @change="updatePerPage">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </label>

                    <div class="grid gap-1 md:min-w-80">
                        <span>Visible columns</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button class="justify-between" variant="outline">
                                    {{ selectedColumnKeys.length }} selected
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="max-h-72 w-72 overflow-y-auto">
                                <DropdownMenuCheckboxItem
                                    v-for="column in allColumns"
                                    :key="column.key"
                                    :checked="selectedColumnKeys.includes(column.key)"
                                    @select.prevent
                                    @update:checked="(checked: boolean) => toggleColumn(column.key, checked)"
                                >
                                    {{ column.label }}
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-lg border">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id" class="border-b bg-muted/40">
                            <th v-for="header in headerGroup.headers" :key="header.id" class="px-3 py-2 font-semibold">
                                <div v-if="!header.isPlaceholder" class="space-y-2">
                                    <button
                                        class="inline-flex items-center gap-1 text-left hover:text-foreground"
                                        type="button"
                                        @click="header.column.getToggleSortingHandler()?.($event)"
                                    >
                                        <FlexRender :render="header.column.columnDef.header" :props="header.getContext()" />
                                        <span v-if="header.column.getIsSorted() === 'asc'">▲</span>
                                        <span v-else-if="header.column.getIsSorted() === 'desc'">▼</span>
                                    </button>

                                    <select
                                        v-if="header.column.id === 'project_status'"
                                        :value="(header.column.getFilterValue() as string) ?? ''"
                                        class="h-8 w-full rounded-md border bg-white px-2 text-xs font-normal"
                                        @change="header.column.setFilterValue(($event.target as HTMLSelectElement).value || undefined)"
                                    >
                                        <option value="">All</option>
                                        <option value="Upcoming">Upcoming</option>
                                        <option value="in progress">in progress</option>
                                        <option value="Wait for FBs">Wait for FBs</option>
                                        <option value="DONE">DONE</option>
                                    </select>

                                    <select
                                        v-else-if="header.column.id === 'assets_status'"
                                        :value="(header.column.getFilterValue() as string) ?? ''"
                                        class="h-8 w-full rounded-md border bg-white px-2 text-xs font-normal"
                                        @change="header.column.setFilterValue(($event.target as HTMLSelectElement).value || undefined)"
                                    >
                                        <option value="">All</option>
                                        <option value="Not ready">Not ready</option>
                                        <option value="Ready">Ready</option>
                                        <option value="Blanks">Blanks</option>
                                    </select>

                                    <input
                                        v-else
                                        :value="(header.column.getFilterValue() as string) ?? ''"
                                        class="h-8 w-full rounded-md border bg-white px-2 text-xs font-normal"
                                        placeholder="Filter..."
                                        type="text"
                                        @input="header.column.setFilterValue(($event.target as HTMLInputElement).value || undefined)"
                                    />
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in visibleRows" :key="row.id" class="border-b align-top odd:bg-accent/10 even:bg-background hover:bg-accent/25">
                            <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-3 py-2 align-top">
                                <template v-if="cell.column.id === 'project_status'">
                                    <select
                                        v-if="canCreate"
                                        :value="row.original.project_status ?? 'Upcoming'"
                                        :class="['rounded-md border bg-white px-2 py-1 font-medium', statusClasses(row.original.project_status ?? 'Upcoming')]"
                                        @change="updateStatus(row.original.id, ($event.target as HTMLSelectElement).value as Exclude<TaskItem['project_status'], null>)"
                                    >
                                        <option value="Upcoming">Upcoming</option>
                                        <option value="in progress">in progress</option>
                                        <option value="Wait for FBs">Wait for FBs</option>
                                        <option value="DONE">DONE</option>
                                    </select>
                                    <span v-else :class="['inline-flex rounded-full border px-2 py-0.5 text-xs font-medium', statusClasses(row.original.project_status ?? 'Upcoming')]">
                                        {{ row.original.project_status ?? 'Upcoming' }}
                                    </span>
                                </template>
                                <template v-else-if="isEditableColumn(cell.column.id as TaskColumn['key'])">
                                    <div
                                        class="cursor-text rounded-sm px-1 py-0.5 hover:bg-white/90"
                                        @dblclick="beginCellEdit(row.original, cell.column.id as TaskColumn['key'])"
                                    >
                                        <template v-if="isEditingCell(row.original.id, cell.column.id as TaskColumn['key'])">
                                            <select
                                                v-if="cell.column.id === 'assets_status'"
                                                v-model="editingValue"
                                                class="h-8 w-full rounded-md border bg-white px-2 text-sm"
                                                @blur="saveCellEdit(row.original, cell.column.id as TaskColumn['key'])"
                                                @keydown.esc.prevent="cancelCellEdit"
                                                @keydown.enter.prevent="saveCellEdit(row.original, cell.column.id as TaskColumn['key'])"
                                            >
                                                <option value="">-</option>
                                                <option value="Not ready">Not ready</option>
                                                <option value="Ready">Ready</option>
                                                <option value="Blanks">Blanks</option>
                                            </select>
                                            <input
                                                v-else
                                                v-model="editingValue"
                                                :type="cell.column.id === 'due_date' ? 'date' : 'text'"
                                                class="h-8 w-full rounded-md border bg-white px-2 text-sm"
                                                @blur="saveCellEdit(row.original, cell.column.id as TaskColumn['key'])"
                                                @keydown.esc.prevent="cancelCellEdit"
                                                @keydown.enter.prevent="saveCellEdit(row.original, cell.column.id as TaskColumn['key'])"
                                            />
                                        </template>
                                        <template v-else>
                                            <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                        </template>
                                    </div>
                                </template>
                                <template v-else>
                                    <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                </template>
                            </td>
                        </tr>
                        <tr v-if="visibleRows.length === 0">
                            <td class="px-2 py-6 text-center text-muted-foreground" :colspan="visibleColumns.length">No tasks match the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 text-sm md:flex-row md:items-center md:justify-between">
                <p class="text-muted-foreground">
                    Showing {{ props.tasks.from ?? 0 }}-{{ props.tasks.to ?? 0 }} of {{ props.tasks.total }} tasks
                </p>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="props.tasks.current_page <= 1"
                        @click="goToPage(props.tasks.current_page - 1)"
                    >
                        Previous
                    </Button>

                    <template v-for="(item, index) in paginationRange" :key="`${item}-${index}`">
                        <span v-if="item === -1" class="px-1 text-muted-foreground">...</span>
                        <Button
                            v-else
                            :variant="item === props.tasks.current_page ? 'default' : 'outline'"
                            size="sm"
                            class="min-w-9"
                            @click="goToPage(item)"
                        >
                            {{ item }}
                        </Button>
                    </template>

                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="props.tasks.current_page >= props.tasks.last_page"
                        @click="goToPage(props.tasks.current_page + 1)"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </section>
    </div>
</template>
