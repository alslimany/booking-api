<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { onMounted, ref } from 'vue';
import { prettyPrintJson } from 'pretty-print-json';
import XmlViewer from 'vue3-xml-viewer'
import ApexCharts from 'apexcharts';

const props = defineProps({
    command_requests: Object,
    command_requests_chart_data: Object
})

onMounted(() => {
    var options = {
        // series: [{
        //     name: '12:00',
        //     data: [44, 55, 57, 56, 61, 58, 63,]
        // }, {
        //     name: '13:00',
        //     data: [76, 85, 101, 98, 87, 105, 91,]
        // }, {
        //     name: '14:00',
        //     data: [35, 41, 36, 26, 45, 48, 52,]
        // }],

        series: props.command_requests_chart_data?.data?.series,
        chart: {
            type: 'bar',
            height: 350,
            zoom: {
                enabled: true,
                type: 'x',
                autoScaleYaxis: false,
                allowMouseWheelZoom: true,
                zoomedArea: {
                    fill: {
                        color: '#90CAF9',
                        opacity: 0.4
                    },
                    stroke: {
                        color: '#0D47A1',
                        opacity: 0.4,
                        width: 1
                    }
                }
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 5,
                borderRadiusApplication: 'end'
            },
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            // categories: ['Oya', 'Medsky', 'Libyan Wings', 'Buraq', 'Crown', 'Libyan Express', 'Global Air'],
            categories: props.command_requests_chart_data?.tokens,
        },
        yaxis: {
            title: {
                text: 'Number of Requests'
            }
        },
        fill: {
            opacity: 1
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return "" + val + " Request(s)"
                }
            }
        },
    };

    var chart = new ApexCharts(document.querySelector("#chart"), options);
    chart.render();

})
const showModalStatus = ref(false)
const selectedCommandRequest = ref();
const showCommandInfo = (command) => {
    selectedCommandRequest.value = command;
    showModalStatus.value = true;
}

const closeModal = () => {
    showModalStatus.value = false;
}

const baseUrl = (url) => {
    var splitted = url.split('?');
    return splitted[0];
}
</script>

<template>
    <AppLayout title="Command Requests">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Command Requests
            </h2>
        </template>
        <link rel=stylesheet href=https://cdn.jsdelivr.net/npm/pretty-print-json@2.1/dist/css/pretty-print-json.css>



        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="min-w-full bg-white shadow-md rounded-xl mb-4" id="chart"></div>
                <!-- component -->
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white shadow-md rounded-xl">
                        <thead>
                            <tr class="bg-blue-gray-100 text-gray-700">
                                <th class="py-3 px-4 text-left">#</th>
                                <th class="py-3 px-4 text-left">Aero</th>
                                <th class="py-3 px-4 text-left">User</th>
                                <th class="py-3 px-4 text-left">Command</th>
                                <th class="py-3 px-4 text-left">Create at</th>
                                <th class="py-3 px-4 text-left"></th>
                            </tr>
                        </thead>
                        <tbody class="text-blue-gray-900">
                            <tr class="border-b border-blue-gray-200"
                                v-for="(command_request, index) in command_requests?.data">
                                <td class="py-3 px-4">{{ index + 1 }}</td>
                                <td class="py-3 px-4">{{ command_request?.aero_token.name }}</td>
                                <td class="py-3 px-4">{{ command_request?.user?.name }}</td>
                                <td class="py-3 px-4">{{ command_request.command }}</td>
                                <td class="py-3 px-4">{{ command_request.created_at_formatted }}</td>
                                <td @click="showCommandInfo(command_request)" class="py-3 px-4">Show</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--  -->
        <DialogModal :show="showModalStatus" @close="closeModal">
            <template #title>
                Comand #{{ selectedCommandRequest?.id }}
            </template>

            <template #content>
                Command Request
                <!-- <p>{{ JSON.stringify(selectedApiLog?., null, 4) }}</p> -->

                <p>{{ selectedCommandRequest?.command }}</p>


                <p>Response</p>
                <XmlViewer :xml="selectedCommandRequest?.result" />

                <p>Plain Response</p>
                <p>{{ selectedCommandRequest?.result }}</p>
            </template>

            <template #footer>
                <SecondaryButton @click="closeModal">
                    Close
                </SecondaryButton>
            </template>
        </DialogModal>
        <!--  -->
    </AppLayout>
</template>
