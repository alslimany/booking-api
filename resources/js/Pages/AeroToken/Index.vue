<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';

defineProps({
   aero_tokens: Object,
})
</script>

<template>
    <AppLayout title="Aero Tokens">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Aero Tokens
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- component -->
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white shadow-md rounded-xl">
                        <thead>
                            <tr class="bg-blue-gray-100 text-gray-700">
                                <th class="py-3 px-4 text-left">#</th>
                                <th class="py-3 px-4 text-left">User</th>
                                <th class="py-3 px-4 text-left">Token name</th>
                                <th class="py-3 px-4 text-left">Iata code</th>
                                <th class="py-3 px-4 text-left">Type</th>
                                <th class="py-3 px-4 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-blue-gray-900">
                            <tr class="border-b border-blue-gray-200" v-for="(aero_token, index) in aero_tokens">
                                <td class="py-3 px-4">{{  index + 1 }}</td>
                                <td class="py-3 px-4">{{ aero_token.user.name }}</td>
                                <td class="py-3 px-4">{{ aero_token.name}}</td>
                                <td class="py-3 px-4">{{ aero_token.iata}}</td>
                                <td class="py-3 px-4">{{ aero_token.type }}</td>
                                <td class="py-3 px-4">
                                    <a :href="route('aero-tokens.edit', {aero_token: aero_token.id})" class="font-medium text-blue-600 hover:text-blue-800">Edit</a>
                                </td>
                            </tr>
                            <!-- Add more rows as needed -->
                            <tr class="border-b border-blue-gray-200">
                                <td class="py-3 px-4 font-medium">Total Tokens</td>
                                <td class="py-3 px-4"></td>
                                <td class="py-3 px-4"></td>
                                <td class="py-3 px-4"></td>
                                <td class="py-3 px-4 font-medium">{{ aero_tokens.length}}</td>
                                <td class="py-3 px-4"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
