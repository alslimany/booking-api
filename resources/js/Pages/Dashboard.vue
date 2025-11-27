<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';
import currency from 'currency.js'

const props = defineProps({
    aero_tokens: Array,
    prices: Object,
})
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white shadow-md rounded-xl">
                        <thead>
                            <tr class="bg-blue-gray-100 text-gray-700">
                                <th class="py-3 px-4 text-left">#</th>
                                <th class="py-3 px-4 text-left">Token name</th>
                                <th class="py-3 px-4 text-left">Iata code</th>
                                <th class="py-3 px-4 text-left">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="text-blue-gray-900">
                            <tr class="border-b border-blue-gray-200" v-for="(aero_token, index) in aero_tokens">
                                <td class="py-3 px-4">{{  index + 1 }}</td>
                                <td class="py-3 px-4">{{ aero_token.name}}</td>
                                <td class="py-3 px-4">{{ aero_token.iata}}</td>
                                <td class="py-3 px-4">


                                    {{ currency(aero_token.data.balance, {separator: ',', symbol: ''}).format() }}
                                    
                                    {{  aero_token.data.currency_code }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-blue-gray-200">
                                <td class="py-3 px-4" colspan="3">Totals</td>
                                <td class="py-3 px-4">
                                    <span v-for="(price,  curr) in prices" :key="curr">
                                        {{ currency(price, {separator: ',', symbol: ''}).format() }} {{ curr }}
                                        <br/>
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
