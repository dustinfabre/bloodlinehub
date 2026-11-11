// Plans, bank accounts and bank-transfer payments (billing page and admin dashboard)

export type PaymentRegion = 'local' | 'international';

export type PaymentStatus = 'pending' | 'approved' | 'rejected';

export type FeedbackCategory = 'bug' | 'idea' | 'question' | 'other';

export interface Plan {
    id: number;
    name: string;
    description: string | null;
    duration_months: number;
    // Decimal strings, e.g. "1500.00"; price_usd is null when the plan isn't sold abroad
    price_php: string;
    price_usd: string | null;
}

// An account payers send money to, as the billing page shows it
export interface PayerAccount {
    id: number;
    region: PaymentRegion;
    bank_name: string;
    account_name: string;
    account_number: string;
    swift_code: string | null;
    bank_address: string | null;
    currency: 'PHP' | 'USD';
    instructions: string | null;
    has_qr: boolean;
    // Added to the QR code URL so a replaced code isn't served from the browser cache
    qr_version: number | null;
}

// A Laravel paginator as Inertia receives it
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
