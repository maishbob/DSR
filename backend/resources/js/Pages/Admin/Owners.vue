<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    owners: Array,
});

// ── Modal state ────────────────────────────────────────────────
const showModal = ref(false);
const editing   = ref(null);

const form = ref({ name: '', email: '', phone: '', password: '', is_active: true });

function openCreate() {
    editing.value = null;
    form.value = { name: '', email: '', phone: '', password: '', is_active: true };
    showModal.value = true;
}

function openEdit(owner) {
    editing.value = owner;
    form.value = {
        name: owner.name,
        email: owner.email,
        phone: owner.phone || '',
        password: '',
        is_active: owner.is_active,
    };
    showModal.value = true;
}

function save() {
    if (editing.value) {
        router.put(route('admin.owners.update', editing.value.id), {
            name: form.value.name,
            email: form.value.email,
            phone: form.value.phone,
            is_active: form.value.is_active,
        }, { onSuccess: () => { showModal.value = false; } });
    } else {
        router.post(route('admin.owners.store'), form.value, {
            onSuccess: () => { showModal.value = false; },
        });
    }
}

// ── Password reset modal ───────────────────────────────────────
const showPasswordModal = ref(false);
const passwordTarget = ref(null);
const newPassword = ref('');

function openResetPassword(owner) {
    passwordTarget.value = owner;
    newPassword.value = '';
    showPasswordModal.value = true;
}

function submitResetPassword() {
    if (!passwordTarget.value?.user?.id) return;
    router.post(route('admin.users.reset-password', passwordTarget.value.user.id), {
        password: newPassword.value,
    }, {
        onSuccess: () => { showPasswordModal.value = false; },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-800">Manage Owners</h1>
        </template>

        <div class="max-w-5xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <p class="text-sm text-gray-500">{{ owners.length }} owner(s)</p>
                <button @click="openCreate"
                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                    + New Owner
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-medium text-gray-600">Name</th>
                            <th class="text-left px-5 py-3 font-medium text-gray-600">Email</th>
                            <th class="text-left px-5 py-3 font-medium text-gray-600">Phone</th>
                            <th class="text-center px-5 py-3 font-medium text-gray-600">Stations</th>
                            <th class="text-center px-5 py-3 font-medium text-gray-600">Status</th>
                            <th class="text-right px-5 py-3 font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="owner in owners" :key="owner.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5 font-medium text-gray-900">{{ owner.name }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ owner.email }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ owner.phone || '—' }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    {{ owner.stations?.length || 0 }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="owner.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                    {{ owner.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right space-x-3">
                                <button @click="openEdit(owner)"
                                    class="text-orange-600 hover:text-orange-700 text-xs font-medium hover:underline">
                                    Edit
                                </button>
                                <button @click="openResetPassword(owner)"
                                    class="text-slate-600 hover:text-slate-700 text-xs font-medium hover:underline">
                                    Reset Password
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!owners.length">
                            <td colspan="6" class="px-5 py-8 text-center text-gray-400">No owners found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <Transition enter-from-class="opacity-0" leave-to-class="opacity-0"
                enter-active-class="transition duration-200" leave-active-class="transition duration-200">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6" @click.stop>
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ editing ? 'Edit Owner' : 'New Owner' }}</h2>
                        <form @submit.prevent="save" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input v-model="form.name" type="text" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input v-model="form.email" type="email" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input v-model="form.phone" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500" />
                            </div>
                            <div v-if="!editing">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input v-model="form.password" type="password" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500" />
                            </div>
                            <div v-if="editing" class="flex items-center gap-2">
                                <input v-model="form.is_active" type="checkbox" id="ownerActive"
                                    class="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />
                                <label for="ownerActive" class="text-sm text-gray-700">Active</label>
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="showModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-orange-500 rounded-lg hover:bg-orange-600 transition-colors">
                                    {{ editing ? 'Save Changes' : 'Create Owner' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
        <!-- Password Reset Modal -->
        <Teleport to="body">
            <Transition enter-from-class="opacity-0" leave-to-class="opacity-0"
                enter-active-class="transition duration-200" leave-active-class="transition duration-200">
                <div v-if="showPasswordModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPasswordModal = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6" @click.stop>
                        <h2 class="text-lg font-semibold text-gray-800 mb-1">Reset Password</h2>
                        <p class="text-sm text-gray-500 mb-4">{{ passwordTarget?.name }}</p>
                        <form @submit.prevent="submitResetPassword" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                <input v-model="newPassword" type="password" required minlength="6"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500" />
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="showPasswordModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-orange-500 rounded-lg hover:bg-orange-600 transition-colors">
                                    Reset Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
