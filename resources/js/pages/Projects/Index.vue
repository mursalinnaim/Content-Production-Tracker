<script setup lang="ts">
interface Project {
    id: number;
    title: string;
    content_type: string;
    status: string;
    due_date: string | null;
}
const formatDate = (date: string | null): string => {
    return date ? date.slice(0, 10) : 'No due date';
};
interface Props {
    projects: Project[];
}

defineProps<Props>();
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
        <div>
            <h1 class="text-2xl font-semibold">Projects</h1>
            <p class="text-muted-foreground">
                {{ projects.length }} project{{
                    projects.length === 1 ? '' : 's'
                }}
            </p>
        </div>

        <div
            v-if="projects.length === 0"
            class="rounded-xl border p-8 text-center"
        >
            <h2 class="text-lg font-medium">No projects yet</h2>
            <p class="text-muted-foreground mt-2 text-sm">
                You don't have any projects yet.
            </p>
        </div>

        <div v-else class="grid gap-4">
            <div
                v-for="project in projects"
                :key="project.id"
                class="rounded-xl border p-5"
            >
                <h2 class="text-lg font-medium">
                    {{ project.title }}
                </h2>

                <div class="text-muted-foreground mt-3 space-y-1 text-sm">
                    <p>Type: {{ project.content_type }}</p>
                    <div class="mt-2">
                        <span
                            class="rounded-full border px-2.5 py-1 text-xs font-medium"
                        >
                            {{ project.status }}
                        </span>
                    </div>
                    <p>
                        Due date:
                        {{ formatDate(project.due_date) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
