<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import {
    isGenerationRequestInFlight,
    runGenerationRequest,
} from './generationRequestGuard';
import type {
    ContentPlan,
    GenerationResponse,
    ProjectGeneration,
} from './types';

interface Props {
    projectId: number;
    initialGeneration: ProjectGeneration | null;
}

interface ErrorResponse {
    message?: string;
}

const props = defineProps<Props>();
const generation = ref<ProjectGeneration | null>(props.initialGeneration);
const error = ref('');
let pollingTimer: ReturnType<typeof window.setInterval> | undefined;
let pollingInFlight = false;

const isActive = (value: ProjectGeneration | null): boolean => {
    return value?.status === 'pending' || value?.status === 'processing';
};

const statusLabel = (status: ProjectGeneration['status']): string => {
    return {
        pending: 'Waiting to start',
        processing: 'Generating',
        completed: 'Completed',
        failed: 'Failed',
    }[status];
};

const statusMessage = (value: ProjectGeneration): string => {
    if (value.status === 'pending') {
        return 'Your request is queued and waiting for a worker.';
    }

    if (value.status === 'processing') {
        return 'A background worker is generating your content plan.';
    }

    if (value.status === 'failed') {
        return (
            value.error_message ??
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

    const valueRecord = value as Record<string, unknown>;

    return (
        typeof valueRecord.id === 'number' &&
        ['pending', 'processing', 'completed', 'failed'].includes(
            valueRecord.status as string,
        ) &&
        (valueRecord.response === null ||
            isValidContentPlan(valueRecord.response)) &&
        (typeof valueRecord.model === 'string' || valueRecord.model === null) &&
        (typeof valueRecord.input_tokens === 'number' ||
            valueRecord.input_tokens === null) &&
        (typeof valueRecord.output_tokens === 'number' ||
            valueRecord.output_tokens === null) &&
        (typeof valueRecord.error_code === 'string' ||
            valueRecord.error_code === null) &&
        (typeof valueRecord.error_message === 'string' ||
            valueRecord.error_message === null)
    );
};

const stopPolling = (): void => {
    if (pollingTimer !== undefined) {
        window.clearInterval(pollingTimer);
        pollingTimer = undefined;
    }
};

const fetchGenerationStatus = async (): Promise<void> => {
    if (generation.value === null || pollingInFlight) {
        return;
    }

    pollingInFlight = true;

    try {
        const response = await fetch(
            `/projects/${props.projectId}/generations/${generation.value.id}`,
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

        generation.value = (data as GenerationResponse).generation;
        error.value = '';

        if (!isActive(generation.value)) {
            stopPolling();
        }
    } catch {
        error.value =
            'Status could not be checked. Your generation has not been restarted.';
    } finally {
        pollingInFlight = false;
    }
};

const startPolling = (): void => {
    stopPolling();

    pollingTimer = window.setInterval(() => {
        void fetchGenerationStatus();
    }, 2000);
};

const generateContentPlan = async (): Promise<void> => {
    if (
        isActive(generation.value) ||
        isGenerationRequestInFlight(props.projectId)
    ) {
        return;
    }

    error.value = '';

    await runGenerationRequest(props.projectId, async () => {
        try {
            const response = await fetch(
                `/projects/${props.projectId}/generations`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') ?? '',
                    },
                },
            );

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

            generation.value = (data as GenerationResponse).generation;

            if (isActive(generation.value)) {
                startPolling();
            }
        } catch (requestError) {
            error.value =
                requestError instanceof Error
                    ? requestError.message
                    : 'The content plan could not be queued. Please try again later.';
        }
    });
};

onMounted(() => {
    if (isActive(generation.value)) {
        startPolling();
    }
});

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <div class="mt-4">
        <button
            type="button"
            class="hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="
                isActive(generation) ||
                isGenerationRequestInFlight(props.projectId)
            "
            @click="generateContentPlan"
        >
            {{
                generation?.status === 'processing'
                    ? 'Generating...'
                    : generation?.status === 'pending'
                      ? 'Waiting to start...'
                      : 'Generate Content Plan'
            }}
        </button>

        <div v-if="generation" class="mt-5 rounded-lg border p-4">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm font-medium">
                    {{ statusLabel(generation.status) }}
                </p>
                <span class="rounded-full border px-2.5 py-1 text-xs">
                    {{ generation.status }}
                </span>
            </div>

            <p class="text-muted-foreground mt-2 text-sm">
                {{ statusMessage(generation) }}
            </p>

            <p v-if="error" class="text-destructive mt-3 text-sm">
                {{ error }}
            </p>
        </div>

        <div
            v-if="generation?.status === 'completed' && generation.response"
            class="mt-6 space-y-5 rounded-lg border p-5"
        >
            <div>
                <p class="text-muted-foreground text-xs font-medium">
                    SUGGESTED TITLE
                </p>
                <h3 class="mt-1 text-lg font-semibold">
                    {{ generation.response.suggested_title }}
                </h3>
            </div>

            <div>
                <p class="text-sm font-medium">Content Brief</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{ generation.response.content_brief }}
                </p>
            </div>

            <div>
                <p class="text-sm font-medium">Outline</p>
                <div class="mt-2 space-y-3">
                    <div
                        v-for="item in generation.response.outline"
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
                    <li v-for="point in generation.response.key_points" :key="point">
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
                        v-for="task in generation.response.production_tasks"
                        :key="task"
                    >
                        {{ task }}
                    </li>
                </ul>
            </div>

            <div>
                <p class="text-sm font-medium">Risks / Missing Information</p>
                <ul
                    class="text-muted-foreground mt-2 list-disc space-y-1 pl-5 text-sm"
                >
                    <li
                        v-for="risk in generation.response.risks_or_missing_information"
                        :key="risk"
                    >
                        {{ risk }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
