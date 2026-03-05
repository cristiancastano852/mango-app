export type Company = {
    id: number;
    name: string;
    slug: string;
    logo: string | null;
    timezone: string;
    country: string;
    settings: Record<string, unknown> | null;
    subscription_plan: string;
};

export type User = {
    id: number;
    company_id: number | null;
    name: string;
    email: string;
    avatar: string | null;
    phone: string | null;
    is_active: boolean;
};

export type Department = {
    id: number;
    name: string;
};

export type Position = {
    id: number;
    name: string;
    department_id: number;
};

export type Schedule = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    break_duration: number;
    days_of_week: number[];
};

export type Location = {
    id: number;
    name: string;
    address: string | null;
};

export type Employee = {
    id: number;
    user_id: number;
    company_id: number;
    department_id: number | null;
    position_id: number | null;
    employee_code: string | null;
    hire_date: string | null;
    hourly_rate: string | null;
    salary_type: string;
    schedule_id: number | null;
    location_id: number | null;
    user: User;
    department: Department | null;
    position: Position | null;
    schedule: Schedule | null;
    location: Location | null;
};

export type BreakType = {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
    is_paid: boolean;
    max_duration_minutes: number | null;
    max_per_day: number | null;
    is_default: boolean;
    is_active: boolean;
};

export type BreakEntry = {
    id: number;
    time_entry_id: number;
    break_type_id: number;
    started_at: string;
    ended_at: string | null;
    duration_minutes: number | null;
    notes: string | null;
    break_type: BreakType;
};

export type TimeEntry = {
    id: number;
    employee_id: number;
    company_id: number;
    date: string;
    clock_in: string | null;
    clock_out: string | null;
    gross_hours: string;
    break_hours: string;
    net_hours: string;
    regular_hours: string;
    overtime_hours: string;
    night_hours: string;
    sunday_holiday_hours: string;
    status: string;
    pin_verified: boolean;
    breaks: BreakEntry[];
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};
