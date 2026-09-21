<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import {
    isGenerationRequestInFlight,
    runGenerationRequest,
} from './generationRequestGuard';

import type {
    AcceptedContentPlan,
    AcceptedContentPlanResponse,
    AcceptedContentPlanResult,
    ContentPlan,
    GenerationResponse,
    ProjectGeneration,
    GenerationHistoryItem,
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
const generationRequestPending = ref(false);
const generationMenuOpen = ref(false);
const generations = ref<GenerationHistoryItem[]>([]);
const selectedGenerationId = ref<number | null>(
    props.initialGeneration?.id ?? null,
);
const selectedGenerationDetail = ref<ProjectGeneration | null>(
    props.initialGeneration,
);
const historyLoading = ref(false);
const historyError = ref<string | null>(null);
const acceptedContentPlan = ref<AcceptedContentPlan | null>(null);
const acceptanceLoading = ref(false);
const acceptanceError = ref<string | null>(null);
const regenerationInstructions = ref('');
const regenerationLoading = ref(false);
const regenerationError = ref<string | null>(null);
const regenerationNotice = ref<string | null>(null);
let pollingTimer: ReturnType<typeof window.setInterval> | undefined;
let pollingInFlight = false;
let disposed = false;

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
        typeof valueRecord.generation_number === 'number' &&
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
    if (disposed || generation.value === null || pollingInFlight) {
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

        const updatedGeneration = (data as GenerationResponse).generation;
        generation.value = updatedGeneration;
        if (
            selectedGenerationId.value === updatedGeneration.id &&
            !isEditing.value
        ) {
            selectedGenerationDetail.value = updatedGeneration;
        }
        updateGenerationInHistory(updatedGeneration);
        error.value = '';

        if (!isActive(updatedGeneration)) {
            stopPolling();
            void loadGenerationHistory();
        }
    } catch {
        error.value =
            'Status could not be checked. Your generation has not been restarted.';
    } finally {
        pollingInFlight = false;
    }
};

const startPolling = (): void => {
    if (disposed) {
        return;
    }
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
    generationRequestPending.value = true;

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

            if (disposed) {
                return;
            }

            generation.value = (data as GenerationResponse).generation;
            selectedGenerationId.value = generation.value.id;
            selectedGenerationDetail.value = generation.value;
            generationMenuOpen.value = true;

            if (isActive(generation.value)) {
                startPolling();
            }
        } catch (requestError) {
            if (disposed) {
                return;
            }

            error.value =
                requestError instanceof Error
                    ? requestError.message
                    : 'The content plan could not be queued. Please try again later.';
        }
    });

    generationRequestPending.value = false;
};

const loadAcceptedContentPlan = async (): Promise<void> => {
    try {
        const response = await fetch(
            `/projects/${props.projectId}/accepted-plan`,
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('Unable to load the accepted content plan.');
        }

        const data = (await response.json()) as AcceptedContentPlanResponse;
        acceptedContentPlan.value = data.accepted_content_plan;
    } catch {
        acceptanceError.value = 'Unable to load the accepted content plan.';
    }
};

const loadGenerationHistory = async (): Promise<void> => {
    historyLoading.value = true;
    historyError.value = null;

    try {
        const response = await fetch(
            `/projects/${props.projectId}/generations`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Unable to load content generation history.');
        }

        const data = (await response.json()) as {
            generations: GenerationHistoryItem[];
        };

        generations.value = data.generations;

        if (
            selectedGenerationId.value === null &&
            data.generations.length > 0
        ) {
            selectedGenerationId.value = data.generations[0].id;

            if (generation.value?.id === data.generations[0].id) {
                selectedGenerationDetail.value = generation.value;
            } else {
                await loadGenerationDetail(data.generations[0].id);
            }
        }
    } catch {
        historyError.value = 'Unable to load content generation history.';
    } finally {
        historyLoading.value = false;
    }
};

const selectedGeneration = computed<ProjectGeneration | null>(() => {
    return selectedGenerationId.value === null
        ? null
        : selectedGenerationDetail.value;
});

const displayedGeneration = computed<ProjectGeneration | null>(() => {
    return selectedGeneration.value ?? generation.value;
});

const loadGenerationDetail = async (id: number): Promise<void> => {
    try {
        const response = await fetch(
            `/projects/${props.projectId}/generations/${id}`,
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('Unable to load the selected generation.');
        }

        const data: unknown = await response.json();

        if (
            !data ||
            typeof data !== 'object' ||
            !('generation' in data) ||
            !isValidGeneration((data as GenerationResponse).generation)
        ) {
            throw new Error('Invalid generation response.');
        }

        if (selectedGenerationId.value === id) {
            selectedGenerationDetail.value = (
                data as GenerationResponse
            ).generation;
        }
    } catch {
        historyError.value = 'Unable to load the selected generation.';
    }
};

const isEditing = ref(false);
const draft = ref<ContentPlan | null>(null);
const draftSaving = ref(false);
const draftError = ref<string | null>(null);

const cloneContentPlan = (value: ContentPlan): ContentPlan => {
    return JSON.parse(JSON.stringify(value)) as ContentPlan;
};

const savedContentPlan = computed<ContentPlan | null>(() => {
    const value = displayedGeneration.value;

    if (!value || value.status !== 'completed') {
        return null;
    }

    return value.draft ?? value.response;
});

const hasUnsavedChanges = computed(() => {
    if (!isEditing.value || draft.value === null) {
        return false;
    }

    return (
        JSON.stringify(draft.value) !== JSON.stringify(savedContentPlan.value)
    );
});

const isAcceptedVersion = computed(() => {
    if (
        acceptedContentPlan.value === null ||
        displayedGeneration.value === null ||
        savedContentPlan.value === null
    ) {
        return false;
    }

    return (
        acceptedContentPlan.value.source_generation_id ===
            displayedGeneration.value.id &&
        JSON.stringify(acceptedContentPlan.value.content) ===
            JSON.stringify(savedContentPlan.value)
    );
});

const getReviewGeneration = (): ProjectGeneration | null => {
    return displayedGeneration.value ?? generation.value;
};

const startEditing = (): void => {
    const value = getReviewGeneration();

    if (value?.status !== 'completed') {
        return;
    }

    const content = value.draft ?? value.response;

    if (content === null) {
        return;
    }

    draft.value = cloneContentPlan(content);
    draftError.value = null;
    isEditing.value = true;
};

const cancelEditing = (): void => {
    isEditing.value = false;
    draft.value = null;
    draftError.value = null;
};

const selectGeneration = (id: number): void => {
    if (id === selectedGenerationId.value) {
        return;
    }

    if (
        isEditing.value &&
        hasUnsavedChanges.value &&
        !window.confirm(
            'You have unsaved edits. Discard them and switch versions?',
        )
    ) {
        return;
    }

    cancelEditing();
    selectedGenerationId.value = id;
    selectedGenerationDetail.value =
        generation.value?.id === id ? generation.value : null;

    if (selectedGenerationDetail.value === null) {
        void loadGenerationDetail(id);
    }
};

const formatHistoryDate = (value: string | null): string => {
    if (value === null) {
        return 'Time not available';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Time not available';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

const updateGenerationInHistory = (
    updatedGeneration: ProjectGeneration,
): void => {
    const updatedItem: GenerationHistoryItem = {
        id: updatedGeneration.id,
        generation_number: updatedGeneration.generation_number,
        status: updatedGeneration.status,
        source_generation_id: updatedGeneration.source_generation_id,
        has_draft: updatedGeneration.draft !== null,
        is_accepted:
            acceptedContentPlan.value?.source_generation_id ===
            updatedGeneration.id,
        regeneration_instructions: updatedGeneration.regeneration_instructions,
        processing_started_at: updatedGeneration.processing_started_at,
        completed_at: updatedGeneration.completed_at,
    };

    if (!generations.value.some((item) => item.id === updatedGeneration.id)) {
        generations.value = [updatedItem, ...generations.value];
        return;
    }

    generations.value = generations.value.map((item) =>
        item.id === updatedGeneration.id ? { ...item, ...updatedItem } : item,
    );
};

const saveDraft = async (): Promise<void> => {
    const value = displayedGeneration.value;

    if (
        value?.status !== 'completed' ||
        draft.value === null ||
        draftSaving.value
    ) {
        return;
    }

    draftSaving.value = true;
    draftError.value = null;

    try {
        const response = await fetch(
            `/projects/${props.projectId}/generations/${value.id}/draft`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ draft: draft.value }),
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
            throw new Error('Invalid draft response.');
        }

        const updatedGeneration = (data as GenerationResponse).generation;
        updateGenerationInHistory(updatedGeneration);

        if (generation.value?.id === updatedGeneration.id) {
            generation.value = updatedGeneration;
        }

        if (selectedGenerationId.value === updatedGeneration.id) {
            selectedGenerationDetail.value = updatedGeneration;
        }

        if (updatedGeneration.draft !== null) {
            draft.value = cloneContentPlan(updatedGeneration.draft);
        }

        isEditing.value = false;
    } catch (saveError) {
        draftError.value =
            saveError instanceof Error
                ? saveError.message
                : 'The draft could not be saved. Please try again.';
    } finally {
        draftSaving.value = false;
    }
};

const acceptGeneration = async (): Promise<void> => {
    const value = getReviewGeneration();
    const content = value?.draft ?? value?.response ?? null;

    if (
        value?.status !== 'completed' ||
        content === null ||
        isEditing.value ||
        acceptanceLoading.value
    ) {
        return;
    }

    acceptanceLoading.value = true;
    acceptanceError.value = null;

    try {
        const response = await fetch(
            `/projects/${props.projectId}/generations/${value.id}/accept`,
            {
                method: 'POST',
                headers: {
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
            !('accepted_content_plan' in data)
        ) {
            throw new Error('Invalid acceptance response.');
        }

        acceptedContentPlan.value = (
            data as AcceptedContentPlanResult
        ).accepted_content_plan;
    } catch (acceptError) {
        acceptanceError.value =
            acceptError instanceof Error
                ? acceptError.message
                : 'The content plan could not be accepted. Please try again.';
    } finally {
        acceptanceLoading.value = false;
    }
};

const regenerateContentPlan = async (): Promise<void> => {
    const value = getReviewGeneration();
    const instructions = regenerationInstructions.value.trim();

    if (
        value?.status !== 'completed' ||
        (value.draft === null && value.response === null) ||
        isEditing.value ||
        instructions === '' ||
        regenerationLoading.value
    ) {
        return;
    }

    regenerationLoading.value = true;
    regenerationError.value = null;
    regenerationNotice.value = null;

    try {
        const response = await fetch(
            `/projects/${props.projectId}/generations/${value.id}/regenerate`,
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
                body: JSON.stringify({ instructions }),
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
            throw new Error('Invalid regeneration response.');
        }

        const regenerationResponse = data as GenerationResponse & {
            message?: string;
            regeneration_queued?: boolean;
        };
        const newGeneration = regenerationResponse.generation;
        updateGenerationInHistory(newGeneration);
        generation.value = newGeneration;
        selectedGenerationId.value = newGeneration.id;
        selectedGenerationDetail.value = newGeneration;
        generationMenuOpen.value = true;
        regenerationInstructions.value = '';

        if (
            regenerationResponse.regeneration_queued === false &&
            typeof regenerationResponse.message === 'string'
        ) {
            regenerationNotice.value = regenerationResponse.message;
        }

        if (isActive(newGeneration)) {
            startPolling();
        }
    } catch (regenError) {
        regenerationError.value =
            regenError instanceof Error
                ? regenError.message
                : 'The content plan could not be regenerated. Please try again.';
    } finally {
        regenerationLoading.value = false;
    }
};

const addOutlineItem = (): void => {
    draft.value?.outline.push({
        heading: '',
        purpose: '',
    });
};

const removeOutlineItem = (index: number): void => {
    draft.value?.outline.splice(index, 1);
};

const addListItem = (
    field: 'key_points' | 'production_tasks' | 'risks_or_missing_information',
): void => {
    draft.value?.[field].push('');
};

const removeListItem = (
    field: 'key_points' | 'production_tasks' | 'risks_or_missing_information',
    index: number,
): void => {
    draft.value?.[field].splice(index, 1);
};

const editableListFields = [
    'key_points',
    'production_tasks',
    'risks_or_missing_information',
] as const;

const listFieldLabel = (field: (typeof editableListFields)[number]): string => {
    return {
        key_points: 'Key Points',
        production_tasks: 'Production Tasks',
        risks_or_missing_information: 'Risks / Missing Information',
    }[field];
};

onMounted(async () => {
    await Promise.all([loadGenerationHistory(), loadAcceptedContentPlan()]);

    if (isActive(generation.value)) {
        startPolling();
    }
});

onBeforeUnmount(() => {
    disposed = true;
    stopPolling();
});
</script>

<template>
    <div class="mt-4">
        <template v-if="generation">
            <button
                type="button"
                class="hover:bg-muted flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left text-sm font-medium transition"
                :aria-expanded="generationMenuOpen"
                @click="generationMenuOpen = !generationMenuOpen"
            >
                <span>
                    Content Generation
                    <span class="text-muted-foreground ml-1 font-normal">
                        — Generation #{{ generation.generation_number }}
                    </span>
                </span>
                <span aria-hidden="true">{{
                    generationMenuOpen ? '−' : '+'
                }}</span>
            </button>

            <div v-if="generationMenuOpen" class="mt-3 space-y-5">
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

                <div v-if="historyLoading">Loading generation history...</div>

                <div v-else-if="historyError">
                    {{ historyError }}
                </div>

                <div v-else-if="generations.length > 0" class="mt-6">
                    <h3 class="text-sm font-semibold">Generation history</h3>

                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="item in generations"
                            :key="item.id"
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="{
                                'bg-muted': item.id === selectedGenerationId,
                            }"
                            :disabled="
                                item.status === 'pending' ||
                                item.status === 'processing'
                            "
                            @click="selectGeneration(item.id)"
                        >
                            <span class="block">
                                Generation #{{ item.generation_number }} —
                                {{ item.status }}
                            </span>
                            <span
                                class="text-muted-foreground mt-1 block text-xs"
                            >
                                {{
                                    formatHistoryDate(
                                        item.completed_at ??
                                            item.processing_started_at,
                                    )
                                }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="mt-6 rounded-lg border p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold">Accepted plan</p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                The final snapshot explicitly chosen for this
                                project.
                            </p>
                        </div>
                        <span
                            v-if="acceptedContentPlan"
                            class="rounded-full border px-2.5 py-1 text-xs"
                        >
                            {{
                                isAcceptedVersion
                                    ? 'Accepted version'
                                    : 'Accepted snapshot'
                            }}
                        </span>
                    </div>

                    <template v-if="acceptedContentPlan">
                        <div class="mt-4 space-y-4">
                            <div>
                                <p
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    SUGGESTED TITLE
                                </p>
                                <h3 class="mt-1 text-lg font-semibold">
                                    {{
                                        acceptedContentPlan.content
                                            .suggested_title
                                    }}
                                </h3>
                            </div>

                            <div>
                                <p class="text-sm font-medium">Content Brief</p>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    {{
                                        acceptedContentPlan.content
                                            .content_brief
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-sm font-medium">Outline</p>
                                <div class="mt-2 space-y-3">
                                    <div
                                        v-for="(
                                            item, index
                                        ) in acceptedContentPlan.content
                                            .outline"
                                        :key="index"
                                        class="bg-muted/50 rounded-md p-3"
                                    >
                                        <p class="font-medium">
                                            {{ item.heading }}
                                        </p>
                                        <p
                                            class="text-muted-foreground mt-1 text-sm"
                                        >
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
                                        v-for="(
                                            point, index
                                        ) in acceptedContentPlan.content
                                            .key_points"
                                        :key="index"
                                    >
                                        {{ point }}
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <p class="text-sm font-medium">
                                    Production Tasks
                                </p>
                                <ul
                                    class="text-muted-foreground mt-2 list-disc space-y-1 pl-5 text-sm"
                                >
                                    <li
                                        v-for="(
                                            task, index
                                        ) in acceptedContentPlan.content
                                            .production_tasks"
                                        :key="index"
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
                                        v-for="(
                                            risk, index
                                        ) in acceptedContentPlan.content
                                            .risks_or_missing_information"
                                        :key="index"
                                    >
                                        {{ risk }}
                                    </li>
                                </ul>
                            </div>

                            <p
                                class="text-muted-foreground border-t pt-3 text-xs"
                            >
                                Accepted from Generation #{{
                                    generations.find(
                                        (item) =>
                                            item.id ===
                                            acceptedContentPlan?.source_generation_id,
                                    )?.generation_number ??
                                    acceptedContentPlan?.source_generation_id
                                }}
                                on
                                {{
                                    new Date(
                                        acceptedContentPlan.accepted_at,
                                    ).toLocaleString()
                                }}
                            </p>
                        </div>
                    </template>

                    <p v-else class="text-muted-foreground mt-4 text-sm">
                        No plan has been accepted yet.
                    </p>
                </div>

                <div
                    v-if="displayedGeneration?.status === 'failed'"
                    class="mt-6 rounded-lg border p-5"
                >
                    <p class="text-sm font-medium">Generation failed</p>
                    <p class="text-muted-foreground mt-2 text-sm">
                        {{ statusMessage(displayedGeneration) }}
                    </p>
                </div>

                <div
                    v-if="
                        displayedGeneration?.status === 'completed' &&
                        savedContentPlan
                    "
                    class="mt-6 space-y-5 rounded-lg border p-5"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p
                                class="text-muted-foreground text-xs font-medium"
                            >
                                CONTENT PLAN
                            </p>
                            <p
                                v-if="displayedGeneration.draft"
                                class="text-muted-foreground mt-1 text-xs"
                            >
                                Saved draft
                            </p>
                        </div>

                        <button
                            v-if="!isEditing"
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm font-medium"
                            @click="startEditing()"
                        >
                            Edit
                        </button>

                        <div v-else class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm"
                                :disabled="draftSaving"
                                @click="cancelEditing"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm font-medium disabled:opacity-50"
                                :disabled="draftSaving || !hasUnsavedChanges"
                                @click="saveDraft"
                            >
                                {{ draftSaving ? 'Saving...' : 'Save draft' }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="
                            acceptedContentPlan?.source_generation_id ===
                            displayedGeneration.id
                        "
                        class="rounded-md border px-3 py-2 text-sm"
                    >
                        Accepted plan
                        <span class="text-muted-foreground">
                            —
                            {{
                                new Date(
                                    acceptedContentPlan.accepted_at,
                                ).toLocaleString()
                            }}
                        </span>
                    </div>

                    <p v-if="acceptanceError" class="text-destructive text-sm">
                        {{ acceptanceError }}
                    </p>

                    <div
                        v-if="
                            !isEditing &&
                            displayedGeneration.status === 'completed'
                        "
                        class="space-y-3 border-t pt-4"
                    >
                        <button
                            v-if="!isAcceptedVersion"
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm font-medium disabled:opacity-50"
                            :disabled="acceptanceLoading"
                            @click="acceptGeneration()"
                        >
                            {{
                                acceptanceLoading
                                    ? 'Accepting...'
                                    : 'Accept this plan'
                            }}
                        </button>
                        <p v-else class="text-sm font-medium">Accepted</p>

                        <div class="space-y-2">
                            <label
                                class="text-sm font-medium"
                                :for="`regenerate-${displayedGeneration.id}`"
                            >
                                Regenerate with instructions
                            </label>
                            <textarea
                                :id="`regenerate-${displayedGeneration.id}`"
                                v-model="regenerationInstructions"
                                rows="3"
                                placeholder="Tell the AI what you want changed..."
                                class="w-full rounded-md border px-3 py-2 text-sm"
                            />
                            <button
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                                :disabled="
                                    regenerationLoading ||
                                    regenerationInstructions.trim() === ''
                                "
                                @click="regenerateContentPlan()"
                            >
                                {{
                                    regenerationLoading
                                        ? 'Regenerating...'
                                        : 'Regenerate'
                                }}
                            </button>
                            <p
                                v-if="regenerationError"
                                class="text-destructive text-sm"
                            >
                                {{ regenerationError }}
                            </p>
                            <p
                                v-if="regenerationNotice"
                                class="text-muted-foreground text-sm"
                            >
                                {{ regenerationNotice }}
                            </p>
                        </div>
                    </div>

                    <p v-if="draftError" class="text-destructive text-sm">
                        {{ draftError }}
                    </p>

                    <template v-if="isEditing && draft">
                        <div>
                            <label
                                class="text-sm font-medium"
                                for="content-plan-title"
                            >
                                Suggested Title
                            </label>
                            <input
                                id="content-plan-title"
                                v-model="draft.suggested_title"
                                type="text"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                            />
                        </div>

                        <div>
                            <label
                                class="text-sm font-medium"
                                for="content-plan-brief"
                            >
                                Content Brief
                            </label>
                            <textarea
                                id="content-plan-brief"
                                v-model="draft.content_brief"
                                rows="4"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                            />
                        </div>

                        <div>
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <p class="text-sm font-medium">Outline</p>
                                <button
                                    type="button"
                                    class="rounded-md border px-2 py-1 text-xs"
                                    @click="addOutlineItem"
                                >
                                    Add section
                                </button>
                            </div>

                            <div class="mt-2 space-y-3">
                                <div
                                    v-for="(item, index) in draft.outline"
                                    :key="index"
                                    class="space-y-2 rounded-md border p-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <p class="text-xs font-medium">
                                            Section {{ index + 1 }}
                                        </p>
                                        <button
                                            type="button"
                                            class="text-destructive text-xs"
                                            @click="removeOutlineItem(index)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                    <input
                                        v-model="item.heading"
                                        type="text"
                                        placeholder="Heading"
                                        class="w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                    <textarea
                                        v-model="item.purpose"
                                        rows="2"
                                        placeholder="Purpose"
                                        class="w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-for="field in editableListFields" :key="field">
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <p class="text-sm font-medium">
                                    {{ listFieldLabel(field) }}
                                </p>
                                <button
                                    type="button"
                                    class="rounded-md border px-2 py-1 text-xs"
                                    @click="addListItem(field)"
                                >
                                    Add item
                                </button>
                            </div>

                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="(item, index) in draft[field]"
                                    :key="index"
                                    class="flex gap-2"
                                >
                                    <textarea
                                        v-model="draft[field][index]"
                                        rows="2"
                                        class="min-w-0 flex-1 rounded-md border px-3 py-2 text-sm"
                                    />
                                    <button
                                        type="button"
                                        class="text-destructive shrink-0 text-xs"
                                        @click="removeListItem(field, index)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div>
                            <p
                                class="text-muted-foreground text-xs font-medium"
                            >
                                SUGGESTED TITLE
                            </p>
                            <h3 class="mt-1 text-lg font-semibold">
                                {{ savedContentPlan.suggested_title }}
                            </h3>
                        </div>

                        <div>
                            <p class="text-sm font-medium">Content Brief</p>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{ savedContentPlan.content_brief }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-medium">Outline</p>
                            <div class="mt-2 space-y-3">
                                <div
                                    v-for="(
                                        item, index
                                    ) in savedContentPlan.outline"
                                    :key="index"
                                    class="bg-muted/50 rounded-md p-3"
                                >
                                    <p class="font-medium">
                                        {{ item.heading }}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-sm"
                                    >
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
                                    v-for="(
                                        point, index
                                    ) in savedContentPlan.key_points"
                                    :key="index"
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
                                    v-for="(
                                        task, index
                                    ) in savedContentPlan.production_tasks"
                                    :key="index"
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
                                    v-for="(
                                        risk, index
                                    ) in savedContentPlan.risks_or_missing_information"
                                    :key="index"
                                >
                                    {{ risk }}
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <button
            v-else
            type="button"
            class="hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="
                generationRequestPending ||
                isGenerationRequestInFlight(props.projectId)
            "
            @click="generateContentPlan"
        >
            Generate Content Plan
        </button>

        <p v-if="error" class="text-destructive mt-3 text-sm">
            {{ error }}
        </p>
    </div>
</template>
