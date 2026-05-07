<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import { fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    logs:       Object,
    filters:    Object,
    actions:    Array,
    modelTypes: Array,
    users:      Array,
});

const filters = reactive({
    from:       props.filters.from       ?? '',
    to:         props.filters.to         ?? '',
    action:     props.filters.action     ?? '',
    model_type: props.filters.model_type ?? '',
    user_id:    props.filters.user_id    ?? '',
    search:     props.filters.search     ?? '',
});

function apply() {
    router.get(route('audit-log.index'), cleanParams(filters), { preserveState: true, preserveScroll: true });
}

function reset() {
    Object.keys(filters).forEach(k => filters[k] = '');
    router.get(route('audit-log.index'));
}

function cleanParams(obj) {
    return Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== '' && v !== null && v !== undefined));
}

// ── Detail modal ──────────────────────────────────────────────
const detail = ref(null);

function openDetail(log) {
    detail.value = log;
}

function closeDetail() {
    detail.value = null;
}

// ── Display helpers ──────────────────────────────────────────
function ts(value) {
    if (!value) return '—';
    const d = new Date(value);
    return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const actionColor = (action) => {
    if (!action) return 'bg-gray-100 text-gray-700';
    if (action.includes('delet'))  return 'bg-red-100 text-red-700';
    if (action.includes('updat'))  return 'bg-blue-100 text-blue-700';
    if (action.includes('creat'))  return 'bg-green-100 text-green-700';
    if (action.includes('approv') || action.includes('lock')) return 'bg-purple-100 text-purple-700';
    if (action.includes('cash'))   return 'bg-orange-100 text-orange-700';
    return 'bg-gray-100 text-gray-700';
};

function diffKeys(oldV, newV) {
    const keys = new Set([...Object.keys(oldV ?? {}), ...Object.keys(newV ?? {})]);
    return [...keys].sort();
}

function valueOf(obj, key) {
    if (!obj || obj[key] === undefined || obj[key] === null) return '—';
    if (typeof obj[key] === 'object') return JSON.stringify(obj[key]);
    return String(obj[key]);
}

function changed(oldV, newV, key) {
    return JSON.stringify(oldV?.[key]) !== JSON.stringify(newV?.[key]);
}
</script>

<template>
    <Head title="Audit Log" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Audit Log</h1>
        </template>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">From</label>
                    <input type="date" v-model="filters.from"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">To</label>
                    <input type="date" v-model="filters.to"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Action</label>
                    <select v-model="filters.action"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All actions</option>
                        <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Record Type</label>
                    <select v-model="filters.model_type"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All records</option>
                        <option v-for="m in modelTypes" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">User</label>
                    <select v-model="filters.user_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All users</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button @click="apply" type="button"
                        class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600">
                        Apply
                    </button>
                    <button @click="reset" type="button"
                        class="border border-gray-300 px-3 py-2 rounded-lg text-sm hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <input v-model="filters.search" @keyup.enter="apply"
                    type="search" placeholder="Search action, record type, IP…"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">When</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">User</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Action</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Record</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">IP</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id" class="border-t border-gray-100 hover:bg-gray-50/50">
                        <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ ts(log.created_at) }}</td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-800">{{ log.user?.name ?? 'System' }}</div>
                            <div class="text-xs text-gray-500 capitalize">{{ log.user?.role ?? '' }}</div>
                        </td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 text-xs rounded-full font-medium" :class="actionColor(log.action)">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                            <span class="font-medium">{{ log.model_type }}</span>
                            <span v-if="log.model_id" class="text-gray-400">#{{ log.model_id }}</span>
                        </td>
                        <td class="px-4 py-2 text-gray-500 text-xs">{{ log.ip_address ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="log.old_values || log.new_values"
                                @click="openDetail(log)" type="button"
                                class="text-orange-600 hover:text-orange-700 text-xs font-medium">
                                View changes
                            </button>
                            <span v-else class="text-gray-300 text-xs">—</span>
                        </td>
                    </tr>
                    <tr v-if="!logs.data?.length">
                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                            No audit log entries match the current filters.
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1"
                class="px-4 py-3 border-t border-gray-100 flex justify-between items-center text-sm bg-gray-50/50">
                <span class="text-gray-500">
                    Page <span class="font-medium text-gray-700">{{ logs.current_page }}</span> of {{ logs.last_page }}
                    · {{ logs.total }} total
                </span>
                <div class="flex gap-1.5">
                    <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" preserve-scroll
                        class="inline-flex items-center gap-1 px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-white hover:border-gray-300 text-gray-600 font-medium">
                        Prev
                    </Link>
                    <Link v-if="logs.next_page_url" :href="logs.next_page_url" preserve-scroll
                        class="inline-flex items-center gap-1 px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-white hover:border-gray-300 text-gray-600 font-medium">
                        Next
                    </Link>
                </div>
            </div>
        </div>

        <!-- Detail modal -->
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0"
            enter-active-class="transition-opacity duration-150"
            leave-active-class="transition-opacity duration-150">
            <div v-if="detail"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4"
                @click.self="closeDetail">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <div>
                            <h2 class="font-semibold text-gray-800">
                                {{ detail.action }} — {{ detail.model_type }}<span v-if="detail.model_id" class="text-gray-400">#{{ detail.model_id }}</span>
                            </h2>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ detail.user?.name ?? 'System' }} · {{ ts(detail.created_at) }}
                                <span v-if="detail.ip_address"> · {{ detail.ip_address }}</span>
                            </p>
                        </div>
                        <button @click="closeDetail"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="overflow-y-auto px-6 py-4">
                        <table class="w-full text-sm border border-gray-100 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Field</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Before</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">After</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="key in diffKeys(detail.old_values, detail.new_values)" :key="key"
                                    class="border-t border-gray-100"
                                    :class="changed(detail.old_values, detail.new_values, key) ? 'bg-amber-50/40' : ''">
                                    <td class="px-3 py-2 font-medium text-gray-700 align-top">{{ key }}</td>
                                    <td class="px-3 py-2 text-gray-500 align-top break-all">
                                        {{ valueOf(detail.old_values, key) }}
                                    </td>
                                    <td class="px-3 py-2 align-top break-all"
                                        :class="changed(detail.old_values, detail.new_values, key) ? 'text-gray-900 font-medium' : 'text-gray-500'">
                                        {{ valueOf(detail.new_values, key) }}
                                    </td>
                                </tr>
                                <tr v-if="!diffKeys(detail.old_values, detail.new_values).length">
                                    <td colspan="3" class="px-3 py-6 text-center text-gray-400">No field-level data recorded.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>
