export type GenerationStatus =
    | 'pending'
    | 'processing'
    | 'completed'
    | 'failed';

export interface OutlineItem {
    heading: string;
    purpose: string;
}

export interface ContentPlan {
    suggested_title: string;
    content_brief: string;
    outline: OutlineItem[];
    key_points: string[];
    production_tasks: string[];
    risks_or_missing_information: string[];
}

export interface ProjectGeneration {
    id: number;
    generation_number: number;
    status: GenerationStatus;
    response: ContentPlan | null;
    draft: ContentPlan | null;
    source_generation_id: number | null;
    regeneration_instructions: string | null;
    model: string | null;
    input_tokens: number | null;
    output_tokens: number | null;
    error_code: string | null;
    error_message: string | null;
    processing_started_at: string | null;
    completed_at: string | null;
}

export interface GenerationHistoryItem {
    id: number;
    generation_number: number;
    status: GenerationStatus;
    source_generation_id: number | null;
    has_draft: boolean;
    is_accepted: boolean;
    regeneration_instructions: string | null;
    processing_started_at: string | null;
    completed_at: string | null;
}

export interface GenerationHistoryResponse {
    generations: GenerationHistoryItem[];
}

export interface Project {
    id: number;
    title: string;
    content_type: string;
    status: string;
    due_date: string | null;
    latest_content_generation: ProjectGeneration | null;
}

export interface GenerationResponse {
    generation: ProjectGeneration;
}

export interface AcceptedContentPlan {
    id: number;
    source_generation_id: number;
    content: ContentPlan;
    accepted_at: string;
}

export interface AcceptedContentPlanResponse {
    accepted_content_plan: AcceptedContentPlan | null;
}

export interface AcceptedContentPlanResult {
    accepted_content_plan: AcceptedContentPlan;
}
