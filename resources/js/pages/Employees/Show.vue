<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2, ArrowLeft } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';
import type { BreadcrumbItem, Employee } from '@/types';

type Props = {
    employee: Employee;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: { url: '/dashboard', method: 'get' } },
    { title: 'Employees', href: employeesIndex() },
    { title: props.employee.user.name, href: { url: `/employees/${props.employee.id}`, method: 'get' } },
];

function deleteEmployee() {
    if (confirm(`¿Eliminar a ${props.employee.user.name}?`)) {
        router.delete(`/employees/${props.employee.id}`);
    }
}
</script>

<template>
    <Head :title="employee.user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 md:p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child>
                        <Link href="/employees">
                            <ArrowLeft class="size-4" />
                        </Link>
                    </Button>
                    <div class="flex items-center gap-3">
                        <div class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary text-lg font-semibold">
                            {{ employee.user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <h1 class="text-xl font-bold">{{ employee.user.name }}</h1>
                            <p class="text-muted-foreground text-sm">{{ employee.user.email }}</p>
                        </div>
                        <Badge v-if="!employee.user.is_active" variant="secondary">Inactivo</Badge>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/employees/${employee.id}/edit`">
                            <Pencil class="mr-2 size-4" />
                            Editar
                        </Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="deleteEmployee">
                        <Trash2 class="mr-2 size-4" />
                        Eliminar
                    </Button>
                </div>
            </div>

            <!-- Details -->
            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Información Personal</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Teléfono</span>
                            <span>{{ employee.user.phone ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Código</span>
                            <Badge variant="outline" class="font-mono">{{ employee.employee_code ?? '—' }}</Badge>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Fecha contratación</span>
                            <span>{{ employee.hire_date ?? '—' }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Organización</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Departamento</span>
                            <span>{{ employee.department?.name ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Cargo</span>
                            <span>{{ employee.position?.name ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Horario</span>
                            <span>{{ employee.schedule?.name ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Sede</span>
                            <span>{{ employee.location?.name ?? '—' }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Salario</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Tipo</span>
                            <span>{{ employee.salary_type === 'hourly' ? 'Por hora' : 'Mensual' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Valor hora</span>
                            <span>{{ employee.hourly_rate ? `$${Number(employee.hourly_rate).toLocaleString('es-CO')}` : '—' }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
