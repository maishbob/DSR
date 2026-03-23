<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign In" />

    <div class="min-h-screen flex">

        <!-- Left panel — brand -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between relative overflow-hidden"
            style="background: linear-gradient(135deg, #111827 0%, #1f2937 50%, #111827 100%);">

            <!-- Subtle grid pattern -->
            <div class="absolute inset-0 opacity-5"
                style="background-image: linear-gradient(#f97316 1px, transparent 1px), linear-gradient(90deg, #f97316 1px, transparent 1px); background-size: 40px 40px;"></div>

            <!-- Large decorative circle -->
            <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full opacity-10"
                style="background: radial-gradient(circle, #f97316, transparent);"></div>
            <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full opacity-10"
                style="background: radial-gradient(circle, #f97316, transparent);"></div>

            <!-- Content -->
            <div class="relative z-10 flex flex-col justify-center h-full px-14 xl:px-20">

                <!-- Logo mark -->
                <div class="flex items-center gap-3 mb-16">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                        style="background: #f97316;">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6.75v6.75" />
                        </svg>
                    </div>
                    <span class="text-white font-bold text-xl tracking-tight">DSR System</span>
                </div>

                <!-- Headline -->
                <div class="mb-12">
                    <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-4">
                        Daily Sales<br/>
                        <span style="color: #f97316;">Reconciliation</span>
                    </h1>
                    <p class="text-gray-400 text-lg leading-relaxed max-w-md">
                        Complete fuel station management — meter readings, stock reconciliation, credit accounts, and auditable DSR reports.
                    </p>
                </div>

                <!-- Feature pills -->
                <div class="flex flex-wrap gap-2 mb-12">
                    <span v-for="f in ['Shift Management', 'Stock Variance', 'Credit Accounts', 'Cash Reconciliation', 'DSR Reports']"
                        :key="f"
                        class="px-3 py-1.5 rounded-full text-xs font-medium border"
                        style="border-color: #374151; color: #9ca3af; background: rgba(249,115,22,0.08);">
                        {{ f }}
                    </span>
                </div>

                <!-- Stats strip -->
                <div class="grid grid-cols-3 gap-6 pt-8 border-t" style="border-color: #1f2937;">
                    <div>
                        <p class="text-2xl font-bold" style="color: #f97316;">Live</p>
                        <p class="text-xs text-gray-500 mt-0.5">Real-time data</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-white">100%</p>
                        <p class="text-xs text-gray-500 mt-0.5">Audit trail</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-white">Zero</p>
                        <p class="text-xs text-gray-500 mt-0.5">Silent errors</p>
                    </div>
                </div>
            </div>

            <!-- Bottom credit -->
            <div class="relative z-10 px-14 xl:px-20 pb-8">
                <p class="text-xs text-gray-600">Fuel station operations platform</p>
            </div>
        </div>

        <!-- Right panel — form -->
        <div class="w-full lg:w-1/2 xl:w-2/5 flex items-center justify-center bg-gray-50 px-6 py-12">
            <div class="w-full max-w-sm">

                <!-- Mobile logo -->
                <div class="flex items-center gap-2 mb-10 lg:hidden">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: #f97316;">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6.75v6.75" />
                        </svg>
                    </div>
                    <span class="font-bold text-gray-900">DSR System</span>
                </div>

                <!-- Heading -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900">Welcome back</h2>
                    <p class="text-sm text-gray-500 mt-1">Sign in to your station account</p>
                </div>

                <!-- Status message -->
                <div v-if="status"
                    class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ status }}
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-5">

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Email address
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="you@station.co.ke"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20"
                            :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400/20': form.errors.email }"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Password
                            </label>
                            <Link v-if="canResetPassword" :href="route('password.request')"
                                class="text-xs text-orange-600 hover:text-orange-700">
                                Forgot password?
                            </Link>
                        </div>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20"
                            :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400/20': form.errors.password }"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center gap-2">
                        <input id="remember" v-model="form.remember" type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-orange-500 focus:ring-orange-400" />
                        <label for="remember" class="text-sm text-gray-600 select-none cursor-pointer">
                            Keep me signed in
                        </label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                        style="background: #f97316;"
                        onmouseover="this.style.background='#ea6c0a'"
                        onmouseout="this.style.background='#f97316'">
                        <span v-if="form.processing" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            Signing in…
                        </span>
                        <span v-else>Sign in</span>
                    </button>

                </form>

                <!-- Footer -->
                <p class="mt-10 text-center text-xs text-gray-400">
                    Daily Sales Reconciliation System &mdash; Fuel Station Management
                </p>

            </div>
        </div>
    </div>
</template>
