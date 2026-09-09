const inFlightProjects = new Set<number>();

export const runGenerationRequest = async <T>(
    projectId: number,
    request: () => Promise<T>,
): Promise<T | undefined> => {
    if (inFlightProjects.has(projectId)) {
        return undefined;
    }

    inFlightProjects.add(projectId);

    try {
        return await request();
    } finally {
        inFlightProjects.delete(projectId);
    }
};

export const isGenerationRequestInFlight = (projectId: number): boolean =>
    inFlightProjects.has(projectId);
