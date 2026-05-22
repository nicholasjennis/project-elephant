<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const importForm = useForm({
    source_file: null as File | null,
});
const page = usePage();
const importError = computed(() => (page.props.errors as Record<string, string | undefined>).import);
const successMessage = computed(() => {
    const flash = (page.props.flash ?? {}) as Record<string, string | undefined>;

    return flash.success ?? '';
});

function submitImport(): void {
    importForm.post('/tasks/import', {
        preserveScroll: true,
        forceFormData: true,
    });
}

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    importForm.source_file = target.files?.[0] ?? null;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Task Import',
                href: '/tasks/import',
            },
        ],
    },
});
</script>

<template>
    <Head title="Task Import" />

    <div class="space-y-6 p-4">
        <section class="rounded-xl border border-sidebar-border/70 bg-background p-4">
            <h1 class="text-lg font-semibold">Import Tasks</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Import `.xlsx` or `.csv` by uploading a file.
            </p>

            <div v-if="successMessage" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                {{ successMessage }}
            </div>

            <div v-if="importError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ importError }}
            </div>

            <form class="mt-4 grid gap-3" @submit.prevent="submitImport">
                <label class="grid gap-1 text-sm">
                    <span>Upload file (`.xlsx` or `.csv`)</span>
                    <input
                        class="rounded-md border px-3 py-2"
                        type="file"
                        accept=".xlsx,.csv"
                        @change="onFileChange"
                    />
                    <span v-if="importForm.errors.source_file" class="text-xs text-red-600">{{ importForm.errors.source_file }}</span>
                </label>
                <div class="flex items-end">
                    <Button type="submit" :disabled="importForm.processing">Import File</Button>
                </div>
            </form>
        </section>
    </div>
</template>
