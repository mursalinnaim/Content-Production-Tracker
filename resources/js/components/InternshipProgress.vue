<script setup lang="ts">
interface InternshipProgress {
    studentName: string;
    projectName: string;
    currentDay: string;
    status: string;
    message: string;
}

defineProps<{
    progress: InternshipProgress;
}>();
</script>

<template>
    <div class="rounded-xl border p-6">
        <h2 class="text-xl font-semibold">
            Internship Progress
        </h2>

        <div class="mt-4 space-y-2">
            <p>
                <span class="font-medium">Student:</span>
                {{ progress.studentName }}
            </p>

            <p>
                <span class="font-medium">Project:</span>
                {{ progress.projectName }}
            </p>

            <p>
                <span class="font-medium">Current day:</span>
                {{ progress.currentDay }}
            </p>

            <p>
                <span class="font-medium">Status:</span>
                {{ progress.status }}
            </p>
        </div>

        <p class="mt-4">
            {{ progress.message }}
        </p>
    </div>
</template>
