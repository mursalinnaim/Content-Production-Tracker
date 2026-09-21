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
    draft: null,
    source_generation_id: null,
    regeneration_instructions: null,
    generation_number: 1,
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

const completedPlan = {
    suggested_title: 'Test title',
    content_brief: 'Test brief',
    outline: [{ heading: 'Intro', purpose: 'Introduce the topic.' }],
    key_points: ['Point one'],
    production_tasks: ['Task one'],
    risks_or_missing_information: ['Risk one'],
};

const completedGeneration = (overrides = {}) =>
    generation({
        generation_number: 1,
        status: 'completed',
        response: completedPlan,
        draft: null,
        ...overrides,
    });

const jsonResponse = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });

const requestUrl = (input: RequestInfo | URL): string => {
    if (typeof input === 'string') {
        return input;
    }

    return input instanceof URL ? input.toString() : input.url;
};

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

        vi.spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(jsonResponse({ generations: [] }))
            .mockResolvedValueOnce(
                jsonResponse({ accepted_content_plan: null }),
            )
            .mockReturnValueOnce(request);

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 2002,
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
        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(fetchMock.mock.calls.map(([url]) => url)).not.toContain(
            '/projects/3/generations/1',
        );
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

        await flushPromises();

        expect(vi.getTimerCount()).toBe(1);

        wrapper.unmount();

        expect(vi.getTimerCount()).toBe(0);

        fetchMock.mockClear();

        await vi.advanceTimersByTimeAsync(2000);

        expect(fetchMock).not.toHaveBeenCalled();
    });
});

describe('content plan review', () => {
    it('loads history and renders a completed generation for review', async () => {
        vi.spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                jsonResponse({
                    generations: [
                        {
                            id: 7,
                            generation_number: 2,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: false,
                            is_accepted: false,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: '2026-09-22T10:00:00Z',
                        },
                    ],
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({ accepted_content_plan: null }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    generation: completedGeneration({
                        id: 7,
                        generation_number: 2,
                    }),
                }),
            );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({
                    id: 7,
                    generation_number: 2,
                }),
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('Generation #2');

        await wrapper
            .get('button', { text: /Content Generation/ })
            .trigger('click');

        expect(wrapper.text()).toContain('completed');
        expect(wrapper.text()).toContain('Test title');
        expect(wrapper.text()).toContain('Edit');
    });

    it('keeps generation review controls inside a collapsed dropdown', async () => {
        vi.spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                jsonResponse({
                    generations: [
                        {
                            id: 7,
                            generation_number: 1,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: false,
                            is_accepted: false,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: null,
                        },
                    ],
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({ accepted_content_plan: null }),
            );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({ id: 7 }),
            },
        });

        await flushPromises();

        expect(
            wrapper
                .get('button', { text: /Content Generation/ })
                .attributes('aria-expanded'),
        ).toBe('false');
        expect(wrapper.text()).not.toContain('Accept this plan');

        await wrapper
            .get('button', { text: /Content Generation/ })
            .trigger('click');

        expect(
            wrapper
                .get('button', { text: /Content Generation/ })
                .attributes('aria-expanded'),
        ).toBe('true');
        expect(wrapper.text()).toContain('Accept this plan');
    });

    it('saves an edited draft without starting another generation', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                jsonResponse({
                    generations: [
                        {
                            id: 7,
                            generation_number: 1,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: false,
                            is_accepted: false,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: '2026-09-22T10:00:00Z',
                        },
                    ],
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({ accepted_content_plan: null }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    generation: completedGeneration({
                        id: 7,
                        draft: {
                            ...completedPlan,
                            suggested_title: 'Edited title',
                        },
                    }),
                }),
            );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({ id: 7 }),
            },
        });

        await flushPromises();
        await wrapper
            .findAll('button')
            .find((button) => /Content Generation/.test(button.text()))!
            .trigger('click');
        await wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Edit')!
            .trigger('click');
        await flushPromises();
        if (
            wrapper
                .get('button', { text: /Content Generation/ })
                .attributes('aria-expanded') === 'false'
        ) {
            await wrapper
                .get('button', { text: /Content Generation/ })
                .trigger('click');
        }
        expect(wrapper.get('#content-plan-title').exists()).toBe(true);
        const title = wrapper.get('#content-plan-title');
        await title.setValue('Edited title');
        await wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Save draft')!
            .trigger('click');

        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/projects/10/generations/7/draft',
            expect.objectContaining({ method: 'PUT' }),
        );
        expect(fetchMock).not.toHaveBeenCalledWith(
            '/projects/10/generations',
            expect.objectContaining({ method: 'POST' }),
        );
        expect(wrapper.text()).toContain('Edited title');
    });

    it('restores a saved draft when the project is reopened', async () => {
        const savedDraft = {
            ...completedPlan,
            suggested_title: 'Restored draft title',
        };

        vi.spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                jsonResponse({
                    generations: [
                        {
                            id: 7,
                            generation_number: 1,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: true,
                            is_accepted: false,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: null,
                        },
                    ],
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({ accepted_content_plan: null }),
            );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({
                    id: 7,
                    draft: savedDraft,
                }),
            },
        });

        await flushPromises();
        await wrapper
            .findAll('button')
            .find((button) => /Content Generation/.test(button.text()))!
            .trigger('click');

        expect(wrapper.text()).toContain('Saved draft');
        expect(wrapper.text()).toContain('Restored draft title');
    });

    it('restores the accepted snapshot when the project is reopened', async () => {
        vi.spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                jsonResponse({
                    generations: [
                        {
                            id: 7,
                            generation_number: 1,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: false,
                            is_accepted: true,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: null,
                        },
                    ],
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    accepted_content_plan: {
                        id: 3,
                        source_generation_id: 7,
                        content: {
                            ...completedPlan,
                            suggested_title: 'Restored accepted title',
                        },
                        accepted_at: '2026-09-22T10:00:00Z',
                    },
                }),
            );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({ id: 7 }),
            },
        });

        await flushPromises();
        await wrapper
            .findAll('button')
            .find((button) => /Content Generation/.test(button.text()))!
            .trigger('click');

        expect(wrapper.text()).toContain('Accepted plan');
        expect(wrapper.text()).toContain('Restored accepted title');
    });

    it('accepts the current completed version', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                jsonResponse({
                    generations: [
                        {
                            id: 7,
                            generation_number: 1,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: false,
                            is_accepted: false,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: null,
                        },
                    ],
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({ accepted_content_plan: null }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    accepted_content_plan: {
                        id: 3,
                        source_generation_id: 7,
                        content: completedPlan,
                        accepted_at: '2026-09-22T10:00:00Z',
                    },
                }),
            );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({ id: 7 }),
            },
        });

        await flushPromises();
        await wrapper
            .findAll('button')
            .find((button) => /Content Generation/.test(button.text()))!
            .trigger('click');
        await wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Accept this plan')!
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenNthCalledWith(
            3,
            '/projects/10/generations/7/accept',
            expect.objectContaining({ method: 'POST' }),
        );
        expect(wrapper.text()).toContain('Accepted');
    });

    it('regenerates with explicit instructions and starts polling the new generation', async () => {
        vi.useFakeTimers();

        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                jsonResponse({
                    generations: [
                        {
                            id: 7,
                            generation_number: 1,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: false,
                            is_accepted: false,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: null,
                        },
                    ],
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({ accepted_content_plan: null }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    generation: {
                        ...completedGeneration({ id: 7 }),
                        generation_number: 2,
                        status: 'pending',
                    },
                }),
            );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({ id: 7 }),
            },
        });

        await flushPromises();
        await wrapper
            .findAll('button')
            .find((button) => /Content Generation/.test(button.text()))!
            .trigger('click');
        const textarea = wrapper.get('textarea');
        await textarea.setValue('Make the outline more concise.');
        await wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Regenerate')!
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/projects/10/generations/7/regenerate',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({
                    instructions: 'Make the outline more concise.',
                }),
            }),
        );
        expect(vi.getTimerCount()).toBe(1);

        wrapper.unmount();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('polls regeneration to completion while preserving the older version', async () => {
        vi.useFakeTimers();

        const regenerated = completedGeneration({
            id: 8,
            generation_number: 2,
            response: {
                ...completedPlan,
                suggested_title: 'Regenerated title',
            },
        });
        const fetchMock = vi.spyOn(globalThis, 'fetch');
        let completed = false;

        fetchMock.mockImplementation((input, init) => {
            const url = requestUrl(input);

            if (init?.method === 'POST' && url.endsWith('/regenerate')) {
                return Promise.resolve(
                    jsonResponse({
                        generation: generation({
                            id: 8,
                            generation_number: 2,
                            status: 'pending',
                            response: null,
                        }),
                    }),
                );
            }

            if (url.endsWith('/generations/8')) {
                completed = true;
                return Promise.resolve(
                    jsonResponse({ generation: regenerated }),
                );
            }

            if (url.endsWith('/generations')) {
                return Promise.resolve(
                    jsonResponse({
                        generations: completed
                            ? [
                                  {
                                      id: 8,
                                      generation_number: 2,
                                      status: 'completed',
                                      source_generation_id: 7,
                                      has_draft: false,
                                      is_accepted: false,
                                      regeneration_instructions:
                                          'Use a smaller-apartment angle.',
                                      processing_started_at: null,
                                      completed_at: null,
                                  },
                                  {
                                      id: 7,
                                      generation_number: 1,
                                      status: 'completed',
                                      source_generation_id: null,
                                      has_draft: false,
                                      is_accepted: false,
                                      regeneration_instructions: null,
                                      processing_started_at: null,
                                      completed_at: null,
                                  },
                              ]
                            : [
                                  {
                                      id: 7,
                                      generation_number: 1,
                                      status: 'completed',
                                      source_generation_id: null,
                                      has_draft: false,
                                      is_accepted: false,
                                      regeneration_instructions: null,
                                      processing_started_at: null,
                                      completed_at: null,
                                  },
                              ],
                    }),
                );
            }

            if (url.endsWith('/accepted-plan')) {
                return Promise.resolve(
                    jsonResponse({ accepted_content_plan: null }),
                );
            }

            return Promise.reject(new Error(`Unexpected fetch: ${url}`));
        });

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({ id: 7 }),
            },
        });

        await flushPromises();
        await wrapper
            .findAll('button')
            .find((button) => /Content Generation/.test(button.text()))!
            .trigger('click');
        await wrapper
            .get('textarea')
            .setValue('Use a smaller-apartment angle.');
        await wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Regenerate')!
            .trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Waiting to start');
        expect(vi.getTimerCount()).toBe(1);

        await vi.advanceTimersByTimeAsync(2000);
        await flushPromises();

        expect(wrapper.text()).toContain('Regenerated title');
        expect(wrapper.text()).toContain('Generation #2');
        expect(vi.getTimerCount()).toBe(0);
    });

    it('keeps loading state independent between projects', async () => {
        let resolveFirstRequest!: (response: Response) => void;
        const firstRequest = new Promise<Response>((resolve) => {
            resolveFirstRequest = resolve;
        });
        const fetchMock = vi.spyOn(globalThis, 'fetch');

        fetchMock.mockImplementation((input, init) => {
            const url = requestUrl(input);

            if (url.endsWith('/generations') && init?.method === 'POST') {
                if (url.startsWith('/projects/21/')) {
                    return firstRequest;
                }

                return Promise.resolve(
                    jsonResponse(
                        { message: 'Project is missing required information.' },
                        422,
                    ),
                );
            }

            if (url.endsWith('/generations')) {
                return Promise.resolve(jsonResponse({ generations: [] }));
            }

            if (url.endsWith('/accepted-plan')) {
                return Promise.resolve(
                    jsonResponse({ accepted_content_plan: null }),
                );
            }

            return Promise.reject(new Error(`Unexpected fetch: ${url}`));
        });

        const first = mount(ContentGeneration, {
            props: { projectId: 21, initialGeneration: null },
        });
        const second = mount(ContentGeneration, {
            props: { projectId: 22, initialGeneration: null },
        });

        await flushPromises();
        await first.get('button').trigger('click');
        await second.get('button').trigger('click');
        await flushPromises();

        expect(first.get('button').attributes('disabled')).toBeDefined();
        expect(second.get('button').attributes('disabled')).toBeUndefined();

        resolveFirstRequest(successfulResponse());
        first.unmount();
        second.unmount();
    });

    it('warns before discarding unsaved edits when switching generations', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        vi.spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                jsonResponse({
                    generations: [
                        {
                            id: 7,
                            generation_number: 2,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: false,
                            is_accepted: false,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: null,
                        },
                        {
                            id: 6,
                            generation_number: 1,
                            status: 'completed',
                            source_generation_id: null,
                            has_draft: false,
                            is_accepted: false,
                            regeneration_instructions: null,
                            processing_started_at: null,
                            completed_at: null,
                        },
                    ],
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({ accepted_content_plan: null }),
            );

        const wrapper = mount(ContentGeneration, {
            props: {
                projectId: 10,
                initialGeneration: completedGeneration({ id: 7 }),
            },
        });

        await flushPromises();
        await wrapper
            .findAll('button')
            .find((button) => /Content Generation/.test(button.text()))!
            .trigger('click');
        await wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Edit')!
            .trigger('click');
        await wrapper.vm.$nextTick();
        if (
            wrapper
                .get('button', { text: /Content Generation/ })
                .attributes('aria-expanded') === 'false'
        ) {
            await wrapper
                .get('button', { text: /Content Generation/ })
                .trigger('click');
        }
        await wrapper.get('#content-plan-title').setValue('Unsaved title');
        await wrapper
            .findAll('button')
            .find((button) => /Generation #1\s+—/.test(button.text()))!
            .trigger('click');

        expect(confirm).toHaveBeenCalled();
        expect(
            (wrapper.get('#content-plan-title').element as HTMLInputElement)
                .value,
        ).toBe('Unsaved title');
    });
});
