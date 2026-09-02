<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import InternshipProgress from '@/components/InternshipProgress.vue';
import { dashboard } from '@/routes';

interface InternshipProgress {
    projectName: string;
    currentDay: string;
    status: string;
    message: string;
}
const page = usePage<{
    auth: {
        user: {
            name: string;
        };
    };
    internship: InternshipProgress;
}>();
const user = computed(() => page.props.auth.user);

const internshipProgress = page.props.internship;

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <InternshipProgress
                :progress="internshipProgress"
                :student-name="user.name"
            />

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border"
            >
                <PlaceholderPattern />
            </div>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border"
            >
                <PlaceholderPattern />
            </div>
        </div>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
