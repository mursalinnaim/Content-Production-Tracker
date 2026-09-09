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
    status: GenerationStatus;
    response: ContentPlan | null;
    model: string | null;
    input_tokens: number | null;
    output_tokens: number | null;
    error_code: string | null;
    error_message: string | null;
    processing_started_at: string | null;
    completed_at: string | null;
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
