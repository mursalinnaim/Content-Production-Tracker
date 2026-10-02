import { describe, expect, it } from 'vite-plus/test';

import {
    isGenerationRequestInFlight,
    runGenerationRequest,
} from '../../resources/js/pages/Projects/generationRequestGuard';

const deferred = <T>() => {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;

    const promise = new Promise<T>((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
    });

    return { promise, resolve, reject };
};

describe('generation request guard', () => {
    it('blocks repeated clicks for the same project while its request is pending', async () => {
        const firstResponse = deferred<string>();
        let requestCount = 0;

        const first = runGenerationRequest(1, async () => {
            requestCount++;
            return firstResponse.promise;
        });

        expect(isGenerationRequestInFlight(1)).toBe(true);

        const duplicate = await runGenerationRequest(1, async () => {
            requestCount++;
            return 'duplicate';
        });

        expect(duplicate).toBeUndefined();
        expect(requestCount).toBe(1);

        firstResponse.resolve('completed');
        await expect(first).resolves.toBe('completed');
        expect(isGenerationRequestInFlight(1)).toBe(false);
    });

    it('allows another project to start while the first project is pending', async () => {
        const firstResponse = deferred<string>();
        const secondResponse = deferred<string>();
        let requestCount = 0;

        const first = runGenerationRequest(1, async () => {
            requestCount++;
            return firstResponse.promise;
        });
        const second = runGenerationRequest(2, async () => {
            requestCount++;
            return secondResponse.promise;
        });

        expect(requestCount).toBe(2);
        expect(isGenerationRequestInFlight(1)).toBe(true);
        expect(isGenerationRequestInFlight(2)).toBe(true);

        firstResponse.resolve('first completed');
        secondResponse.resolve('second completed');

        await expect(first).resolves.toBe('first completed');
        await expect(second).resolves.toBe('second completed');
        expect(isGenerationRequestInFlight(1)).toBe(false);
        expect(isGenerationRequestInFlight(2)).toBe(false);
    });

    it('clears the guard after a failed request so a later request can run', async () => {
        const failedResponse = deferred<string>();
        let requestCount = 0;

        const failed = runGenerationRequest(3, async () => {
            requestCount++;
            return failedResponse.promise;
        });

        failedResponse.reject(new Error('request failed'));
        await expect(failed).rejects.toThrow('request failed');
        expect(isGenerationRequestInFlight(3)).toBe(false);

        const retry = await runGenerationRequest(3, async () => {
            requestCount++;
            return 'retry completed';
        });

        expect(retry).toBe('retry completed');
        expect(requestCount).toBe(2);
        expect(isGenerationRequestInFlight(3)).toBe(false);
    });
});
