// Plans, bank accounts and bank-transfer payments (billing page and admin dashboard)

export type PaymentRegion = 'local' | 'international';

// A bank or e-wallet account, or PayPal (international only; account_number is the PayPal email)
export type PaymentMethod = 'bank' | 'paypal';

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
    method: PaymentMethod;
    bank_name: string;
    account_name: string;
    account_number: string;
    // International (US-style) receiving details
    account_type: 'checking' | 'savings' | null;
    routing_number: string | null;
    swift_code: string | null;
    bank_address: string | null;
    currency: 'PHP' | 'USD';
    instructions: string | null;
    has_qr: boolean;
    // Added to the QR code URL so a replaced code isn't served from the browser cache
    qr_version: number | null;
}

// Premium's price and free trial as the public pages show them (prices null while it isn't on sale)
export interface Pricing {
    on_sale: boolean;
    duration_months: number;
    trial_days: number;
    price_php: string | null;
    price_usd: string | null;
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
