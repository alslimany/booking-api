<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref } from 'vue';
import { prettyPrintJson } from 'pretty-print-json';

defineProps({
   api_logs: Object,
})

const showModalStatus =  ref(false)
const selectedApiLog = ref();
const showLogInfo = (log) => {
    selectedApiLog.value = log;
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
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                API Logs
            </h2>
        </template>
        <link rel=stylesheet href=https://cdn.jsdelivr.net/npm/pretty-print-json@2.1/dist/css/pretty-print-json.css>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- component -->
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white shadow-md rounded-xl">
                        <thead>
                            <tr class="bg-blue-gray-100 text-gray-700">
                                <th class="py-3 px-4 text-left">#</th>
                                <th class="py-3 px-4 text-left">Duration</th>
                                <th class="py-3 px-4 text-left">User</th>
                                <th class="py-3 px-4 text-left">Method</th>
                                <th class="py-3 px-4 text-left">Url</th>
                                <th class="py-3 px-4 text-left">Create at</th>
                                <th class="py-3 px-4 text-left"></th>
                            </tr>
                        </thead>
                        <tbody class="text-blue-gray-900">
                            <tr class="border-b border-blue-gray-200" v-for="(api_log, index) in api_logs.data">
                                <td class="py-3 px-4">{{  index + 1 }}</td>
                                <td class="py-3 px-4">{{ api_log?.duration}}</td>
                                <td class="py-3 px-4">{{ api_log?.user?.name }}</td>
                                <td class="py-3 px-4">{{ api_log.method }}</td>
                                <td class="py-3 px-4">{{ baseUrl(api_log.url) }}</td>
                                <td class="py-3 px-4">{{ api_log.created_at }}</td>
                                <td @click="showLogInfo(api_log)" class="py-3 px-4">Show Infos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--  -->
        <DialogModal :show="showModalStatus" @close="closeModal">
                <template #title>
                    Log #{{  selectedApiLog?.id }}
                </template>

                <template #content>
                    Log Request
                    <!-- <p>{{ JSON.stringify(selectedApiLog?., null, 4) }}</p> -->

                    <p>Ip : {{  selectedApiLog?.ip }}</p> 
                    <p>Method : {{  selectedApiLog?.method }}</p> 
                    <p>Url : {{  selectedApiLog?.url }}</p> 
                    <p>Duration : {{  selectedApiLog?.duration }} second(s)</p> 
                    <p>Daet time : {{  selectedApiLog?.created_at }}</p> 

                    <pre class="json-container" v-html="prettyPrintJson.toHtml(selectedApiLog?.request_data)"></pre>
                    
                    <p>Log Response</p>
                    <pre class="json-container" v-html="prettyPrintJson.toHtml(selectedApiLog?.response_data)"/>

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
