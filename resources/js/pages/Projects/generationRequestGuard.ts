import { ref } from 'vue';

const inFlightProjects = ref(new Set<number>());

export const runGenerationRequest = async <T>(
    projectId: number,
    request: () => Promise<T>,
): Promise<T | undefined> => {
    if (inFlightProjects.value.has(projectId)) {
        return undefined;
    }

    const nextProjects = new Set(inFlightProjects.value);
    nextProjects.add(projectId);
    inFlightProjects.value = nextProjects;

    try {
        return await request();
    } finally {
        const remainingProjects = new Set(inFlightProjects.value);
        remainingProjects.delete(projectId);
        inFlightProjects.value = remainingProjects;
    }
};

export const isGenerationRequestInFlight = (projectId: number): boolean =>
    inFlightProjects.value.has(projectId);
