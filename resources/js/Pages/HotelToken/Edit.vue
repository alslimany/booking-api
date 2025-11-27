<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { onMounted } from 'vue';

const props = defineProps({
    aero_token: Object,
    metas: Object,
})

const modes = [
    {
        'id': "api",
        'name': 'API'
    },
    {
        'id': "user_auth",
        'name': 'User authentication'
    }
];

const form = useForm({
    name: props.aero_token.name,
    iata: props.aero_token.iata,
    type: props.aero_token.type,
    token: {
        mode: props.aero_token?.data?.mode ?? 'api',
        auth_user: props.aero_token?.data?.auth_user,
        auth_pass: props.aero_token?.data?.auth_pass,

        url: props.aero_token.data.url,
        // videcom
        api_token: props.aero_token?.data?.api_token,
        // amadeus
        api_key: props.aero_token?.data?.api_key,
        api_secret: props.aero_token?.data?.api_secret
    },
    pricing: {
        currency_code: props.aero_token?.data?.currency_code,
        profit_from: props.aero_token?.data?.profit_from,
        profit_percentage_international: props.aero_token?.data?.profit_percentage_international,
        profit_percentage_domestic: props.aero_token?.data?.profit_percentage_domestic,
        added_tax: props.aero_token?.data?.added_tax,
    },
    airport_management_type: props.aero_token?.data?.airport_management_type ?? 'execulde',
    execluded_airports: props.metas?.execluded_airports ?? [],
    included_airports: props.metas?.included_airports ?? []
});

const store = () => {
    form.put(route('aero-tokens.update', {aero_token: props.aero_token.id}))
}
</script>

<template>
    <AppLayout title="Edit Aero Token">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Aero Token
            </h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <FormSection @submitted="store">
                <template #title>
                    Edit Aero Token
                </template>

                <template #description>
                    <p>This data is used by api router, insure that iata code is unique, there is one iata code for each token.</p>
                    <p class="text-red-500">The token type must be a videcom or amadeus only!</p>
                </template>

                <template #form>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            ref="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="iata" value="IATA Code" />
                        <TextInput
                            id="iata"
                            ref="iata"
                            v-model="form.iata"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.iata" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="type" value="Type" />
                        <TextInput
                            id="type"
                            ref="type"
                            v-model="form.type"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>
                    
                </template>

                <template #actions>
                    <ActionMessage :on="form.recentlySuccessful" class="me-3">
                        Saved.
                    </ActionMessage>

                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Save
                    </PrimaryButton>
                </template>
            </FormSection>

            <FormSection @submitted="store">
                <template #title>
                    Authentication Mode
                </template>

                <template #description>
                    <p></p>
                </template>

                <template #form>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="mode" value="Mode" />
                        <div class="relative z-0 mt-1 border border-gray-200 rounded-lg cursor-pointer">
                            <button
                                v-for="(mode, i) in modes"
                                :key="mode.id"
                                type="button"
                                class="relative px-4 py-3 inline-flex w-full rounded-lg focus:z-10 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                                :class="{'border-t border-gray-200 focus:border-none rounded-t-none': i > 0, 'rounded-b-none': i != Object.keys(modes).length - 1}"
                                @click="form.token.mode = mode.id"
                            >
                                <div :class="{'opacity-50': form.token.mode && form.token.mode != mode.id}">
                                    <!-- Role Name -->
                                    <div class="flex items-center">
                                        <div class="text-sm text-gray-600" :class="{'font-semibold': form.token.mode == mode.id}">
                                            {{ mode.name }}
                                        </div>

                                        <svg v-if="form.token.mode == mode.id" class="ms-2 h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>

                                    <!-- Role Description -->
                                    <div class="mt-2 text-xs text-gray-600 text-start">
                                        {{ mode.name }}
                                    </div>
                                </div>
                            </button>
                        </div>
                        <InputError :message="form.errors?.token?.url" class="mt-2" />
                    </div>

                    <template v-if="form.token.mode == 'api'">
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="url" value="Url" />
                            <TextInput
                                id="url"
                                ref="url"
                                v-model="form.token.url"
                                type="url"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors?.token?.url" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4" v-if="form.type == 'videcom'">
                            <InputLabel for="api_token" value="API Token" />
                            <TextInput
                                id="api_token"
                                ref="api_token"
                                v-model="form.token.api_token"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors?.token?.api_token" class="mt-2" />
                        </div>



                    <!-- Pricing -->
                    <h4>Pricing</h4>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="currency_code" value="Currency Code" />
                        <TextInput
                            id="currency_code"
                            ref="currency_code"
                            v-model="form.pricing.currency_code"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.pricing?.currency_code" class="mt-2" />
                    </div>
                    <!--  -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="profit_from" value="Profit From" />
                        <select
                            v-model="form.pricing.profit_from"
                            class="mt-1 block w-3/4"
                        >
                            <option value="" disabled>Select Option</option>
                            <option value="fare" >Fare Basis</option>
                            <option value="total" >Total Price</option>
                        </select>
                        <InputError :message="form.errors?.pricing?.profit_from" class="mt-2" />
                    </div>
                    <!--  -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="profit_percentage" value="Profit Percentage International (%)" />
                        <TextInput
                            id="profit_percentage"
                            ref="profit_percentage"
                            v-model="form.pricing.profit_percentage_international"
                            type="number"
                            setp="any"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.pricing?.profit_percentage_international" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="profit_percentage" value="Profit Percentage Domestic (%)" />
                        <TextInput
                            id="profit_percentage"
                            ref="profit_percentage"
                            v-model="form.pricing.profit_percentage_domestic"
                            type="number"
                            setp="any"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.pricing?.profit_percentage_domestic" class="mt-2" />
                    </div>
                    
                    <!--  -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="added_tax" value="Added Tax (%)" />
                        <TextInput
                            id="added_tax"
                            ref="added_tax"
                            v-model="form.pricing.added_tax"
                            type="number"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.pricing?.added_tax" class="mt-2" />
                    </div>
                    </template>

                    <template v-if="form.token.mode == 'user_auth'">
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="url" value="Url" />
                            <TextInput
                                id="url"
                                ref="url"
                                v-model="form.token.url"
                                type="url"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors?.token?.url" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="user_name" value="Auth User" />
                            <TextInput
                                id="auth_user"
                                ref="auth_user"
                                v-model="form.token.auth_user"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors?.token?.auth_user" class="mt-2" />
                        </div>
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="user_name" value="Auth Pass" />
                            <TextInput
                                id="auth_pass"
                                ref="auth_pass"
                                v-model="form.token.auth_pass"
                                type="password"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors?.token?.auth_pass" class="mt-2" />
                        </div>
                    </template>

                </template>

                <template #actions>
                    <ActionMessage :on="form.recentlySuccessful" class="me-3">
                        Saved.
                    </ActionMessage>

                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Save
                    </PrimaryButton>
                </template>
            </FormSection>
            <!-- Airport Management -->
            <FormSection @submitted="store">
                <template #title>
                   Airport Management
                </template>

                <template #description>
                    This option determine how the api will handle the defined airports. In case of execlude, the API will execlude the defined airports, wich will affect on schedule sync, and offer pricing.
                    Th case of include, the API will include the defined airports only, which cause the API schedule fetching and pricing on the defined airports only.
                </template>

                <template #form>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="airport_management_type" value="Airport management type" />
                        <select
                            v-model="form.airport_management_type"
                            class="mt-1 block w-3/4"
                        >
                            <option value="" disabled>Select Option</option>
                            <option value="include" >Include Airports</option>
                            <option value="execulde" >Execulde Airports</option>
                        </select>
                        <InputError :message="form.errors?.airport_management_type" class="mt-2" />
                    </div>
                </template>

                <template #actions>
                    <ActionMessage :on="form.recentlySuccessful" class="me-3">
                        Saved.
                    </ActionMessage>

                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Save
                    </PrimaryButton>
                </template>
            </FormSection>

            <!-- Execluded Airports -->
            <FormSection @submitted="store" v-if="form.airport_management_type == 'execulde'">
                <template #title>
                   Exclude Airports
                </template>

                <template #description>
                   
                </template>

                <template #form>
                    <div class="col-span-6 sm:col-span-4" v-for="( execluded_airport, index) in form.execluded_airports">
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            ref="name"
                            :value="execluded_airport"
                            v-model="form.execluded_airports[index]"
                            type="text"
                            class="mt-1 block w-full"
                        />

                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        
                        <button type="button" @click="form.execluded_airports.push('')">+</button>
                    </div>
                    
                </template>

                <template #actions>
                    <ActionMessage :on="form.recentlySuccessful" class="me-3">
                        Saved.
                    </ActionMessage>

                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Save
                    </PrimaryButton>
                </template>
            </FormSection>

            <!-- Included Airports -->
            <FormSection @submitted="store" v-if="form.airport_management_type == 'include'">
                <template #title>
                   Included Airports
                </template>

                <template #description>
                   
                </template>

                <template #form>
                    <div class="col-span-6 sm:col-span-4" v-for="( included_airport, index) in form.included_airports">
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            ref="name"
                            :value="included_airport"
                            v-model="form.included_airports[index]"
                            type="text"
                            class="mt-1 block w-full"
                        />

                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        
                        <button type="button" @click="form.included_airports.push('')">+</button>
                    </div>
                    
                </template>

                <template #actions>
                    <ActionMessage :on="form.recentlySuccessful" class="me-3">
                        Saved.
                    </ActionMessage>

                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Save
                    </PrimaryButton>
                </template>
            </FormSection>

        </div>
    </AppLayout>
</template>
