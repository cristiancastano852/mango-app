<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { create as registerCompany } from '@/actions/App/Http/Controllers/CompanyRegistrationController';
import { login } from '@/routes';
</script>

<template>
    <Head title="MangoApp — Control de asistencia para tu empresa" />

    <div class="min-h-screen bg-white text-gray-900">
        <!-- Navbar -->
        <nav class="flex items-center justify-between px-6 py-4 lg:px-16">
            <span class="text-xl font-bold text-orange-500">🥭 MangoApp</span>
            <div class="flex items-center gap-4">
                <a href="#pricing" class="text-sm text-gray-600 hover:text-gray-900">Precios</a>
                <Link :href="login()" class="text-sm text-gray-600 hover:text-gray-900">Iniciar sesión</Link>
                <Link
                    :href="registerCompany()"
                    class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600"
                >
                    Registrar empresa
                </Link>
            </div>
        </nav>

        <!-- Hero -->
        <section class="bg-gradient-to-br from-orange-50 to-amber-50 px-6 py-20 text-center lg:px-16">
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 lg:text-6xl">
                Control de asistencia<br />
                <span class="text-orange-500">para empresas colombianas</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-600">
                Registra entradas, salidas y pausas de tus empleados. Calcula horas extras, nocturnas y dominicales
                según la legislación colombiana. Sin complicaciones.
            </p>
            <div class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                <Link
                    :href="registerCompany()"
                    class="rounded-xl bg-orange-500 px-8 py-4 text-base font-bold text-white shadow-lg hover:bg-orange-600"
                >
                    Empieza gratis — 14 días de prueba
                </Link>
                <a href="#features" class="text-sm font-medium text-gray-600 hover:text-gray-900">Ver características →</a>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="px-6 py-20 lg:px-16">
            <h2 class="mb-12 text-center text-3xl font-bold">Todo lo que necesitas para gestionar tu equipo</h2>
            <div class="mx-auto grid max-w-5xl gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="mb-4 text-3xl">⏱️</div>
                    <h3 class="mb-2 font-bold">Check-in / Check-out</h3>
                    <p class="text-sm text-gray-600">Fichaje desde el celular con PIN de seguridad y registro de pausas.</p>
                </div>
                <div class="rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="mb-4 text-3xl">🇨🇴</div>
                    <h3 class="mb-2 font-bold">Legislación colombiana</h3>
                    <p class="text-sm text-gray-600">Cálculo automático de recargos nocturnos, dominicales y horas extras.</p>
                </div>
                <div class="rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="mb-4 text-3xl">📊</div>
                    <h3 class="mb-2 font-bold">Reportes detallados</h3>
                    <p class="text-sm text-gray-600">Exporta en PDF o Excel. Filtra por empleado, fecha o sede.</p>
                </div>
                <div class="rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="mb-4 text-3xl">🏢</div>
                    <h3 class="mb-2 font-bold">Multi-sede</h3>
                    <p class="text-sm text-gray-600">Gestiona varias sucursales desde un solo panel de control.</p>
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section id="pricing" class="bg-gray-50 px-6 py-20 lg:px-16">
            <h2 class="mb-4 text-center text-3xl font-bold">Planes simples y transparentes</h2>
            <p class="mb-12 text-center text-gray-600">Sin contratos. Cancela cuando quieras.</p>
            <div class="mx-auto grid max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Free -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h3 class="font-bold text-gray-900">Free</h3>
                    <p class="mt-2 text-3xl font-extrabold">$0<span class="text-base font-normal text-gray-500">/mes</span></p>
                    <p class="mt-2 text-sm text-gray-600">Hasta 5 empleados</p>
                    <Link :href="registerCompany()" class="mt-6 block rounded-lg border border-orange-500 px-4 py-2 text-center text-sm font-semibold text-orange-500 hover:bg-orange-50">
                        Empezar gratis
                    </Link>
                </div>
                <!-- Básico -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h3 class="font-bold text-gray-900">Básico</h3>
                    <p class="mt-2 text-3xl font-extrabold">$15<span class="text-base font-normal text-gray-500">/mes</span></p>
                    <p class="mt-2 text-sm text-gray-600">Hasta 25 empleados</p>
                    <Link :href="registerCompany()" class="mt-6 block rounded-lg border border-orange-500 px-4 py-2 text-center text-sm font-semibold text-orange-500 hover:bg-orange-50">
                        Empezar prueba
                    </Link>
                </div>
                <!-- Pro (destacado) -->
                <div class="rounded-2xl border-2 border-orange-500 bg-white p-6 shadow-lg">
                    <div class="mb-2 inline-block rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-600">Más popular</div>
                    <h3 class="font-bold text-gray-900">Pro</h3>
                    <p class="mt-2 text-3xl font-extrabold">$35<span class="text-base font-normal text-gray-500">/mes</span></p>
                    <p class="mt-2 text-sm text-gray-600">Hasta 100 empleados</p>
                    <Link :href="registerCompany()" class="mt-6 block rounded-lg bg-orange-500 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-orange-600">
                        Empezar prueba
                    </Link>
                </div>
                <!-- Enterprise -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h3 class="font-bold text-gray-900">Enterprise</h3>
                    <p class="mt-2 text-3xl font-extrabold">$75<span class="text-base font-normal text-gray-500">/mes</span></p>
                    <p class="mt-2 text-sm text-gray-600">Empleados ilimitados</p>
                    <Link :href="registerCompany()" class="mt-6 block rounded-lg border border-orange-500 px-4 py-2 text-center text-sm font-semibold text-orange-500 hover:bg-orange-50">
                        Contactar
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="px-6 py-10 text-center text-sm text-gray-500 lg:px-16">
            <p>© {{ new Date().getFullYear() }} MangoApp. Hecho con 🥭 para empresas colombianas.</p>
        </footer>
    </div>
</template>
