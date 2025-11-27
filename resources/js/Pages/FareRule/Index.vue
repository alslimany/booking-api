<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from "@/Components/Pagination.vue";
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
   fare_rules: Object,
   filters: Object, 
   counts: Object
})

const form = useForm({
    status: props.filters.status ?? 'new',
    search: props.filters.search,
})

const doSearch = () => {
    form
            .transform((data) => ({
                ...data,
            }))
            .get(route("fare-rules.index"), {
                preserveState: true,
            });
}
</script>

<template>
    <AppLayout title="Fare Rules">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Fare Rules
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 bg-white shadow-md rounded-xl">
                <!-- component -->
                <form class="overflow-x-auto pt-3" @submit.prevent="doSearch">
                    <div class="sm:flex items-center justify-between">
                    <div class="flex items-center">
                        <a class="rounded-full focus:outline-none focus:ring-2  focus:bg-indigo-50 focus:ring-indigo-800" :href="route('fare-rules.index', {status: 'new'})">
                            <div class="py-2 px-8 " :class="[form?.status == 'new' ? 'bg-indigo-100 text-indigo-700 rounded-full' :'text-gray-600 hover:text-indigo-700 hover:bg-indigo-100 rounded-full']">
                                <p>
                                    New
                                    <span class="bg-red-500 text-red-50 py-1 px-2 text-xs rounded ml-1">{{  props.counts.new }}</span>
                                </p>
                            </div>
                        </a>
                        <a class="rounded-full focus:outline-none focus:ring-2 focus:bg-indigo-50 focus:ring-indigo-800 ml-4 sm:ml-8" :href="route('fare-rules.index', {status: 'changed'})">
                            <div class="py-2 px-8" :class="[form?.status == 'changed' ? 'bg-indigo-100 text-indigo-700 rounded-full' :'text-gray-600 hover:text-indigo-700 hover:bg-indigo-100 rounded-full']">
                                <p>
                                    Changed
                                    <span class="bg-red-500 text-red-50 py-1 px-2 text-xs rounded ml-1">{{  props.counts.changed }}</span>
                                </p>
                            </div>
                        </a>
                        <a class="rounded-full focus:outline-none focus:ring-2 focus:bg-indigo-50 focus:ring-indigo-800 ml-4 sm:ml-8" :href="route('fare-rules.index', {status: 'updated'})">
                            <div class="py-2 px-8" :class="[form?.status == 'updated' ? 'bg-indigo-100 text-indigo-700 rounded-full' :'text-gray-600 hover:text-indigo-700 hover:bg-indigo-100 rounded-full']">
                                <p>Updated 
                                    <span class="bg-red-500 text-red-50 py-1 px-2 text-xs rounded ml-1">{{  props.counts.updated }}</span>
                                </p>
                                
                            </div>
                        </a>

                        <input type="text" placeholder="search" v-model="form.search" />
                    </div>
                    
                </div>
                    <table class="min-w-full ">
                        <thead>
                            <tr class="bg-blue-gray-100 text-gray-700">
                                <th class="py-3 px-4 text-left">#</th>
                                <th class="py-3 px-4 text-left">Aero</th>
                                <th class="py-3 px-4 text-left">Carrier</th>
                                <th class="py-3 px-4 text-left">Fare ID</th>
                                <th class="py-3 px-4 text-left">Rules</th>
                                <th class="py-3 px-4 text-left">Note</th>
                                <th class="py-3 px-4 text-left">status</th>
                                <th class="py-3 px-4 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-blue-gray-900">
                            <tr class="border-b border-blue-gray-200" v-for="(fare_rule, index) in fare_rules.data">
                                <td class="py-3 px-4">{{  index + 1 }}</td>
                                <td class="py-3 px-4">{{ fare_rule?.aero_token?.name }}</td>
                                <td class="py-3 px-4">{{ fare_rule.carrier}}</td>
                                <td class="py-3 px-4">{{ fare_rule.fare_id}}</td>
                                <td class="py-3 px-4">{{ fare_rule.rules }}</td>
                                <td class="py-3 px-4">{{ fare_rule.note }}</td>
                                <td class="py-3 px-4">{{ fare_rule.status }}</td>
                                <td class="py-3 px-4">
                                    <a :href="route('fare-rules.edit', {fare_rule: fare_rule.id})" class="font-medium text-blue-600 hover:text-blue-800">Edit</a>
                                </td>
                            </tr>
                            <!-- Add more rows as needed -->
                            <tr class="border-b border-blue-gray-200">
                                <td class="py-3 px-4 font-medium">Total Fare Rules</td>
                                <td class="py-3 px-4"></td>
                                <td class="py-3 px-4"></td>
                                <td class="py-3 px-4"></td>
                                <td class="py-3 px-4 font-medium">{{ fare_rules.length}}</td>
                                <td class="py-3 px-4"></td>
                            </tr>
                        </tbody>
                    </table>
                </form>

                <Pagination :links="fare_rules.links" />
            </div>
        </div>
    </AppLayout>
</template>
