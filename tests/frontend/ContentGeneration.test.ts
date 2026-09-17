import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';

import ContentGeneration from '../../resources/js/pages/Projects/ContentGeneration.vue';
import type { ProjectGeneration } from '../../resources/js/pages/Projects/types';

const generation = (
    overrides: Partial<ProjectGeneration> = {},
): ProjectGeneration => ({
    id: 1,
    status: 'pending',
    response: null,
    model: 'gpt-4o-mini',
    input_tokens: null,
    output_tokens: null,
    error_code: null,
    error_message: null,
    ...overrides,
});

const successfulResponse = () =>
    new Response(
        JSON.stringify({
            generation: generation({
                status: 'pending',
            }),
        }),
        {
            status: 202,
            headers: {
                'Content-Type': 'application/json',
            },
        },
    );

afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
});

describe('ContentGeneration', () => {
    it('shows a request error when the generation POST fails before a generation exists', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response(
                JSON.stringify({
                    message: 'You cannot generate content right now.',
                }),
                {
                    status: 422,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                },
            ),
        );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 1,
                initialGeneration: null,
            },
        });

        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain(
            'You cannot generate content right now.',
        );
    });

    it('disables the generate button while the request is pending', async () => {
        let resolveRequest!: (response: Response) => void;

        const request = new Promise<Response>((resolve) => {
            resolveRequest = resolve;
        });

        vi.spyOn(globalThis, 'fetch').mockReturnValue(request);

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 2,
                initialGeneration: null,
            },
        });

        const button = wrapper.get('button');

        expect(button.attributes('disabled')).toBeUndefined();

        await button.trigger('click');
        await wrapper.vm.$nextTick();

        expect(button.attributes('disabled')).toBeDefined();

        resolveRequest(successfulResponse());
        await flushPromises();

        expect(button.attributes('disabled')).toBeDefined();
    });

    it('does not start polling when the POST resolves after unmount', async () => {
        vi.useFakeTimers();

        let resolveRequest!: (response: Response) => void;

        const request = new Promise<Response>((resolve) => {
            resolveRequest = resolve;
        });

        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockReturnValue(request);

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 3,
                initialGeneration: null,
            },
        });

        await wrapper.get('button').trigger('click');

        wrapper.unmount();

        expect(vi.getTimerCount()).toBe(0);

        resolveRequest(successfulResponse());
        await flushPromises();

        expect(vi.getTimerCount()).toBe(0);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('cleans up the polling timer when unmounted', async () => {
        vi.useFakeTimers();

        const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response(
                JSON.stringify({
                    generation: generation({
                        status: 'pending',
                    }),
                }),
                {
                    status: 200,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                },
            ),
        );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 4,
                initialGeneration: generation({
                    status: 'pending',
                }),
            },
        });

        await wrapper.vm.$nextTick();

        expect(vi.getTimerCount()).toBe(1);

        wrapper.unmount();

        expect(vi.getTimerCount()).toBe(0);

        await vi.advanceTimersByTimeAsync(2000);

        expect(fetchMock).not.toHaveBeenCalled();
    });
});
