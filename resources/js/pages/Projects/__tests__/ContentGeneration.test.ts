import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ContentGeneration from '../ContentGeneration.vue';

const plan = {
    suggested_title: 'Original title',
    content_brief: 'Original brief',
    outline: [{ heading: 'Introduction', purpose: 'Introduce the topic.' }],
    key_points: ['Original point'],
    production_tasks: ['Original task'],
    risks_or_missing_information: ['Original risk'],
};

const generation = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    generation_number: 1,
    status: 'completed',
    response: plan,
    draft: null,
    source_generation_id: null,
    regeneration_instructions: null,
    model: 'gpt-4o-mini',
    input_tokens: null,
    output_tokens: null,
    error_code: null,
    error_message: null,
    processing_started_at: null,
    completed_at: '2026-09-22T10:00:00Z',
    ...overrides,
});

const jsonResponse = (body: unknown, status = 200): Response =>
    new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });

describe('ContentGeneration review interactions', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn((url: string) => {
                if (url.endsWith('/generations')) {
                    return Promise.resolve(
                        jsonResponse({
                            generations: [
                                {
                                    id: 1,
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
                    );
                }

                if (url.endsWith('/accepted-plan')) {
                    return Promise.resolve(
                        jsonResponse({ accepted_content_plan: null }),
                    );
                }

                if (url.endsWith('/generations/1')) {
                    return Promise.resolve(
                        jsonResponse({ generation: generation() }),
                    );
                }

                return Promise.reject(new Error(`Unexpected fetch: ${url}`));
            }),
        );
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('cancels an unsaved edit without changing the saved content', async () => {
        const wrapper = mount(ContentGeneration, {
            props: { projectId: 11, initialGeneration: generation() },
        });

        await wrapper.vm.$nextTick();
        await wrapper.get('button').trigger('click');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Edit')!
            .trigger('click');

        const title = wrapper.get('#content-plan-title');
        await title.setValue('Unsaved title');
        expect((title.element as HTMLInputElement).value).toBe('Unsaved title');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cancel')!
            .trigger('click');

        expect(wrapper.find('#content-plan-title').exists()).toBe(false);
        expect(wrapper.text()).toContain('Original title');
        expect(wrapper.text()).not.toContain('Unsaved title');
    });

    it('keeps an active edit form intact when a background completion update arrives', async () => {
        const processing = generation({
            status: 'processing',
            response: null,
            processing_started_at: '2026-09-22T10:00:00Z',
            completed_at: null,
        });

        const completed = generation({
            response: {
                ...plan,
                suggested_title: 'Background result',
            },
        });

        const fetchMock = vi.mocked(fetch);
        fetchMock.mockImplementation((url: string) => {
            if (url.endsWith('/generations')) {
                return Promise.resolve(
                    jsonResponse({
                        generations: [
                            {
                                id: 1,
                                generation_number: 1,
                                status: 'processing',
                                source_generation_id: null,
                                has_draft: false,
                                is_accepted: false,
                                regeneration_instructions: null,
                                processing_started_at:
                                    processing.processing_started_at,
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

            if (url.endsWith('/generations/1')) {
                return Promise.resolve(jsonResponse({ generation: completed }));
            }

            return Promise.reject(new Error(`Unexpected fetch: ${url}`));
        });

        const wrapper = mount(ContentGeneration, {
            props: { projectId: 11, initialGeneration: processing },
        });

        await wrapper.vm.$nextTick();
        await vi.advanceTimersByTimeAsync(2000);
        await wrapper.vm.$nextTick();

        await wrapper.get('button').trigger('click');
        await wrapper.get('button:has-text("Edit")').trigger('click');

        const title = wrapper.get('#content-plan-title');
        await title.setValue('User is editing this title');

        fetchMock.mockResolvedValue(jsonResponse({ generation: completed }));

        await vi.advanceTimersByTimeAsync(2000);
        await wrapper.vm.$nextTick();

        expect(
            (wrapper.get('#content-plan-title').element as HTMLInputElement)
                .value,
        ).toBe('User is editing this title');
    });
});
