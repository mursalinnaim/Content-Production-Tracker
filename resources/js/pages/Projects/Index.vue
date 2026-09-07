<script setup lang="ts">
import { ref } from 'vue';

interface Project {
    id: number;
    title: string;
    content_type: string;
    status: string;
    due_date: string | null;
}

interface OutlineItem {
    heading: string;
    purpose: string;
}

interface ContentPlan {
    suggested_title: string;
    content_brief: string;
    outline: OutlineItem[];
    key_points: string[];
    production_tasks: string[];
    risks_or_missing_information: string[];
}

interface Generation {
    id: number;
    status: string;
    response: ContentPlan;
}

interface Props {
    projects: Project[];
}

defineProps<Props>();

const generatingProjectId = ref<number | null>(null);
const generations = ref<Record<number, Generation>>({});
const errors = ref<Record<number, string>>({});

const formatDate = (date: string | null): string => {
    return date ? date.slice(0, 10) : 'No due date';
};

const getErrorMessage = (data: unknown): string => {
    if (
        data &&
        typeof data === 'object' &&
        'message' in data &&
        data.message ===
            'The content plan could not be generated. Please try again later.'
    ) {
        return data.message;
    }

    return 'The content plan could not be generated. Please try again later.';
};

const isValidContentPlan = (value: unknown): value is ContentPlan => {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const plan = value as Record<string, unknown>;

    return (
        typeof plan.suggested_title === 'string' &&
        typeof plan.content_brief === 'string' &&
        Array.isArray(plan.outline) &&
        plan.outline.every(
            (item) =>
                item &&
                typeof item === 'object' &&
                typeof (item as Record<string, unknown>).heading === 'string' &&
                typeof (item as Record<string, unknown>).purpose === 'string',
        ) &&
        Array.isArray(plan.key_points) &&
        plan.key_points.every((item) => typeof item === 'string') &&
        Array.isArray(plan.production_tasks) &&
        plan.production_tasks.every((item) => typeof item === 'string') &&
        Array.isArray(plan.risks_or_missing_information) &&
        plan.risks_or_missing_information.every(
            (item) => typeof item === 'string',
        )
    );
};

const generateContentPlan = async (projectId: number): Promise<void> => {
    delete generations.value[projectId];
    generatingProjectId.value = projectId;
    errors.value[projectId] = '';

    try {
        const response = await fetch(`/projects/${projectId}/generations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
        });

        const data: unknown = await response.json();

        if (!response.ok) {
            throw new Error(getErrorMessage(data));
        }

        if (
            !data ||
            typeof data !== 'object' ||
            !('generation' in data) ||
            !data.generation ||
            typeof data.generation !== 'object'
        ) {
            throw new Error(
                'The content plan could not be generated. Please try again later.',
            );
        }

        const generation = data.generation as Record<string, unknown>;

        if (
            typeof generation.id !== 'number' ||
            typeof generation.status !== 'string' ||
            !isValidContentPlan(generation.response)
        ) {
            throw new Error('Invalid generation response.');
        }

        generations.value[projectId] = {
            id: generation.id,
            status: generation.status,
            response: generation.response,
        };
    } catch {
        errors.value[projectId] =
            'The content plan could not be generated. Please try again later.';
    } finally {
        generatingProjectId.value = null;
    }
};
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
                You don't have any projects yet. Projects assigned to your
                account will appear here when they are available.
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

                <button
                    type="button"
                    class="hover:bg-muted mt-4 rounded-lg border px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="generatingProjectId === project.id"
                    @click="generateContentPlan(project.id)"
                >
                    {{
                        generatingProjectId === project.id
                            ? 'Generating...'
                            : 'Generate Content Plan'
                    }}
                </button>

                <p
                    v-if="errors[project.id]"
                    class="text-destructive mt-3 text-sm"
                >
                    {{ errors[project.id] }}
                </p>

                <div
                    v-if="generations[project.id]"
                    class="mt-6 space-y-5 rounded-lg border p-5"
                >
                    <div>
                        <p class="text-muted-foreground text-xs font-medium">
                            SUGGESTED TITLE
                        </p>
                        <h3 class="mt-1 text-lg font-semibold">
                            {{
                                generations[project.id].response.suggested_title
                            }}
                        </h3>
                    </div>

                    <div>
                        <p class="text-sm font-medium">Content Brief</p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ generations[project.id].response.content_brief }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-medium">Outline</p>

                        <div class="mt-2 space-y-3">
                            <div
                                v-for="item in generations[project.id].response
                                    .outline"
                                :key="item.heading"
                                class="bg-muted/50 rounded-md p-3"
                            >
                                <p class="font-medium">
                                    {{ item.heading }}
                                </p>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    {{ item.purpose }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-medium">Key Points</p>
                        <ul
                            class="text-muted-foreground mt-2 list-disc space-y-1 pl-5 text-sm"
                        >
                            <li
                                v-for="point in generations[project.id].response
                                    .key_points"
                                :key="point"
                            >
                                {{ point }}
                            </li>
                        </ul>
                    </div>

                    <div>
                        <p class="text-sm font-medium">Production Tasks</p>
                        <ul
                            class="text-muted-foreground mt-2 list-disc space-y-1 pl-5 text-sm"
                        >
                            <li
                                v-for="task in generations[project.id].response
                                    .production_tasks"
                                :key="task"
                            >
                                {{ task }}
                            </li>
                        </ul>
                    </div>

                    <div>
                        <p class="text-sm font-medium">
                            Risks / Missing Information
                        </p>

                        <ul
                            class="text-muted-foreground mt-2 list-disc space-y-1 pl-5 text-sm"
                        >
                            <li
                                v-for="risk in generations[project.id].response
                                    .risks_or_missing_information"
                                :key="risk"
                            >
                                {{ risk }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
