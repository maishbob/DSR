<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);

// ── Sidebar state ──────────────────────────────────────────────
const sidebarOpen   = ref(false);
const sidebarPinned = ref(localStorage.getItem('sidebar-pinned') !== 'false');

function togglePin() {
    sidebarPinned.value = !sidebarPinned.value;
    localStorage.setItem('sidebar-pinned', sidebarPinned.value);
}

// ── Flash toast ────────────────────────────────────────────────
const toast     = ref(null);
const toastType = ref('success');
let toastTimer  = null;

watch(() => page.props.flash, (flash) => {
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error)   showToast(flash.error,   'error');
}, { deep: true });

function showToast(msg, type = 'success') {
    toast.value = msg;
    toastType.value = type;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.value = null; }, 4000);
}

// ── Active route ───────────────────────────────────────────────
function isActive(routeName) {
    try { return route().current(routeName); } catch { return false; }
}

// ── Nav icons (Heroicons paths) ────────────────────────────────
const icons = {
    home:               'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    clock:              'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    truck:              'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
    chart:              'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    users:              'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    banknotes:          'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    'credit-card':      'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    receipt:            'M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M9 7V5a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2h-2M9 7h2a2 2 0 012 2v2',
    'chart-bar':        'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    cog:                'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
};

// ── Nav structure ──────────────────────────────────────────────
const openGroup = ref(null);

const navGroups = [
    {
        label: 'Operations',
        items: [
            { label: 'Dashboard',   route: 'dashboard',        icon: 'home' },
            { label: 'Shifts',      route: 'shifts.index',     icon: 'clock' },
            { label: 'Deliveries',  route: 'deliveries.index', icon: 'truck' },
            { label: 'DSR Records', route: 'dsr.index',        icon: 'chart' },
        ],
    },
    {
        label: 'Accounts',
        items: [
            { label: 'Credit Accounts', route: 'credits.index',     icon: 'users' },
            { label: 'Payments',        route: 'payments.index',    icon: 'banknotes' },
            { label: 'Card Recons',     route: 'card-recons.index', icon: 'credit-card' },
            { label: 'POS Account',     route: 'pos-account.index', icon: 'receipt' },
        ],
    },
    {
        label: 'Reports',
        collapsible: true,
        icon: 'chart-bar',
        children: [
            { label: 'Sales Summary', route: 'reports.sales' },
            { label: 'Wet Stock',     route: 'reports.wet-stock' },
            { label: 'Deliveries',    route: 'reports.deliveries' },
            { label: 'Variance',      route: 'reports.variance' },
        ],
    },
    {
        label: 'Settings',
        items: [
            { label: 'Station Settings', route: 'station.settings', icon: 'cog' },
        ],
    },
];

onMounted(() => {
    const reportRoutes = ['reports.sales','reports.wet-stock','reports.deliveries','reports.variance'];
    if (reportRoutes.some(r => isActive(r))) openGroup.value = 'Reports';
});

function toggleGroup(label) {
    openGroup.value = openGroup.value === label ? null : label;
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex font-sans antialiased">

        <!-- Mobile overlay -->
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0"
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200">
            <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-black/60 lg:hidden"
                @click="sidebarOpen = false" />
        </Transition>

        <!-- ── Sidebar ── -->
        <aside class="fixed top-0 left-0 h-full z-40 flex flex-col w-64 bg-slate-900 transition-transform duration-300 ease-in-out"
            :class="(sidebarOpen || sidebarPinned) ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

            <!-- Brand -->
            <div class="flex items-center justify-between h-16 px-5 bg-slate-950 flex-shrink-0">
                <Link :href="route('dashboard')" class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-orange-500 flex items-center justify-center flex-shrink-0 shadow">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-bold text-sm leading-tight tracking-wide">FuelOps</p>
                        <p class="text-slate-400 text-xs leading-tight">DSR Platform</p>
                    </div>
                </Link>
                <button @click="togglePin"
                    class="hidden lg:flex items-center justify-center w-7 h-7 rounded-md text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors"
                    :title="sidebarPinned ? 'Unpin sidebar' : 'Pin sidebar'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                    </svg>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-5">
                <template v-for="group in navGroups" :key="group.label">

                    <!-- Collapsible group -->
                    <div v-if="group.collapsible">
                        <p class="px-2 mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ group.label }}</p>
                        <button @click="toggleGroup(group.label)"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                            :class="openGroup === group.label ? 'bg-slate-800 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white'">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="icons[group.icon]" />
                            </svg>
                            <span class="flex-1 text-left">{{ group.label }}</span>
                            <svg class="w-4 h-4 transition-transform duration-200 flex-shrink-0"
                                :class="openGroup === group.label ? 'rotate-90' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-200 overflow-hidden"
                            leave-active-class="transition-all duration-200 overflow-hidden"
                            enter-from-class="max-h-0 opacity-0" enter-to-class="max-h-48 opacity-100"
                            leave-from-class="max-h-48 opacity-100" leave-to-class="max-h-0 opacity-0">
                            <div v-if="openGroup === group.label" class="mt-1 ml-4 pl-3 border-l border-slate-700 space-y-0.5">
                                <Link v-for="child in group.children" :key="child.route"
                                    :href="route(child.route)"
                                    class="block px-3 py-2 rounded-lg text-sm transition-colors"
                                    :class="isActive(child.route) ? 'bg-orange-500 text-white font-medium' : 'text-slate-400 hover:bg-slate-800 hover:text-white'">
                                    {{ child.label }}
                                </Link>
                            </div>
                        </Transition>
                    </div>

                    <!-- Regular group -->
                    <div v-else>
                        <p class="px-2 mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ group.label }}</p>
                        <div class="space-y-0.5">
                            <Link v-for="item in group.items" :key="item.route"
                                :href="route(item.route)"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors group"
                                :class="isActive(item.route) ? 'bg-orange-500 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white'">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                                    :class="isActive(item.route) ? 'text-white' : 'text-slate-500 group-hover:text-slate-300'">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                                </svg>
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>

                </template>
            </nav>

            <!-- User section -->
            <div class="border-t border-slate-800 p-4 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center flex-shrink-0 shadow">
                        <span class="text-white text-sm font-bold">{{ user?.name?.charAt(0)?.toUpperCase() }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ user?.name }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ user?.email }}</p>
                    </div>
                    <Link :href="route('logout')" method="post" as="button"
                        class="p-1.5 rounded-md text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors"
                        title="Sign out">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- ── Main area ── -->
        <div class="flex-1 flex flex-col min-w-0" :class="sidebarPinned ? 'lg:ml-64' : ''">

            <!-- Top navbar -->
            <header class="sticky top-0 z-20 bg-white border-b border-gray-200 h-16 flex items-center gap-4 px-4 lg:px-6 shadow-sm flex-shrink-0">
                <button @click="sidebarOpen = !sidebarOpen"
                    class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                    :class="sidebarPinned ? 'lg:hidden' : ''">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="flex-1 min-w-0">
                    <slot name="header" />
                </div>

                <div class="flex items-center gap-3 flex-shrink-0">
                    <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-orange-50 border border-orange-200 rounded-full">
                        <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="text-xs font-medium text-orange-700">Live</span>
                    </div>
                    <Link :href="route('profile.edit')"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors group">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center shadow-sm">
                            <span class="text-white text-xs font-bold">{{ user?.name?.charAt(0)?.toUpperCase() }}</span>
                        </div>
                        <span class="hidden md:block text-sm font-medium text-gray-700 group-hover:text-gray-900">{{ user?.name }}</span>
                    </Link>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-auto p-4 lg:p-6">
                <slot />
            </main>

            <!-- Footer -->
            <footer class="px-6 py-3 bg-white border-t border-gray-200 flex items-center justify-between flex-shrink-0">
                <p class="text-xs text-gray-400">FuelOps DSR © {{ new Date().getFullYear() }}</p>
                <p class="text-xs text-gray-400">Powered by Laravel + Vue 3</p>
            </footer>
        </div>

        <!-- ── Toast notification ── -->
        <Transition
            enter-active-class="transition-all duration-300"
            leave-active-class="transition-all duration-300"
            enter-from-class="opacity-0 translate-y-2 scale-95"
            leave-to-class="opacity-0 translate-y-2 scale-95">
            <div v-if="toast"
                class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border max-w-sm"
                :class="toastType === 'success' ? 'bg-white border-green-200 text-green-800' : 'bg-white border-red-200 text-red-800'">
                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                    :class="toastType === 'success' ? 'bg-green-100' : 'bg-red-100'">
                    <svg v-if="toastType === 'success'" class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <p class="text-sm font-medium flex-1">{{ toast }}</p>
                <button @click="toast = null" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </Transition>
    </div>
</template>
