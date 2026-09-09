<script setup lang="ts">
import ProjectCard from './ProjectCard.vue';
import type { Project } from './types';

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
                {{ projects.length }} project{{ projects.length === 1 ? '' : 's' }}
            </p>
        </div>

        <div
            v-if="projects.length === 0"
            class="rounded-xl border p-8 text-center"
        >
            <h2 class="text-lg font-medium">No projects yet</h2>
            <p class="text-muted-foreground mt-2 text-sm">
                You don't have any projects yet. Projects assigned to your
                account will appear here when they are available.
            </p>
        </div>

        <div v-else class="grid gap-4">
            <ProjectCard
                v-for="project in projects"
                :key="project.id"
                :project="project"
            />
        </div>
    </div>
</template>
