<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    data: Object,
    from: String,
    to: String,
});


</script>

<template>
    <Head :title="`Statement — ${data?.customer?.customer_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('credits.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-800">
                    Credit Statement — {{ data?.customer?.customer_name }}
                </h1>
            </div>
        </template>

        <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-400 text-xs">Customer</p>
                    <p class="font-semibold text-gray-800">{{ data?.customer?.customer_name }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">Period</p>
                    <p class="font-medium text-gray-700">{{ fmtDate(from) }} — {{ fmtDate(to) }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">Credit Limit</p>
                    <p class="font-medium text-gray-700">KES {{ fmt(data?.customer?.credit_limit) }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">Current Balance</p>
                    <p class="font-bold text-red-600 text-lg">KES {{ fmt(data?.balance) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Type</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Product</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Qty (L)</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Amount</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(txn, i) in data?.transactions" :key="i"
                        class="border-t border-gray-100"
                        :class="txn.type === 'payment' ? 'bg-green-50' : ''">
                        <td class="px-4 py-2 text-gray-700">{{ fmtDate(txn.date) }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 text-xs rounded-full capitalize"
                                :class="txn.type === 'payment' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                {{ txn.type }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-600">{{ txn.product ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">{{ txn.quantity ? fmt(txn.quantity, 3) : '—' }}</td>
                        <td class="px-4 py-2 text-right font-medium"
                            :class="txn.type === 'payment' ? 'text-green-600' : 'text-red-600'">
                            {{ txn.type === 'payment' ? '+' : '-' }} KES {{ fmt(Math.abs(txn.amount)) }}
                        </td>
                        <td class="px-4 py-2 text-right font-semibold text-gray-800">
                            KES {{ fmt(txn.balance) }}
                        </td>
                    </tr>
                    <tr v-if="!data?.transactions?.length">
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">No transactions in selected period.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
