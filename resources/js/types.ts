export interface SharedProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            unit_id: number | null;
            unit: { id: number; code: string; name: string } | null;
            role: string;
            permissions: string[];
        };
    } | null;
    navigation: NavGroup[];
    unit_options: { id: number; code: string; name: string }[];
    context_unit_id: number | null;
    notifications: {
        unread: number;
        preview: { id: string; title: string; body: string; href: string; read: boolean; at: string | null }[];
    };
    flash: { success?: string | null; error?: string | null };
    app_name: string;
    [key: string]: unknown;
}

export interface NavGroup {
    id: string;
    label?: string;
    items: { label: string; href: string; badge?: number }[];
}

export interface Paginator<T> {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
    current_page: number;
}

export interface TransactionRow {
    id: number;
    number: string;
    unit?: string | null;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    transacted_on: string;
    description: string;
    amount: number;
    category?: string | null;
    reference?: string | null;
    creator?: string | null;
}
