<script setup lang="ts">
import type { ContentPlan } from './types';

interface Props {
    plan: ContentPlan;
}

defineProps<Props>();
</script>

<template>
    <div class="space-y-5">
        <div>
            <p class="text-muted-foreground text-xs font-medium">
                SUGGESTED TITLE
            </p>
            <h3 class="mt-1 text-lg font-semibold">
                {{ plan.suggested_title }}
            </h3>
        </div>

        <div>
            <p class="text-sm font-medium">Content Brief</p>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ plan.content_brief }}
            </p>
        </div>

        <div>
            <p class="text-sm font-medium">Outline</p>
            <div class="mt-2 space-y-3">
                <div
                    v-for="(item, index) in plan.outline"
                    :key="index"
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
                <li v-for="(point, index) in plan.key_points" :key="index">
                    {{ point }}
                </li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-medium">Production Tasks</p>
            <ul
                class="text-muted-foreground mt-2 list-disc space-y-1 pl-5 text-sm"
            >
                <li v-for="(task, index) in plan.production_tasks" :key="index">
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
                    v-for="(risk, index) in plan.risks_or_missing_information"
                    :key="index"
                >
                    {{ risk }}
                </li>
            </ul>
        </div>
    </div>
</template>
