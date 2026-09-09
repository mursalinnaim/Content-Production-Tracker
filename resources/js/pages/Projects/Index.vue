<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import {
    isGenerationRequestInFlight,
    runGenerationRequest,
} from './generationRequestGuard';

type GenerationStatus = 'pending' | 'processing' | 'completed' | 'failed';

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

interface ProjectGeneration {
    id: number;
    status: GenerationStatus;
    response: ContentPlan | null;
    model: string | null;
    input_tokens: number | null;
    output_tokens: number | null;
    error_code: string | null;
    error_message: string | null;
    processing_started_at: string | null;
    completed_at: string | null;
}

interface Project {
    id: number;
    title: string;
    content_type: string;
    status: string;
    due_date: string | null;
    latest_content_generation: ProjectGeneration | null;
}

interface GenerationResponse {
    generation: ProjectGeneration;
}

interface ErrorResponse {
    message?: string;
}

interface Props {
    projects: Project[];
}

const props = defineProps<Props>();
const generations = ref<Record<number, ProjectGeneration>>({});
const errors = ref<Record<number, string>>({});
const pollingTimers = new Map<number, ReturnType<typeof window.setInterval>>();
const pollingInFlight = new Set<number>();

const formatDate = (date: string | null): string => {
    return date ? date.slice(0, 10) : 'No due date';
};

const isActive = (generation: ProjectGeneration | undefined): boolean => {
    return (
        generation?.status === 'pending' || generation?.status === 'processing'
    );
};

const statusLabel = (status: GenerationStatus): string => {
    return {
        pending: 'Waiting to start',
        processing: 'Generating',
        completed: 'Completed',
        failed: 'Failed',
    }[status];
};

const statusMessage = (generation: ProjectGeneration): string => {
    if (generation.status === 'pending') {
        return 'Your request is queued and waiting for a worker.';
    }

    if (generation.status === 'processing') {
        return 'A background worker is generating your content plan.';
    }

    if (generation.status === 'failed') {
        return (
            generation.error_message ??
            'The content plan could not be generated. Please try again later.'
        );
    }

    return 'The content plan is ready.';
};

const getErrorMessage = (data: unknown): string => {
    if (
        data &&
        typeof data === 'object' &&
        'message' in data &&
        typeof (data as ErrorResponse).message === 'string'
    ) {
        return (data as ErrorResponse).message as string;
    }

    return 'The content plan could not be queued. Please try again later.';
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

const isValidGeneration = (value: unknown): value is ProjectGeneration => {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const generation = value as Record<string, unknown>;

    return (
        typeof generation.id === 'number' &&
        ['pending', 'processing', 'completed', 'failed'].includes(
            generation.status as string,
        ) &&
        (generation.response === null ||
            isValidContentPlan(generation.response)) &&
        (typeof generation.model === 'string' || generation.model === null) &&
        (typeof generation.input_tokens === 'number' ||
            generation.input_tokens === null) &&
        (typeof generation.output_tokens === 'number' ||
            generation.output_tokens === null) &&
        (typeof generation.error_code === 'string' ||
            generation.error_code === null) &&
        (typeof generation.error_message === 'string' ||
            generation.error_message === null)
    );
};

const stopPolling = (projectId: number): void => {
    const timer = pollingTimers.get(projectId);

    if (timer !== undefined) {
        window.clearInterval(timer);
        pollingTimers.delete(projectId);
    }
};

const stopAllPolling = (): void => {
    pollingTimers.forEach((timer) => window.clearInterval(timer));
    pollingTimers.clear();
};

const fetchGenerationStatus = async (
    projectId: number,
    generationId: number,
): Promise<void> => {
    if (pollingInFlight.has(projectId)) {
        return;
    }

    pollingInFlight.add(projectId);

    try {
        const response = await fetch(
            `/projects/${projectId}/generations/${generationId}`,
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('Status request failed.');
        }

        const data: unknown = await response.json();

        if (
            !data ||
            typeof data !== 'object' ||
            !('generation' in data) ||
            !isValidGeneration((data as GenerationResponse).generation)
        ) {
            throw new Error('Invalid status response.');
        }

        const generation = (data as GenerationResponse).generation;
        generations.value[projectId] = generation;
        errors.value[projectId] = '';

        if (!isActive(generation)) {
            stopPolling(projectId);
        }
    } catch {
        errors.value[projectId] =
            'Status could not be checked. Your generation has not been restarted.';
    } finally {
        pollingInFlight.delete(projectId);
    }
};

const startPolling = (projectId: number, generationId: number): void => {
    stopPolling(projectId);

    const timer = window.setInterval(() => {
        void fetchGenerationStatus(projectId, generationId);
    }, 2000);

    pollingTimers.set(projectId, timer);
};

const generateContentPlan = async (projectId: number): Promise<void> => {
    const existing = generations.value[projectId];

    if (isActive(existing) || isGenerationRequestInFlight(projectId)) {
        return;
    }

    errors.value[projectId] = '';

    await runGenerationRequest(projectId, async () => {
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
                !isValidGeneration((data as GenerationResponse).generation)
            ) {
                throw new Error('Invalid generation response.');
            }

            const generation = (data as GenerationResponse).generation;
            generations.value[projectId] = generation;

            if (isActive(generation)) {
                startPolling(projectId, generation.id);
            }
        } catch (error) {
            errors.value[projectId] =
                error instanceof Error
                    ? error.message
                    : 'The content plan could not be queued. Please try again later.';
        }
    });
};

onMounted(() => {
    props.projects.forEach((project) => {
        if (project.latest_content_generation !== null) {
            generations.value[project.id] = project.latest_content_generation;

            if (isActive(project.latest_content_generation)) {
                startPolling(project.id, project.latest_content_generation.id);
            }
        }
    });
});

onBeforeUnmount(() => {
    stopAllPolling();
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
        <div>
            <h1 class="text-2xl font-semibold">Projects</h1>
            <p class="text-muted-foreground">
                {{ props.projects.length }} project{{
                    props.projects.length === 1 ? '' : 's'
                }}
            </p>
        </div>

        <div
            v-if="props.projects.length === 0"
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
                v-for="project in props.projects"
                :key="project.id"
                class="rounded-xl border p-5"
            >
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

                <button
                    type="button"
                    class="hover:bg-muted mt-4 rounded-lg border px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="
                        isActive(generations[project.id]) ||
                        isGenerationRequestInFlight(project.id)
                    "
                    @click="generateContentPlan(project.id)"
                >
                    {{
                        generations[project.id]?.status === 'processing'
                            ? 'Generating...'
                            : generations[project.id]?.status === 'pending'
                              ? 'Waiting to start...'
                              : 'Generate Content Plan'
                    }}
                </button>

                <div
                    v-if="generations[project.id]"
                    class="mt-5 rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm font-medium">
                            {{ statusLabel(generations[project.id].status) }}
                        </p>
                        <span class="rounded-full border px-2.5 py-1 text-xs">
                            {{ generations[project.id].status }}
                        </span>
                    </div>

                    <p class="text-muted-foreground mt-2 text-sm">
                        {{ statusMessage(generations[project.id]) }}
                    </p>

                    <p
                        v-if="errors[project.id]"
                        class="text-destructive mt-3 text-sm"
                    >
                        {{ errors[project.id] }}
                    </p>
                </div>

                <div
                    v-if="
                        generations[project.id]?.status === 'completed' &&
                        generations[project.id]?.response
                    "
                    class="mt-6 space-y-5 rounded-lg border p-5"
                >
                    <div>
                        <p class="text-muted-foreground text-xs font-medium">
                            SUGGESTED TITLE
                        </p>
                        <h3 class="mt-1 text-lg font-semibold">
                            {{
                                generations[project.id]?.response
                                    ?.suggested_title
                            }}
                        </h3>
                    </div>

                    <div>
                        <p class="text-sm font-medium">Content Brief</p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{
                                generations[project.id]?.response?.content_brief
                            }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-medium">Outline</p>
                        <div class="mt-2 space-y-3">
                            <div
                                v-for="item in generations[project.id]?.response
                                    ?.outline ?? []"
                                :key="item.heading"
                                class="bg-muted/50 rounded-md p-3"
                            >
                                <p class="font-medium">{{ item.heading }}</p>
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
                                v-for="point in generations[project.id]
                                    ?.response?.key_points ?? []"
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
                                v-for="task in generations[project.id]?.response
                                    ?.production_tasks ?? []"
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
                                v-for="risk in generations[project.id]?.response
                                    ?.risks_or_missing_information ?? []"
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
