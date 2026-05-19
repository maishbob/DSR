<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users:   Array,
    station: Object,
});

// ── Add user form ─────────────────────────────────────────────────────────────
const showAddForm = ref(false);

const addForm = useForm({
    name:     '',
    email:    '',
    password: '',
    role:     'operator',
});

function submitAdd() {
    addForm.post(route('users.store'), {
        onSuccess: () => { showAddForm.value = false; addForm.reset(); },
    });
}

// ── Edit user ─────────────────────────────────────────────────────────────────
const editingUser = ref(null);

const editForm = useForm({
    name:     '',
    email:    '',
    password: '',
    role:     'operator',
});

function startEdit(user) {
    editingUser.value = user.id;
    editForm.name     = user.name;
    editForm.email    = user.email;
    editForm.password = '';
    editForm.role     = user.role;
}

function cancelEdit() {
    editingUser.value = null;
    editForm.reset();
}

function submitEdit(user) {
    editForm.put(route('users.update', user.id), {
        onSuccess: () => cancelEdit(),
    });
}

// ── Delete ────────────────────────────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', message: '', onConfirm: () => {} });

function confirmDelete(user) {
    confirmModal.value = {
        show:      true,
        title:     'Remove User',
        message:   `Remove ${user.name} from this station? They will no longer be able to log in.`,
        onConfirm: () => router.delete(route('users.destroy', user.id)),
    };
}

function closeConfirm() { confirmModal.value.show = false; }
function handleConfirm() { confirmModal.value.onConfirm(); closeConfirm(); }

const roleLabel = { operator: 'Operator', manager: 'Manager' };
const roleBadge = {
    operator: 'bg-gray-100 text-gray-700',
    manager:  'bg-blue-100 text-blue-700',
    owner:    'bg-orange-100 text-orange-700',
};
</script>

<template>
    <Head title="Users" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Station Users</h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ station.station_name }}</p>
                </div>
                <button @click="showAddForm = !showAddForm"
                    class="px-4 py-2 bg-orange-500 text-white rounded text-sm font-medium hover:bg-orange-600">
                    + Add User
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Add form -->
                <div v-if="showAddForm" class="bg-white shadow rounded-lg p-6">
                    <h3 class="font-semibold text-gray-700 mb-4">New User</h3>
                    <form @submit.prevent="submitAdd" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Full Name *</label>
                                <input v-model="addForm.name" type="text" required
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                                <p v-if="addForm.errors.name" class="text-xs text-red-600 mt-1">{{ addForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Email *</label>
                                <input v-model="addForm.email" type="email" required
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                                <p v-if="addForm.errors.email" class="text-xs text-red-600 mt-1">{{ addForm.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Password *</label>
                                <input v-model="addForm.password" type="password" required autocomplete="new-password"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                                <p v-if="addForm.errors.password" class="text-xs text-red-600 mt-1">{{ addForm.errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Role *</label>
                                <select v-model="addForm.role" required
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                                    <option value="operator">Operator</option>
                                    <option value="manager">Manager</option>
                                </select>
                                <p v-if="addForm.errors.role" class="text-xs text-red-600 mt-1">{{ addForm.errors.role }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="submit" :disabled="addForm.processing"
                                class="px-4 py-2 bg-orange-500 text-white rounded text-sm font-medium hover:bg-orange-600 disabled:opacity-50">
                                Create User
                            </button>
                            <button type="button" @click="showAddForm = false; addForm.reset()"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded text-sm hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Users table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">

                            <!-- Edit row -->
                            <template v-for="user in users" :key="user.id">
                                <tr v-if="editingUser === user.id" class="bg-blue-50">
                                    <td colspan="4" class="px-4 py-4">
                                        <form @submit.prevent="submitEdit(user)" class="space-y-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="block text-xs text-gray-600 mb-1">Name</label>
                                                    <input v-model="editForm.name" type="text" required
                                                        class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" />
                                                    <p v-if="editForm.errors.name" class="text-xs text-red-600 mt-1">{{ editForm.errors.name }}</p>
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-gray-600 mb-1">Email</label>
                                                    <input v-model="editForm.email" type="email" required
                                                        class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" />
                                                    <p v-if="editForm.errors.email" class="text-xs text-red-600 mt-1">{{ editForm.errors.email }}</p>
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-gray-600 mb-1">New Password <span class="text-gray-400">(leave blank to keep)</span></label>
                                                    <input v-model="editForm.password" type="password" autocomplete="new-password"
                                                        class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" />
                                                    <p v-if="editForm.errors.password" class="text-xs text-red-600 mt-1">{{ editForm.errors.password }}</p>
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-gray-600 mb-1">Role</label>
                                                    <select v-model="editForm.role"
                                                        class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                                                        <option value="operator">Operator</option>
                                                        <option value="manager">Manager</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <button type="submit" :disabled="editForm.processing"
                                                    class="px-3 py-1.5 bg-blue-600 text-white rounded text-xs font-medium hover:bg-blue-700 disabled:opacity-50">
                                                    Save
                                                </button>
                                                <button type="button" @click="cancelEdit"
                                                    class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded text-xs hover:bg-gray-50">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Normal row -->
                                <tr v-else class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ user.name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ user.email }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold"
                                            :class="roleBadge[user.role] ?? 'bg-gray-100 text-gray-700'">
                                            {{ roleLabel[user.role] ?? user.role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <button @click="startEdit(user)"
                                            class="text-xs text-blue-600 hover:underline font-medium">
                                            Edit
                                        </button>
                                        <button @click="confirmDelete(user)"
                                            class="text-xs text-red-600 hover:underline font-medium">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!users.length">
                                <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">
                                    No users yet. Add one above.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-400 px-1">
                    Operators can enter shift data. Managers can also approve DSRs and reopen locked shifts.
                    Owner accounts are managed separately.
                </p>
            </div>
        </div>

        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            variant="danger"
            @confirm="handleConfirm"
            @cancel="closeConfirm"
        />
    </AuthenticatedLayout>
</template>
