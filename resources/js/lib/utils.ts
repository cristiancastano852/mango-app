import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function formatDecimalHours(
    hours: number | string | null | undefined,
): string {
    const h = Number(hours ?? 0);
    if (isNaN(h)) return '0h 0m';
    const totalMinutes = Math.round(h * 60);
    const hrs = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;
    return `${hrs}h ${mins}m`;
}

export function formatMinutes(minutes: number | null | undefined): string {
    return formatDecimalHours(Math.max(0, minutes ?? 0) / 60);
}

// Lee la hora directamente del string ISO para conservar la hora local de la
// empresa sin importar la zona horaria del navegador.
export function formatTime12h(iso: string | null | undefined): string {
    const match = iso?.match(/T(\d{2}):(\d{2})/);
    if (!match) return '—';
    const hours24 = Number(match[1]);
    const suffix = hours24 >= 12 ? 'PM' : 'AM';
    const hours12 = hours24 % 12 || 12;
    return `${hours12}:${match[2]} ${suffix}`;
}
