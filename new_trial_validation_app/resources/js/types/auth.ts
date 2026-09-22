export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: string;
    app_role: string | null;
    department: string | null;
    review_unit: string | null;
    review_team_id: number | null;
    is_active: boolean;
    created_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
