<script setup lang="ts">
import ContentGeneration from './ContentGeneration.vue';
import type { Project } from './types';

interface Props {
    project: Project;
}

defineProps<Props>();

const formatDate = (date: string | null): string => {
    return date ? date.slice(0, 10) : 'No due date';
};
</script>

<template>
    <div class="rounded-xl border p-5">
        <h2 class="text-lg font-medium">{{ project.title }}</h2>

        <div class="text-muted-foreground mt-3 space-y-1 text-sm">
            <p>Type: {{ project.content_type }}</p>
            <div class="mt-2">
                <span
                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                >
                    {{ project.status }}
                </span>
            </div>
            <p>Due date: {{ formatDate(project.due_date) }}</p>
        </div>

        <ContentGeneration
            :project-id="project.id"
            :initial-generation="project.latest_content_generation"
        />
    </div>
</template>
